<?php

namespace App\Http\Controllers\Api;

use App\Events\NewMessageReceived;
use App\Http\Controllers\Controller;
use App\Jobs\FetchGroupNameJob;
use App\Models\Chat;
use App\Models\ContactAlias;
use App\Models\Conversa;
use App\Models\Message;
use App\Models\WhatsappAccount;
use App\Services\ChatMergeService;
use App\Services\EvolutionApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        try {
            $payload = $request->all();
            $event = $payload['event'] ?? null;
            $instanceName = $payload['instance'] ?? null;

            Log::info('Webhook recebido', ['event' => $event, 'instance' => $instanceName]);

            $eventNormalized = strtoupper(str_replace('.', '_', $event ?? ''));

            return match ($eventNormalized) {
                'QRCODE_UPDATED' => $this->handleQrCode($payload),
                'CONNECTION_UPDATE' => $this->handleConnectionUpdate($payload),
                'MESSAGES_UPSERT' => $this->handleMessagesUpsert($payload),
                'MESSAGES_UPDATE' => $this->handleMessagesUpdate($payload),
                'MESSAGES_DELETE' => $this->handleMessagesDelete($payload),
                'SEND_MESSAGE' => $this->handleSendMessage($payload),
                'SEND_REACTION' => $this->handleReaction($payload),
                'PRESENCE_UPDATE' => $this->handlePresenceUpdate($payload),
                'GROUPS_UPSERT' => $this->handleGroupsUpsert($payload),
                'GROUP_UPDATE' => $this->handleGroupUpdate($payload),
                'GROUP_PARTICIPANTS_UPDATE' => $this->handleGroupParticipantsUpdate($payload),
                default => response()->json(['status' => 'ignored', 'event' => $event]),
            };
        } catch (\Exception $e) {
            Log::error('Webhook error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    protected function handleQrCode(array $payload)
    {
        $instanceName = $payload['instance'] ?? null;
        $qrcode = $payload['data']['qrcode']['base64'] ?? null;

        if ($instanceName && $qrcode) {
            Log::info('QR Code atualizado', ['instance' => $instanceName]);
        }

        return response()->json(['status' => 'ok']);
    }

    protected function handleConnectionUpdate(array $payload)
    {
        $instanceName = $payload['instance'] ?? null;
        $state = $payload['data']['state'] ?? null;

        if (!$instanceName) {
            return response()->json(['status' => 'error', 'message' => 'Instance name missing']);
        }

        $account = WhatsappAccount::where('session_name', $instanceName)->first();

        if ($account) {
            $isConnected = $state === 'open';

            $updateData = [
                'is_connected' => $isConnected,
                'last_connection' => $isConnected ? now() : $account->last_connection,
            ];

            // Salvar owner_jid quando conectar (vem no payload da Evolution)
            if ($isConnected) {
                $ownerJid = $payload['data']['ownerJid']
                    ?? $payload['data']['jid']
                    ?? $payload['data']['instance']['owner']
                    ?? null;

                if ($ownerJid) {
                    $updateData['owner_jid'] = $ownerJid;
                }
            }

            $account->update($updateData);

            Log::info('Connection update', ['instance' => $instanceName, 'state' => $state]);
        }

        return response()->json(['status' => 'ok']);
    }

    protected function handleMessagesUpsert(array $payload)
    {
        $instanceName = $payload['instance'] ?? null;
        $data = $payload['data'] ?? [];

        if (!$instanceName) {
            return response()->json(['status' => 'error', 'message' => 'Instance name missing']);
        }

        $account = WhatsappAccount::where('session_name', $instanceName)->first();

        if (!$account) {
            return response()->json(['status' => 'error', 'message' => 'Account not found']);
        }

        // v1.7.x envia uma mensagem única, v2.x envia array
        if (isset($data['key'])) {
            $this->processMessage($account, $data);
            return response()->json(['status' => 'ok', 'processed' => 1]);
        } else {
            foreach ($data as $messageData) {
                if (is_array($messageData)) {
                    $this->processMessage($account, $messageData);
                }
            }
            return response()->json(['status' => 'ok', 'processed' => count($data)]);
        }
    }

    protected function processMessage(WhatsappAccount $account, array $messageData)
    {
        $key = $messageData['key'] ?? [];
        $message = $messageData['message'] ?? [];

        // Verificar se é uma reação
        if (isset($message['reactionMessage'])) {
            return $this->processReaction($messageData);
        }

        $remoteJid = $key['remoteJid'] ?? null;
        $messageId = $key['id'] ?? null;
        $fromMe = $key['fromMe'] ?? false;
        $participant = $key['participant'] ?? null; // JID do remetente em grupos

        if (!$remoteJid || !$messageId) {
            return;
        }

        // Verificar se é grupo
        $isGroup = str_contains($remoteJid, '@g.us');

        // Verificar se é um LID (ID interno do WhatsApp)
        $isLid = str_contains($remoteJid, '@lid');

        // Nome do remetente
        $senderName = $messageData['pushName'] ?? null;

        // 1. Verificar se existe alias para este JID (LID mapeado para chat principal)
        $alias = ContactAlias::where('account_id', $account->id)
            ->where('alias_jid', $remoteJid)
            ->first();

        if ($alias) {
            // Usar o chat principal ao invés de criar novo
            $chat = $alias->primaryChat;
        } else {
            // Se é um LID sem alias cadastrado E é mensagem enviada (fromMe)
            // Ignorar a mensagem pois não sabemos para qual contato real foi
            if ($isLid && $fromMe) {
                Log::warning('Mensagem fromMe com LID sem alias - ignorando', [
                    'remoteJid' => $remoteJid,
                    'messageId' => $messageId,
                    'pushName' => $senderName,
                    'tip' => 'Envie uma mensagem pelo sistema ou aguarde o contato responder para criar o alias correto',
                ]);
                return;
            }

            // Se é um LID sem alias e mensagem RECEBIDA, tentar criar alias automaticamente
            if ($isLid && !$fromMe && $senderName) {
                // Buscar chat existente com mesmo pushName (pode ser o mesmo contato com JID diferente)
                $existingChat = Chat::where('account_id', $account->id)
                    ->where('chat_type', 'individual')
                    ->where('chat_name', $senderName)
                    ->where('chat_id', 'like', '%@s.whatsapp.net')
                    ->first();

                if ($existingChat) {
                    // Criar alias do LID para o chat existente
                    ContactAlias::create([
                        'account_id' => $account->id,
                        'alias_jid' => $remoteJid,
                        'primary_chat_id' => $existingChat->id,
                    ]);

                    Log::info('Alias criado automaticamente para LID', [
                        'lid' => $remoteJid,
                        'primary_chat' => $existingChat->chat_id,
                        'chat_name' => $senderName,
                    ]);

                    $chat = $existingChat;
                } else {
                    // Não existe chat com esse nome, criar novo chat com o LID
                    // Isso vai criar um chat temporário até identificarmos o número real
                    $chat = Chat::create([
                        'account_id' => $account->id,
                        'chat_id' => $remoteJid,
                        'chat_name' => $senderName ?? 'LID: ' . $this->extractPhoneFromJid($remoteJid),
                        'chat_type' => 'individual',
                    ]);

                    Log::warning('Chat criado para LID sem correspondência', [
                        'lid' => $remoteJid,
                        'chat_name' => $senderName,
                        'chat_id' => $chat->id,
                    ]);
                }
            } else {
                // Para grupos, tentar extrair o nome do grupo do payload
                $groupName = null;
                if ($isGroup) {
                    $groupName = $this->getGroupName($messageData);
                }

                // 2. Buscar ou criar chat pelo JID
                $chat = Chat::firstOrCreate(
                    ['account_id' => $account->id, 'chat_id' => $remoteJid],
                    [
                        'chat_name' => $isGroup
                            ? ($groupName ?? $this->extractPhoneFromJid($remoteJid))
                            : ($senderName ?? $this->extractPhoneFromJid($remoteJid)),
                        'chat_type' => $isGroup ? 'group' : 'individual',
                    ]
                );

                // Para grupos, atualizar nome se estava com ID numérico e agora temos o nome real
                if ($isGroup && $groupName && $chat->chat_name !== $groupName) {
                    // Só atualizar se o nome atual parece ser um ID numérico
                    if (preg_match('/^\d+(-\d+)?$/', $chat->chat_name)) {
                        $chat->update(['chat_name' => $groupName]);
                    }
                }

                // Atualizar nome se estava sem nome e agora tem (para chats individuais)
                if (!$isGroup && !$chat->chat_name && $senderName) {
                    $chat->update(['chat_name' => $senderName]);
                }
            }
        }

        // Para grupos, atualizar nome se veio no payload
        if ($isGroup && isset($messageData['groupSubject'])) {
            $chat->update(['chat_name' => $messageData['groupSubject']]);
        }

        // Para grupos sem nome (ID numérico), disparar job para buscar nome em background
        if ($isGroup && $this->groupNameNeedsUpdate($chat->chat_name)) {
            FetchGroupNameJob::dispatch($chat->id, $account->id, $remoteJid)
                ->delay(now()->addSeconds(5)); // Pequeno delay para não sobrecarregar
        }

        // Determinar tipo e conteúdo da mensagem
        $messageType = 'text';
        $messageText = null;
        $mediaUrl = null;
        $mediaMimeType = null;
        $mediaFilename = null;
        $mediaDuration = null;
        $quotedMessageId = null;
        $quotedText = null;

        // Texto simples
        if (isset($message['conversation'])) {
            $messageText = $message['conversation'];
        } elseif (isset($message['extendedTextMessage'])) {
            $messageText = $message['extendedTextMessage']['text'] ?? null;
            // Verificar se tem mensagem citada
            if (isset($message['extendedTextMessage']['contextInfo']['quotedMessage'])) {
                $quotedMessageId = $message['extendedTextMessage']['contextInfo']['stanzaId'] ?? null;
                $quotedText = $this->extractQuotedText($message['extendedTextMessage']['contextInfo']['quotedMessage']);
            }
        }
        // Imagem
        elseif (isset($message['imageMessage'])) {
            $messageType = 'image';
            $messageText = $message['imageMessage']['caption'] ?? null;
            $mediaMimeType = $message['imageMessage']['mimetype'] ?? 'image/jpeg';
            $mediaUrl = $this->downloadAndSaveMedia($account, $messageId, 'image', $mediaMimeType, $messageData);
            if (isset($message['imageMessage']['contextInfo']['quotedMessage'])) {
                $quotedMessageId = $message['imageMessage']['contextInfo']['stanzaId'] ?? null;
            }
        }
        // Vídeo
        elseif (isset($message['videoMessage'])) {
            $messageType = 'video';
            $messageText = $message['videoMessage']['caption'] ?? null;
            $mediaMimeType = $message['videoMessage']['mimetype'] ?? 'video/mp4';
            $mediaDuration = $message['videoMessage']['seconds'] ?? null;
            $mediaUrl = $this->downloadAndSaveMedia($account, $messageId, 'video', $mediaMimeType, $messageData);
        }
        // Áudio
        elseif (isset($message['audioMessage'])) {
            $messageType = 'audio';
            $mediaMimeType = $message['audioMessage']['mimetype'] ?? 'audio/ogg';
            $mediaDuration = $message['audioMessage']['seconds'] ?? null;
            $mediaUrl = $this->downloadAndSaveMedia($account, $messageId, 'audio', $mediaMimeType, $messageData);
        }
        // Documento
        elseif (isset($message['documentMessage'])) {
            $messageType = 'document';
            $mediaFilename = $message['documentMessage']['fileName'] ?? 'documento';
            $messageText = $mediaFilename;
            $mediaMimeType = $message['documentMessage']['mimetype'] ?? 'application/octet-stream';
            $mediaUrl = $this->downloadAndSaveMedia($account, $messageId, 'document', $mediaMimeType, $messageData, $mediaFilename);
        }
        // Sticker
        elseif (isset($message['stickerMessage'])) {
            $messageType = 'sticker';
            $mediaMimeType = $message['stickerMessage']['mimetype'] ?? 'image/webp';
            $mediaUrl = $this->downloadAndSaveMedia($account, $messageId, 'sticker', $mediaMimeType, $messageData);
        }
        // Localização
        elseif (isset($message['locationMessage'])) {
            $messageType = 'location';
        }
        // Contato
        elseif (isset($message['contactMessage'])) {
            $messageType = 'contact';
            $messageText = $message['contactMessage']['displayName'] ?? 'Contato';
        }

        // Determinar JID do owner (fallback para phone_number se owner_jid estiver nulo)
        $ownerJid = $account->owner_jid ?? (preg_replace('/\D/', '', $account->phone_number) . '@s.whatsapp.net');

        // Determinar JID do remetente real
        $senderJid = $fromMe
            ? $ownerJid
            : ($isGroup ? $participant : $remoteJid);

        // Criar ou atualizar mensagem
        $dbMessage = Message::updateOrCreate(
            ['message_key' => $messageId],
            [
                'chat_id' => $chat->id,
                'from_jid' => $senderJid ?? $remoteJid,
                'sender_name' => $fromMe ? null : $senderName,
                'participant_jid' => $isGroup ? $participant : null,
                'to_jid' => $fromMe ? $remoteJid : $ownerJid,
                'message_text' => $messageText,
                'message_type' => $messageType,
                'media_url' => $mediaUrl,
                'media_mime_type' => $mediaMimeType,
                'media_filename' => $mediaFilename,
                'media_duration' => $mediaDuration,
                'is_from_me' => $fromMe,
                'timestamp' => $messageData['messageTimestamp'] ?? time(),
                'status' => 'delivered',
                'quoted_message_id' => $quotedMessageId,
                'quoted_text' => $quotedText,
                'message_raw' => $messageData,
            ]
        );

        // Atualizar último timestamp do chat
        $chat->update([
            'last_message_timestamp' => $dbMessage->timestamp,
            'unread_count' => $fromMe ? 0 : $chat->unread_count + 1,
        ]);

        // Criar ou atualizar conversa na fila (mensagens recebidas)
        if (!$fromMe) {
            $this->updateConversaQueue($account, $chat, $remoteJid, $messageData, $isGroup);
        }

        // Broadcast mensagem em tempo real
        try {
            broadcast(new NewMessageReceived($dbMessage, $account->id, $chat->id))->toOthers();
        } catch (\Exception $e) {
            Log::warning('Broadcast falhou', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Baixar mídia da Evolution API e salvar no storage
     */
    protected function downloadAndSaveMedia(
        WhatsappAccount $account,
        string $messageId,
        string $type,
        string $mimeType,
        array $messageData,
        ?string $filename = null
    ): ?string {
        try {
            $base64 = null;

            // 1. Primeiro tentar usar base64 que já vem no payload (evita desconexão)
            $message = $messageData['data']['message'] ?? [];
            $typeKey = $type . 'Message';
            if ($type === 'image') $typeKey = 'imageMessage';
            if ($type === 'video') $typeKey = 'videoMessage';
            if ($type === 'audio') $typeKey = 'audioMessage';
            if ($type === 'document') $typeKey = 'documentMessage';
            if ($type === 'sticker') $typeKey = 'stickerMessage';

            // Verificar se o base64 já veio no webhook
            if (isset($message[$typeKey]['base64'])) {
                $base64 = $message[$typeKey]['base64'];
                Log::info('Usando base64 do webhook', ['messageId' => $messageId]);
            }

            // 2. Se não veio, tentar baixar via API (pode causar reconexão)
            if (!$base64) {
                $evolutionService = app(EvolutionApiService::class);
                $result = $evolutionService->downloadMedia($account->session_name, $messageId);

                if ($result['success'] && !empty($result['data']['base64'])) {
                    $base64 = $result['data']['base64'];
                }
            }

            if (!$base64) {
                Log::warning('Mídia não disponível para download', ['messageId' => $messageId]);
                return null;
            }

            $extension = $this->getExtensionFromMimeType($mimeType);

            // Gerar nome do arquivo
            if ($filename) {
                $savedFilename = $messageId . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
            } else {
                $savedFilename = $messageId . '.' . $extension;
            }

            // Salvar no storage
            $path = "media/{$account->id}/{$type}/{$savedFilename}";
            Storage::disk('public')->put($path, base64_decode($base64));

            return Storage::url($path);
        } catch (\Exception $e) {
            Log::error('Erro ao baixar mídia', ['error' => $e->getMessage(), 'messageId' => $messageId]);
            return null;
        }
    }

    protected function getExtensionFromMimeType(string $mimeType): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/3gpp' => '3gp',
            'audio/ogg' => 'ogg',
            'audio/mpeg' => 'mp3',
            'audio/mp4' => 'm4a',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        ];

        return $map[$mimeType] ?? 'bin';
    }

    protected function extractQuotedText(array $quotedMessage): ?string
    {
        if (isset($quotedMessage['conversation'])) {
            return $quotedMessage['conversation'];
        }
        if (isset($quotedMessage['extendedTextMessage']['text'])) {
            return $quotedMessage['extendedTextMessage']['text'];
        }
        if (isset($quotedMessage['imageMessage']['caption'])) {
            return '[Imagem] ' . $quotedMessage['imageMessage']['caption'];
        }
        if (isset($quotedMessage['imageMessage'])) {
            return '[Imagem]';
        }
        if (isset($quotedMessage['videoMessage'])) {
            return '[Vídeo]';
        }
        if (isset($quotedMessage['audioMessage'])) {
            return '[Áudio]';
        }
        if (isset($quotedMessage['documentMessage'])) {
            return '[Documento] ' . ($quotedMessage['documentMessage']['fileName'] ?? '');
        }
        return null;
    }

    protected function getGroupName(array $messageData): ?string
    {
        // Tenta extrair nome do grupo do payload
        return $messageData['groupMetadata']['subject'] ??
               $messageData['groupSubject'] ??
               null;
    }

    protected function fetchGroupNameFromApi(WhatsappAccount $account, string $groupJid): ?string
    {
        try {
            $evolution = app(EvolutionApiService::class);
            $result = $evolution->getGroupInfo($account->session_name, $groupJid);

            // A resposta pode vir em diferentes formatos dependendo da versão da API
            return $result['subject'] ??
                   $result['data']['subject'] ??
                   $result['groupMetadata']['subject'] ??
                   null;
        } catch (\Exception $e) {
            Log::warning('Erro ao buscar info do grupo', [
                'group_jid' => $groupJid,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    protected function updateConversaQueue(
        WhatsappAccount $account,
        Chat $chat,
        string $remoteJid,
        array $messageData,
        bool $isGroup = false
    ) {
        // Para grupos, usar o JID do grupo como número
        $phoneNumber = $this->extractPhoneFromJid($remoteJid);

        // Nome do cliente (para grupos, usar nome do grupo)
        $clienteName = $isGroup
            ? ($chat->chat_name ?? $messageData['groupSubject'] ?? 'Grupo')
            : ($messageData['pushName'] ?? null);

        // Buscar conversa existente
        if ($isGroup) {
            // Para grupos, buscar APENAS pelo chat_id (não pelo nome, que pode ser de um participante)
            $conversa = Conversa::where('account_id', $account->id)
                ->whereIn('status', ['aguardando', 'em_atendimento'])
                ->where('chat_id', $chat->id)
                ->first();
        } else {
            // Para chats individuais, buscar pelo chat_id OU pelo número do cliente
            // Isso resolve o problema de LID vs número real (mesmo contato, JIDs diferentes)
            $conversa = Conversa::where('account_id', $account->id)
                ->whereIn('status', ['aguardando', 'em_atendimento'])
                ->where(function ($query) use ($chat, $phoneNumber, $clienteName) {
                    $query->where('chat_id', $chat->id)
                        ->orWhere('cliente_numero', $phoneNumber);

                    // Se tem nome, também busca pelo nome para pegar conversas com LID
                    if ($clienteName) {
                        $query->orWhere('cliente_nome', $clienteName);
                    }
                })
                ->first();
        }

        if (!$conversa) {
            Conversa::create([
                'cliente_numero' => $phoneNumber,
                'cliente_nome' => $clienteName,
                'chat_id' => $chat->id,
                'account_id' => $account->id,
                'status' => 'aguardando',
                'iniciada_em' => now(),
                'ultima_msg_em' => now(),
                'cliente_aguardando_desde' => now(),
            ]);
        } else {
            $updateData = [
                'ultima_msg_em' => now(),
                'chat_id' => $chat->id, // Atualizar para o chat atual (pode ter mudado de LID para número)
            ];

            // Atualizar nome se mudou
            if ($clienteName && $conversa->cliente_nome !== $clienteName) {
                $updateData['cliente_nome'] = $clienteName;
            }

            // Atualizar número se estiver usando LID e agora veio o número real
            if (strlen($phoneNumber) < 15 && strlen($conversa->cliente_numero) > 15) {
                $updateData['cliente_numero'] = $phoneNumber;
            }

            if (!$conversa->cliente_aguardando_desde) {
                $updateData['cliente_aguardando_desde'] = now();
            }

            $conversa->update($updateData);
        }
    }

    protected function handleMessagesUpdate(array $payload)
    {
        $updates = $payload['data'] ?? [];

        foreach ($updates as $update) {
            $messageId = $update['key']['id'] ?? null;
            $status = $update['update']['status'] ?? null;

            if ($messageId && $status) {
                $statusMap = [
                    1 => 'pending',
                    2 => 'sent',
                    3 => 'delivered',
                    4 => 'read',
                ];

                Message::where('message_key', $messageId)
                    ->update(['status' => $statusMap[$status] ?? 'sent']);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    protected function handleMessagesDelete(array $payload)
    {
        $messageId = $payload['data']['key']['id'] ?? null;

        if ($messageId) {
            Message::where('message_key', $messageId)
                ->update(['is_deleted' => true]);
        }

        return response()->json(['status' => 'ok']);
    }

    protected function handleSendMessage(array $payload)
    {
        // SEND_MESSAGE é disparado quando uma mensagem é enviada pelo WhatsApp Web
        // Precisamos salvar essas mensagens também
        $instanceName = $payload['instance'] ?? null;
        $data = $payload['data'] ?? [];

        if (!$instanceName) {
            return response()->json(['status' => 'error', 'message' => 'Instance name missing']);
        }

        $account = WhatsappAccount::where('session_name', $instanceName)->first();
        if (!$account) {
            return response()->json(['status' => 'error', 'message' => 'Account not found']);
        }

        // O payload de SEND_MESSAGE tem estrutura similar ao MESSAGES_UPSERT
        // mas a mensagem vem diretamente em data, não em data[]
        if (!empty($data['key']) && !empty($data['message'])) {
            $this->processMessage($account, $data);
        }

        return response()->json(['status' => 'ok']);
    }

    protected function handleReaction(array $payload)
    {
        $data = $payload['data'] ?? [];
        $reactionMessage = $data['message']['reactionMessage'] ?? $data['reactionMessage'] ?? null;

        if (!$reactionMessage) {
            return response()->json(['status' => 'ok']);
        }

        // ID da mensagem que recebeu a reação
        $targetMessageId = $reactionMessage['key']['id'] ?? null;
        $emoji = $reactionMessage['text'] ?? null;
        $fromJid = $data['key']['remoteJid'] ?? $data['key']['participant'] ?? null;
        $fromMe = $data['key']['fromMe'] ?? false;

        if (!$targetMessageId) {
            return response()->json(['status' => 'ok']);
        }

        // Buscar mensagem alvo
        $message = Message::where('message_key', $targetMessageId)
            ->orWhere('message_key', 'like', '%' . $targetMessageId)
            ->first();

        if (!$message) {
            return response()->json(['status' => 'ok']);
        }

        $reactions = $message->reactions ?? [];

        if (empty($emoji)) {
            // Remover reação (emoji vazio = remoção)
            $reactions = array_filter($reactions, fn($r) => ($r['from'] ?? '') !== $fromJid);
        } else {
            // Remover reação anterior do mesmo remetente
            $reactions = array_filter($reactions, fn($r) => ($r['from'] ?? '') !== $fromJid);

            // Adicionar nova reação
            $reactions[] = [
                'emoji' => $emoji,
                'from' => $fromMe ? 'me' : $fromJid,
                'timestamp' => time(),
            ];
        }

        $message->update(['reactions' => array_values($reactions)]);

        Log::info('Reação processada', [
            'message_id' => $targetMessageId,
            'emoji' => $emoji,
            'from' => $fromJid,
        ]);

        return response()->json(['status' => 'ok']);
    }

    protected function processReaction(array $messageData)
    {
        $key = $messageData['key'] ?? [];
        $message = $messageData['message'] ?? [];
        $reactionMessage = $message['reactionMessage'] ?? null;

        if (!$reactionMessage) {
            return;
        }

        // ID da mensagem que recebeu a reação
        $targetMessageId = $reactionMessage['key']['id'] ?? null;
        $emoji = $reactionMessage['text'] ?? null;
        $fromJid = $key['remoteJid'] ?? $key['participant'] ?? null;
        $fromMe = $key['fromMe'] ?? false;

        if (!$targetMessageId) {
            return;
        }

        // Buscar mensagem alvo
        $dbMessage = Message::where('message_key', $targetMessageId)
            ->orWhere('message_key', 'like', '%' . $targetMessageId)
            ->first();

        if (!$dbMessage) {
            Log::info('Reação ignorada - mensagem não encontrada', ['target' => $targetMessageId]);
            return;
        }

        $reactions = $dbMessage->reactions ?? [];

        if (empty($emoji)) {
            // Remover reação (emoji vazio = remoção)
            $reactions = array_filter($reactions, fn($r) => ($r['from'] ?? '') !== $fromJid);
        } else {
            // Remover reação anterior do mesmo remetente
            $reactions = array_filter($reactions, fn($r) => ($r['from'] ?? '') !== $fromJid);

            // Adicionar nova reação
            $reactions[] = [
                'emoji' => $emoji,
                'from' => $fromMe ? 'me' : $fromJid,
                'timestamp' => time(),
            ];
        }

        $dbMessage->update(['reactions' => array_values($reactions)]);

        Log::info('Reação processada via MESSAGES_UPSERT', [
            'message_id' => $targetMessageId,
            'emoji' => $emoji,
            'from' => $fromJid,
        ]);
    }

    protected function handleGroupsUpsert(array $payload)
    {
        // Evento disparado quando grupos são sincronizados ou atualizados
        $instanceName = $payload['instance'] ?? null;
        $groups = $payload['data'] ?? [];

        if (!$instanceName || empty($groups)) {
            return response()->json(['status' => 'ok']);
        }

        $account = WhatsappAccount::where('session_name', $instanceName)->first();
        if (!$account) {
            return response()->json(['status' => 'ok']);
        }

        foreach ($groups as $group) {
            $groupJid = $group['id'] ?? null;
            $groupName = $group['subject'] ?? null;

            if ($groupJid && $groupName) {
                // Atualizar chat com nome do grupo
                Chat::where('account_id', $account->id)
                    ->where('chat_id', $groupJid)
                    ->update(['chat_name' => $groupName]);

                // Atualizar conversa se existir
                $chat = Chat::where('account_id', $account->id)
                    ->where('chat_id', $groupJid)
                    ->first();

                if ($chat) {
                    Conversa::where('chat_id', $chat->id)
                        ->whereIn('status', ['aguardando', 'em_atendimento'])
                        ->update(['cliente_nome' => $groupName]);
                }

                Log::info('Grupo atualizado', ['group_jid' => $groupJid, 'name' => $groupName]);
            }
        }

        return response()->json(['status' => 'ok', 'processed' => count($groups)]);
    }

    protected function handleGroupUpdate(array $payload)
    {
        // Evento disparado quando um grupo específico é atualizado
        $instanceName = $payload['instance'] ?? null;
        $data = $payload['data'] ?? [];

        $groupJid = $data['id'] ?? $data['groupJid'] ?? null;
        $groupName = $data['subject'] ?? null;

        if (!$instanceName || !$groupJid) {
            return response()->json(['status' => 'ok']);
        }

        $account = WhatsappAccount::where('session_name', $instanceName)->first();
        if (!$account) {
            return response()->json(['status' => 'ok']);
        }

        if ($groupName) {
            Chat::where('account_id', $account->id)
                ->where('chat_id', $groupJid)
                ->update(['chat_name' => $groupName]);

            $chat = Chat::where('account_id', $account->id)
                ->where('chat_id', $groupJid)
                ->first();

            if ($chat) {
                Conversa::where('chat_id', $chat->id)
                    ->whereIn('status', ['aguardando', 'em_atendimento'])
                    ->update(['cliente_nome' => $groupName]);
            }

            Log::info('Grupo atualizado (GROUP_UPDATE)', ['group_jid' => $groupJid, 'name' => $groupName]);
        }

        return response()->json(['status' => 'ok']);
    }

    protected function handleGroupParticipantsUpdate(array $payload)
    {
        // Evento disparado quando participantes de um grupo são atualizados
        // Por enquanto, apenas logamos o evento
        Log::info('Participantes do grupo atualizados', ['payload' => $payload['data'] ?? []]);
        return response()->json(['status' => 'ok']);
    }

    protected function handlePresenceUpdate(array $payload)
    {
        return response()->json(['status' => 'ok']);
    }

    protected function groupNameNeedsUpdate(?string $name): bool
    {
        if (empty($name)) {
            return true;
        }

        // Se o nome é um ID numérico (ex: 120363418482769391 ou 554497285348-1556315939)
        if (preg_match('/^\d+(-\d+)?$/', $name)) {
            return true;
        }

        return false;
    }

    protected function extractPhoneFromJid(string $jid): string
    {
        return explode('@', $jid)[0];
    }
}
