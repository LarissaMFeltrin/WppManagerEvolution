<?php

namespace App\Services;

use App\DTOs\SyncResult;
use App\Models\Chat;
use App\Models\ContactAlias;
use App\Models\Conversa;
use App\Models\Message;
use App\Models\WhatsappAccount;
use Illuminate\Support\Facades\Log;

class HistorySyncService
{
    public function __construct(
        protected EvolutionApiService $evolution,
        protected BaileysService $baileys,
    ) {}

    /**
     * Sincroniza histórico com fallback automático:
     * 1. Evolution API (rápido, limitado)
     * 2. Baileys (completo, requer conexão)
     * 3. Importação manual (sempre funciona)
     */
    public function syncHistory(
        Conversa $conversa,
        int $limit = 100,
        ?string $numeroReal = null
    ): SyncResult {
        $chat = $conversa->chat;
        $account = $conversa->account;

        if (!$chat || !$account) {
            return SyncResult::failed('system', 'Chat ou conta não encontrados');
        }

        // Montar lista de JIDs aceitos
        $acceptedJids = $this->buildAcceptedJids($conversa, $numeroReal);

        if (empty($acceptedJids)) {
            return SyncResult::failed('system', 'Nenhum JID disponível para sincronização');
        }

        $primaryJid = $acceptedJids[0];

        // Resultado que acumula tentativas
        $finalResult = new SyncResult();

        // Etapa 1: Tentar Evolution API
        Log::info('HistorySync: Tentando Evolution API', [
            'conversa' => $conversa->id,
            'jid' => $primaryJid,
        ]);

        $evolutionResult = $this->tryEvolutionSync($conversa, $acceptedJids, $limit);

        if ($evolutionResult->isSuccess()) {
            $finalResult->addAttempt('evolution', 'success', "{$evolutionResult->imported} importadas, {$evolutionResult->skipped} já existiam");

            if ($evolutionResult->imported >= $limit * 0.5) {
                Log::info('HistorySync: Evolution API bem-sucedida', [
                    'imported' => $evolutionResult->imported,
                ]);
                $evolutionResult->attempts = $finalResult->attempts;
                return $evolutionResult;
            }
        } else {
            $finalResult->addAttempt('evolution', 'failed', $evolutionResult->message);
        }

        // Etapa 2: Tentar Baileys (se disponível)
        if ($this->isBaileysAvailable()) {
            Log::info('HistorySync: Tentando Baileys', [
                'conversa' => $conversa->id,
            ]);

            $finalResult->addAttempt('baileys', 'trying', 'Conectando...');

            $baileysResult = $this->tryBaileysSync($chat, $primaryJid, $limit);

            if ($baileysResult->isSuccess()) {
                // Atualizar última tentativa
                $finalResult->attempts[count($finalResult->attempts) - 1] = [
                    'source' => 'baileys',
                    'status' => 'success',
                    'detail' => "{$baileysResult->imported} importadas",
                    'time' => now()->toTimeString(),
                ];

                // Somar com resultado parcial da Evolution
                $baileysResult->imported += $evolutionResult->imported;
                $baileysResult->skipped += $evolutionResult->skipped;
                $baileysResult->attempts = $finalResult->attempts;
                Log::info('HistorySync: Baileys bem-sucedido', [
                    'imported' => $baileysResult->imported,
                ]);
                return $baileysResult;
            } else {
                // Atualizar última tentativa como falha
                $finalResult->attempts[count($finalResult->attempts) - 1] = [
                    'source' => 'baileys',
                    'status' => 'failed',
                    'detail' => $baileysResult->message,
                    'time' => now()->toTimeString(),
                ];
            }
        } else {
            Log::info('HistorySync: Baileys não disponível');
            $finalResult->addAttempt('baileys', 'skipped', 'Serviço não disponível');
        }

        // Etapa 3: Se nenhum método funcionou completamente, orientar importação
        if ($evolutionResult->imported == 0) {
            Log::info('HistorySync: Orientando importação manual');
            $importResult = SyncResult::needsManualImport($chat, 0, 0);
            $importResult->attempts = $finalResult->attempts;
            $importResult->addAttempt('manual', 'required', 'Importação manual necessária');
            return $importResult;
        }

        // Retornar resultado parcial com instrução de importação se precisar mais
        $evolutionResult->message .= ' - Para histórico completo, importe manualmente.';
        $evolutionResult->attempts = $finalResult->attempts;
        return $evolutionResult;
    }

    /**
     * Tentar sincronização via Evolution API
     */
    protected function tryEvolutionSync(
        Conversa $conversa,
        array $acceptedJids,
        int $limit
    ): SyncResult {
        try {
            $messages = [];
            $primaryJid = $acceptedJids[0];

            // Buscar do JID principal
            $result = $this->evolution->fetchMessages(
                $conversa->account->session_name,
                $primaryJid,
                $limit
            );

            if ($result['success'] && !empty($result['data'])) {
                $messages = array_merge($messages, $result['data']);
            }

            // Buscar de aliases (LID)
            foreach ($acceptedJids as $jid) {
                if ($jid !== $primaryJid && strpos($jid, '@lid') !== false) {
                    $aliasResult = $this->evolution->fetchMessages(
                        $conversa->account->session_name,
                        $jid,
                        $limit
                    );
                    if ($aliasResult['success'] && !empty($aliasResult['data'])) {
                        $messages = array_merge($messages, $aliasResult['data']);
                    }
                }
            }

            if (empty($messages)) {
                return SyncResult::failed('evolution', 'Nenhuma mensagem encontrada');
            }

            // Processar mensagens
            $imported = 0;
            $skippedNoKey = 0;
            $skippedWrongJid = 0;
            $skippedDuplicate = 0;

            Log::info('HistorySync: Processando mensagens', [
                'total' => count($messages),
                'acceptedJids' => $acceptedJids,
                'chat_id' => $conversa->chat->id,
            ]);

            foreach ($messages as $index => $messageData) {
                if ($imported >= $limit) break;

                $key = $messageData['key'] ?? [];
                $messageId = $key['id'] ?? null;
                $remoteJid = $key['remoteJid'] ?? null;

                if (!$messageId) {
                    $skippedNoKey++;
                    continue;
                }

                // Validar se pertence a este chat
                if (!in_array($remoteJid, $acceptedJids)) {
                    $skippedWrongJid++;
                    if ($index < 5) { // Log apenas os primeiros 5
                        Log::debug('HistorySync: JID não aceito', [
                            'remoteJid' => $remoteJid,
                            'acceptedJids' => $acceptedJids,
                        ]);
                    }
                    continue;
                }

                // Verificar duplicata - message_key é UNIQUE globalmente
                // NÃO mover mensagens automaticamente (pode ser de outro contato com mesmo LID)
                if (Message::where('message_key', $messageId)
                    ->orWhere('message_key', 'like', '%' . $messageId)
                    ->exists()) {
                    $skippedDuplicate++;
                    continue;
                }

                // Processar e salvar
                $this->processMessage($conversa, $messageData);
                $imported++;
            }

            $totalSkipped = $skippedNoKey + $skippedWrongJid + $skippedDuplicate;

            Log::info('HistorySync: Resultado detalhado', [
                'imported' => $imported,
                'skipped_no_key' => $skippedNoKey,
                'skipped_wrong_jid' => $skippedWrongJid,
                'skipped_duplicate' => $skippedDuplicate,
            ]);

            return SyncResult::success('evolution', $imported, $totalSkipped);

        } catch (\Exception $e) {
            Log::error('HistorySync: Evolution falhou', ['error' => $e->getMessage()]);
            return SyncResult::failed('evolution', $e->getMessage());
        }
    }

    /**
     * Tentar sincronização via Baileys
     */
    protected function tryBaileysSync(Chat $chat, string $jid, int $limit): SyncResult
    {
        try {
            // Iniciar carregamento de histórico
            $loadResult = $this->baileys->loadHistory($jid);

            if (!$loadResult['success']) {
                return SyncResult::failed('baileys', $loadResult['error'] ?? 'Falha ao iniciar carregamento');
            }

            // Aguardar processamento (máx 30s)
            $maxWait = 30;
            $waited = 0;

            while ($waited < $maxWait) {
                sleep(2);
                $waited += 2;

                $status = $this->baileys->loadHistoryStatus($jid);
                $currentStatus = $status['data']['status'] ?? 'unknown';

                if ($currentStatus === 'done' || $currentStatus === 'done_target_reached') {
                    break;
                }

                if ($currentStatus === 'error') {
                    return SyncResult::failed('baileys', 'Erro durante carregamento');
                }
            }

            // Contar mensagens importadas pelo Baileys
            $countAfter = Message::where('chat_id', $chat->id)->count();

            return SyncResult::success('baileys', $countAfter, 0);

        } catch (\Exception $e) {
            Log::error('HistorySync: Baileys falhou', ['error' => $e->getMessage()]);
            return SyncResult::failed('baileys', $e->getMessage());
        }
    }

    /**
     * Verificar se Baileys está disponível
     */
    protected function isBaileysAvailable(): bool
    {
        try {
            $health = $this->baileys->health();
            return $health['success'] &&
                   in_array($health['data']['status'] ?? '', ['connected', 'open']);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Montar lista de JIDs aceitos para sincronização
     */
    protected function buildAcceptedJids(Conversa $conversa, ?string $numeroReal = null): array
    {
        $acceptedJids = [];
        $chatJid = $conversa->chat->chat_id ?? null;
        $clienteNumero = $conversa->cliente_numero;

        if ($chatJid) {
            $acceptedJids[] = $chatJid;
        }

        if ($clienteNumero) {
            $acceptedJids[] = $clienteNumero . '@s.whatsapp.net';
            $numeroLimpo = preg_replace('/\D/', '', $clienteNumero);
            if ($numeroLimpo !== $clienteNumero) {
                $acceptedJids[] = $numeroLimpo . '@s.whatsapp.net';
            }
        }

        if ($numeroReal) {
            $numeroLimpo = preg_replace('/\D/', '', $numeroReal);
            $acceptedJids[] = $numeroLimpo . '@s.whatsapp.net';
        }

        // Buscar aliases vinculados (LID <-> número)
        if ($conversa->chat) {
            $aliases = ContactAlias::where('primary_chat_id', $conversa->chat->id)
                ->pluck('alias_jid')
                ->toArray();
            $acceptedJids = array_merge($acceptedJids, $aliases);
        }

        return array_unique($acceptedJids);
    }

    /**
     * Processar e salvar mensagem individual
     */
    protected function processMessage(Conversa $conversa, array $messageData): void
    {
        $key = $messageData['key'] ?? [];
        $message = $messageData['message'] ?? [];

        $remoteJid = $key['remoteJid'] ?? null;
        $messageId = $key['id'] ?? null;
        $fromMe = $key['fromMe'] ?? false;
        $participant = $key['participant'] ?? null;

        $isGroup = str_contains($remoteJid ?? '', '@g.us');
        $senderName = $messageData['pushName'] ?? null;

        // Determinar tipo e conteúdo
        $messageType = 'text';
        $messageText = null;
        $mediaMimeType = null;
        $mediaFilename = null;

        if (isset($message['conversation'])) {
            $messageText = $message['conversation'];
        } elseif (isset($message['extendedTextMessage'])) {
            $messageText = $message['extendedTextMessage']['text'] ?? null;
        } elseif (isset($message['imageMessage'])) {
            $messageType = 'image';
            $messageText = $message['imageMessage']['caption'] ?? null;
            $mediaMimeType = $message['imageMessage']['mimetype'] ?? 'image/jpeg';
        } elseif (isset($message['videoMessage'])) {
            $messageType = 'video';
            $messageText = $message['videoMessage']['caption'] ?? null;
            $mediaMimeType = $message['videoMessage']['mimetype'] ?? 'video/mp4';
        } elseif (isset($message['audioMessage'])) {
            $messageType = 'audio';
            $mediaMimeType = $message['audioMessage']['mimetype'] ?? 'audio/ogg';
        } elseif (isset($message['documentMessage'])) {
            $messageType = 'document';
            $mediaFilename = $message['documentMessage']['fileName'] ?? 'documento';
            $messageText = $mediaFilename;
            $mediaMimeType = $message['documentMessage']['mimetype'] ?? 'application/octet-stream';
        } elseif (isset($message['stickerMessage'])) {
            $messageType = 'sticker';
        } elseif (isset($message['locationMessage'])) {
            $messageType = 'location';
        } elseif (isset($message['contactMessage'])) {
            $messageType = 'contact';
            $messageText = $message['contactMessage']['displayName'] ?? 'Contato';
        }

        $senderJid = $fromMe
            ? $conversa->account->owner_jid
            : ($isGroup ? $participant : $remoteJid);

        Message::create([
            'chat_id' => $conversa->chat->id,
            'message_key' => $messageId,
            'from_jid' => $senderJid ?? $remoteJid,
            'sender_name' => $fromMe ? null : $senderName,
            'participant_jid' => $isGroup ? $participant : null,
            'to_jid' => $fromMe ? $remoteJid : $conversa->account->owner_jid,
            'message_text' => $messageText,
            'message_type' => $messageType,
            'media_mime_type' => $mediaMimeType,
            'media_filename' => $mediaFilename,
            'is_from_me' => $fromMe,
            'timestamp' => $messageData['messageTimestamp'] ?? time(),
            'status' => 'delivered',
            'message_raw' => $messageData,
        ]);
    }
}
