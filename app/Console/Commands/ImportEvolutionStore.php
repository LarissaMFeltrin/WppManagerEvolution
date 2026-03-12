<?php

namespace App\Console\Commands;

use App\Models\Chat;
use App\Models\Message;
use App\Models\WhatsappAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class ImportEvolutionStore extends Command
{
    protected $signature = 'evolution:import-store
        {instance : Nome da instância}
        {--jid= : JID específico para importar (ex: 554499323937@s.whatsapp.net)}
        {--limit=0 : Limite de mensagens (0 = todas)}
        {--dry-run : Apenas simular, não importar}';

    protected $description = 'Importa mensagens do store interno da Evolution API para o banco de dados';

    protected string $containerName = 'evolution-api';
    protected string $storePath = '/evolution/store/messages';

    public function handle()
    {
        $instanceName = $this->argument('instance');
        $targetJid = $this->option('jid');
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        $account = WhatsappAccount::where('session_name', $instanceName)->first();
        if (!$account) {
            $this->error("Instância '{$instanceName}' não encontrada");
            return 1;
        }

        $this->info("Importando mensagens do store Evolution para '{$instanceName}'...");

        if ($dryRun) {
            $this->warn('Modo dry-run: nenhuma alteração será feita');
        }

        // Listar arquivos de mensagens
        $path = "{$this->storePath}/{$instanceName}";
        $result = Process::run("docker exec {$this->containerName} ls {$path}");

        if (!$result->successful()) {
            $this->error("Erro ao acessar store: " . $result->errorOutput());
            return 1;
        }

        $files = array_filter(explode("\n", trim($result->output())));
        $totalFiles = count($files);
        $this->info("Total de arquivos no store: {$totalFiles}");

        $imported = 0;
        $skipped = 0;
        $errors = 0;

        $bar = $this->output->createProgressBar($limit > 0 ? min($limit, $totalFiles) : $totalFiles);
        $bar->start();

        foreach ($files as $file) {
            if ($limit > 0 && ($imported + $skipped) >= $limit) {
                break;
            }

            $filePath = "{$path}/{$file}";
            $result = Process::run("docker exec {$this->containerName} cat {$filePath}");

            if (!$result->successful()) {
                $errors++;
                continue;
            }

            $messageData = json_decode($result->output(), true);
            if (!$messageData) {
                $errors++;
                continue;
            }

            $key = $messageData['key'] ?? [];
            $remoteJid = $key['remoteJid'] ?? null;
            $messageId = $key['id'] ?? null;

            // Filtrar por JID se especificado
            if ($targetJid && $remoteJid !== $targetJid) {
                continue;
            }

            // Verificar se já existe
            if ($messageId && Message::where('message_key', $messageId)->exists()) {
                $skipped++;
                $bar->advance();
                continue;
            }

            if (!$dryRun) {
                $this->importMessage($account, $messageData);
            }

            $imported++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Importação concluída!");
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Importadas', $imported],
                ['Já existentes', $skipped],
                ['Erros', $errors],
            ]
        );

        return 0;
    }

    protected function importMessage(WhatsappAccount $account, array $messageData): void
    {
        $key = $messageData['key'] ?? [];
        $message = $messageData['message'] ?? [];

        $remoteJid = $key['remoteJid'] ?? null;
        $messageId = $key['id'] ?? null;
        $fromMe = $key['fromMe'] ?? false;
        $participant = $key['participant'] ?? null;

        if (!$remoteJid || !$messageId) {
            return;
        }

        // Verificar se é grupo
        $isGroup = str_contains($remoteJid, '@g.us');

        // Buscar ou criar chat
        $chat = Chat::firstOrCreate(
            ['account_id' => $account->id, 'chat_id' => $remoteJid],
            [
                'chat_name' => $messageData['pushName'] ?? $this->extractPhoneFromJid($remoteJid),
                'chat_type' => $isGroup ? 'group' : 'individual',
            ]
        );

        // Determinar tipo e conteúdo
        $messageType = 'text';
        $messageText = null;
        $mediaUrl = null;
        $mediaMimeType = null;
        $mediaFilename = null;
        $mediaDuration = null;

        if (isset($message['conversation'])) {
            $messageText = $message['conversation'];
        } elseif (isset($message['extendedTextMessage'])) {
            $messageText = $message['extendedTextMessage']['text'] ?? null;
        } elseif (isset($message['imageMessage'])) {
            $messageType = 'image';
            $messageText = $message['imageMessage']['caption'] ?? null;
            $mediaMimeType = $message['imageMessage']['mimetype'] ?? 'image/jpeg';
        } elseif (isset($message['audioMessage'])) {
            $messageType = 'audio';
            $mediaMimeType = $message['audioMessage']['mimetype'] ?? 'audio/ogg';
            $mediaDuration = $message['audioMessage']['seconds'] ?? null;
        } elseif (isset($message['videoMessage'])) {
            $messageType = 'video';
            $messageText = $message['videoMessage']['caption'] ?? null;
            $mediaMimeType = $message['videoMessage']['mimetype'] ?? 'video/mp4';
            $mediaDuration = $message['videoMessage']['seconds'] ?? null;
        } elseif (isset($message['documentMessage'])) {
            $messageType = 'document';
            $mediaFilename = $message['documentMessage']['fileName'] ?? 'documento';
            $messageText = $mediaFilename;
            $mediaMimeType = $message['documentMessage']['mimetype'] ?? 'application/octet-stream';
        } elseif (isset($message['stickerMessage'])) {
            $messageType = 'sticker';
            $mediaMimeType = $message['stickerMessage']['mimetype'] ?? 'image/webp';
        } elseif (isset($message['reactionMessage'])) {
            // Pular reações
            return;
        }

        // Determinar JIDs
        $ownerJid = $account->owner_jid ?? (preg_replace('/\D/', '', $account->phone_number) . '@s.whatsapp.net');
        $senderJid = $fromMe ? $ownerJid : ($isGroup ? $participant : $remoteJid);
        $toJid = $fromMe ? $remoteJid : $ownerJid;

        // Extrair timestamp
        $timestamp = $messageData['messageTimestamp'] ?? time();
        if (is_array($timestamp)) {
            $timestamp = $timestamp['low'] ?? time();
        }

        Message::create([
            'chat_id' => $chat->id,
            'message_key' => $messageId,
            'from_jid' => $senderJid ?? $remoteJid,
            'sender_name' => $fromMe ? null : ($messageData['pushName'] ?? null),
            'participant_jid' => $isGroup ? $participant : null,
            'to_jid' => $toJid,
            'message_text' => $messageText,
            'message_type' => $messageType,
            'media_url' => $mediaUrl,
            'media_mime_type' => $mediaMimeType,
            'media_filename' => $mediaFilename,
            'media_duration' => $mediaDuration,
            'is_from_me' => $fromMe,
            'timestamp' => $timestamp,
            'status' => $messageData['status'] ?? 'delivered',
            'message_raw' => $messageData,
        ]);

        // Atualizar último timestamp do chat
        $latestTimestamp = Message::where('chat_id', $chat->id)->max('timestamp');
        if ($latestTimestamp) {
            $chat->update(['last_message_timestamp' => $latestTimestamp]);
        }
    }

    protected function extractPhoneFromJid(string $jid): string
    {
        return explode('@', $jid)[0];
    }
}
