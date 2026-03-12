<?php

namespace App\Console\Commands;

use App\Models\Chat;
use App\Models\Message;
use App\Models\WhatsappAccount;
use App\Services\EvolutionApiService;
use Illuminate\Console\Command;

class SyncChatMessages extends Command
{
    protected $signature = 'chat:sync-messages {instance} {jid} {--limit=100}';
    protected $description = 'Sincroniza mensagens de um chat específico da Evolution API';

    public function handle(EvolutionApiService $evolution)
    {
        $instanceName = $this->argument('instance');
        $jid = $this->argument('jid');
        $limit = (int) $this->option('limit');

        // Normalizar JID
        if (!str_contains($jid, '@')) {
            $jid = $jid . '@s.whatsapp.net';
        }

        $account = WhatsappAccount::where('session_name', $instanceName)->first();
        if (!$account) {
            $this->error("Instância '{$instanceName}' não encontrada");
            return 1;
        }

        $this->info("Buscando mensagens de {$jid} na instância {$instanceName}...");

        $result = $evolution->fetchMessages($instanceName, $jid, $limit);

        if (!$result['success']) {
            $this->error("Erro ao buscar mensagens: " . ($result['error'] ?? 'desconhecido'));
            return 1;
        }

        $messages = $result['data'] ?? [];
        $this->info("Encontradas " . count($messages) . " mensagens na API");

        // Buscar ou criar chat
        $chat = Chat::firstOrCreate(
            ['account_id' => $account->id, 'chat_id' => $jid],
            [
                'chat_name' => 'Contato',
                'chat_type' => str_contains($jid, '@g.us') ? 'group' : 'individual',
            ]
        );

        $imported = 0;
        $skipped = 0;

        foreach ($messages as $msg) {
            $messageId = $msg['key']['id'] ?? null;
            if (!$messageId) {
                $skipped++;
                continue;
            }

            // Verificar se já existe
            if (Message::where('message_key', $messageId)->exists()) {
                $skipped++;
                continue;
            }

            $fromMe = $msg['key']['fromMe'] ?? false;
            $participant = $msg['key']['participant'] ?? null;
            $message = $msg['message'] ?? [];
            $timestamp = $msg['messageTimestamp'] ?? time();

            // Extrair timestamp se for objeto
            if (is_array($timestamp)) {
                $timestamp = $timestamp['low'] ?? time();
            }

            // Determinar tipo e texto
            $messageType = 'text';
            $messageText = null;
            $mediaUrl = null;
            $mediaMime = null;
            $mediaFilename = null;

            if (isset($message['conversation'])) {
                $messageText = $message['conversation'];
            } elseif (isset($message['extendedTextMessage'])) {
                $messageText = $message['extendedTextMessage']['text'] ?? '';
            } elseif (isset($message['imageMessage'])) {
                $messageType = 'image';
                $messageText = $message['imageMessage']['caption'] ?? null;
                $mediaUrl = $message['imageMessage']['url'] ?? null;
                $mediaMime = $message['imageMessage']['mimetype'] ?? null;
            } elseif (isset($message['audioMessage'])) {
                $messageType = 'audio';
                $mediaUrl = $message['audioMessage']['url'] ?? null;
                $mediaMime = $message['audioMessage']['mimetype'] ?? null;
            } elseif (isset($message['videoMessage'])) {
                $messageType = 'video';
                $messageText = $message['videoMessage']['caption'] ?? null;
                $mediaUrl = $message['videoMessage']['url'] ?? null;
                $mediaMime = $message['videoMessage']['mimetype'] ?? null;
            } elseif (isset($message['documentMessage'])) {
                $messageType = 'document';
                $mediaUrl = $message['documentMessage']['url'] ?? null;
                $mediaMime = $message['documentMessage']['mimetype'] ?? null;
                $mediaFilename = $message['documentMessage']['fileName'] ?? null;
            } elseif (isset($message['stickerMessage'])) {
                $messageType = 'sticker';
                $mediaUrl = $message['stickerMessage']['url'] ?? null;
            } elseif (isset($message['reactionMessage'])) {
                // Pular reações
                $skipped++;
                continue;
            }

            // Determinar JIDs
            $ownerJid = $account->owner_jid ?? ($account->phone_number . '@s.whatsapp.net');
            $senderJid = $fromMe ? $ownerJid : ($participant ?? $jid);
            $toJid = $fromMe ? $jid : $ownerJid;

            try {
                Message::create([
                    'chat_id' => $chat->id,
                    'message_key' => $messageId,
                    'from_jid' => $senderJid,
                    'sender_name' => $fromMe ? null : ($msg['pushName'] ?? null),
                    'participant_jid' => $participant,
                    'to_jid' => $toJid,
                    'message_text' => $messageText,
                    'message_type' => $messageType,
                    'media_url' => $mediaUrl,
                    'media_mime_type' => $mediaMime,
                    'media_filename' => $mediaFilename,
                    'is_from_me' => $fromMe,
                    'timestamp' => $timestamp,
                    'status' => 'delivered',
                    'message_raw' => $msg,
                ]);
                $imported++;
            } catch (\Exception $e) {
                $this->warn("Erro ao importar mensagem {$messageId}: " . $e->getMessage());
                $skipped++;
            }
        }

        // Atualizar nome do chat se possível
        if (!$chat->chat_name || $chat->chat_name === 'Contato') {
            $pushName = collect($messages)->pluck('pushName')->filter()->first();
            if ($pushName) {
                $chat->update(['chat_name' => $pushName]);
            }
        }

        // Atualizar último timestamp
        $latestTimestamp = Message::where('chat_id', $chat->id)->max('timestamp');
        if ($latestTimestamp) {
            $chat->update(['last_message_timestamp' => $latestTimestamp]);
        }

        $this->info("Sincronização concluída!");
        $this->info("  Importadas: {$imported}");
        $this->info("  Puladas (já existem ou reações): {$skipped}");

        return 0;
    }
}
