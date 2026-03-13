<?php

namespace App\Console\Commands;

use App\Models\Chat;
use App\Models\Message;
use App\Models\WhatsappAccount;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportWhatsAppChat extends Command
{
    protected $signature = 'whatsapp:import-chat
        {file : Caminho do arquivo .txt exportado do WhatsApp}
        {instance : Nome da instância}
        {phone : Número do contato (ex: 5544999999999)}
        {--owner-name= : Nome do dono do WhatsApp (para identificar mensagens enviadas)}
        {--contact-name= : Nome do contato}
        {--dry-run : Apenas simular, não importar}';

    protected $description = 'Importa histórico de chat exportado do WhatsApp (arquivo .txt)';

    protected ?WhatsappAccount $account = null;
    protected ?Chat $chat = null;
    protected string $ownerName = '';
    protected string $contactPhone = '';

    public function handle()
    {
        $filePath = $this->argument('file');
        $instanceName = $this->argument('instance');
        $phone = preg_replace('/\D/', '', $this->argument('phone'));
        $ownerName = $this->option('owner-name');
        $contactName = $this->option('contact-name');
        $dryRun = $this->option('dry-run');

        // Validar arquivo
        if (!file_exists($filePath)) {
            $this->error("Arquivo não encontrado: {$filePath}");
            return 1;
        }

        // Buscar conta
        $this->account = WhatsappAccount::where('session_name', $instanceName)->first();
        if (!$this->account) {
            $this->error("Instância '{$instanceName}' não encontrada");
            return 1;
        }

        $this->contactPhone = $phone;
        $jid = "{$phone}@s.whatsapp.net";

        // Detectar nome do owner se não informado
        if (!$ownerName) {
            $ownerName = $this->detectOwnerName($filePath);
            if ($ownerName) {
                $this->info("Nome do owner detectado: {$ownerName}");
            } else {
                $this->error("Não foi possível detectar o nome do owner. Use --owner-name='Nome'");
                return 1;
            }
        }
        $this->ownerName = $ownerName;

        // Criar ou buscar chat
        $this->chat = Chat::firstOrCreate(
            ['account_id' => $this->account->id, 'chat_id' => $jid],
            [
                'chat_name' => $contactName ?? $phone,
                'chat_type' => 'individual',
            ]
        );

        $this->info("Importando para chat: {$this->chat->chat_name} (ID: {$this->chat->id})");

        if ($dryRun) {
            $this->warn('Modo dry-run: nenhuma alteração será feita');
        }

        // Ler e processar arquivo
        $content = file_get_contents($filePath);
        $messages = $this->parseWhatsAppExport($content);

        $this->info("Mensagens encontradas no arquivo: " . count($messages));

        $imported = 0;
        $skipped = 0;
        $errors = 0;

        $bar = $this->output->createProgressBar(count($messages));
        $bar->start();

        foreach ($messages as $msg) {
            // Gerar ID único para a mensagem (baseado em timestamp + hash do texto)
            $messageKey = $this->generateMessageKey($msg);

            // Verificar se já existe
            if (Message::where('message_key', $messageKey)->exists()) {
                $skipped++;
                $bar->advance();
                continue;
            }

            if (!$dryRun) {
                try {
                    $this->createMessage($msg, $messageKey);
                    $imported++;
                } catch (\Exception $e) {
                    $errors++;
                    $this->newLine();
                    $this->warn("Erro: " . $e->getMessage());
                }
            } else {
                $imported++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Atualizar último timestamp do chat
        if (!$dryRun) {
            $latestTimestamp = Message::where('chat_id', $this->chat->id)->max('timestamp');
            if ($latestTimestamp) {
                $this->chat->update(['last_message_timestamp' => $latestTimestamp]);
            }

            // Atualizar nome do chat se informado
            if ($contactName && $this->chat->chat_name !== $contactName) {
                $this->chat->update(['chat_name' => $contactName]);
            }
        }

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

    /**
     * Detecta o nome do owner analisando o padrão de mensagens
     */
    protected function detectOwnerName(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);

        $senders = [];

        foreach ($lines as $line) {
            // Formato: DD/MM/AAAA HH:MM - Nome: Mensagem
            // ou: DD/MM/AAAA, HH:MM - Nome: Mensagem
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}[,\s]+\d{2}:\d{2}\s*-\s*([^:]+):/', $line, $matches)) {
                $sender = trim($matches[1]);
                $senders[$sender] = ($senders[$sender] ?? 0) + 1;
            }
        }

        if (empty($senders)) {
            return null;
        }

        // O owner geralmente é quem mais envia mensagens ou tem nome específico
        arsort($senders);

        // Mostrar opções para o usuário
        $this->info("Remetentes encontrados:");
        $options = [];
        foreach ($senders as $name => $count) {
            $options[] = "{$name} ({$count} mensagens)";
            $this->line("  - {$name}: {$count} mensagens");
        }

        // Perguntar qual é o owner
        $ownerIndex = $this->choice(
            'Qual desses é o DONO do WhatsApp (você/empresa)?',
            array_keys($senders),
            0
        );

        return $ownerIndex;
    }

    /**
     * Parse do arquivo exportado do WhatsApp
     */
    protected function parseWhatsAppExport(string $content): array
    {
        // Normalizar conteúdo
        $content = $this->normalizeContent($content);

        $messages = [];
        $lines = explode("\n", $content);
        $currentMessage = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Tentar fazer parse de nova mensagem
            // Formatos possíveis:
            // 12/03/2026 15:30 - Nome: Mensagem
            // 12/03/2026, 15:30 - Nome: Mensagem
            // [12/03/2026 15:30] Nome: Mensagem
            // [12/03/2026, 15:30] Nome: Mensagem

            $parsed = $this->parseMessageLine($line);

            if ($parsed) {
                // Salvar mensagem anterior se existir
                if ($currentMessage) {
                    $messages[] = $currentMessage;
                }
                $currentMessage = $parsed;
            } elseif ($currentMessage) {
                // Linha de continuação - adicionar ao texto da mensagem atual
                $currentMessage['text'] .= "\n" . $line;
            }
        }

        // Adicionar última mensagem
        if ($currentMessage) {
            $messages[] = $currentMessage;
        }

        return $messages;
    }

    /**
     * Parse de uma linha de mensagem
     */
    protected function parseMessageLine(string $line): ?array
    {
        // Padrão 1: DD/MM/AAAA HH:MM - Nome: Mensagem
        // Padrão 2: DD/MM/AAAA, HH:MM - Nome: Mensagem
        // Padrão 3: [DD/MM/AAAA HH:MM] Nome: Mensagem
        // Padrão 4: DD/MM/AA HH:MM - Nome: Mensagem

        $patterns = [
            // Com colchetes e segundos: [13/03/2026 10:30:45] Nome: Mensagem
            '/^\[(\d{2}\/\d{2}\/\d{4})[,\s]+(\d{2}:\d{2})(?::\d{2})?\]\s*([^:]+):\s*(.*)$/u',
            // Com colchetes ano curto: [13/03/26, 10:30] Nome: Mensagem
            '/^\[(\d{2}\/\d{2}\/\d{2})[,\s]+(\d{2}:\d{2})(?::\d{2})?\]\s*([^:]+):\s*(.*)$/u',
            // Sem colchetes com vírgula: 13/03/2026, 10:30 - Nome: Mensagem
            '/^(\d{2}\/\d{2}\/\d{4})[,\s]+(\d{2}:\d{2})\s*-\s*([^:]+):\s*(.*)$/u',
            // Sem colchetes sem vírgula: 13/03/2026 10:30 - Nome: Mensagem
            '/^(\d{2}\/\d{2}\/\d{4})\s+(\d{2}:\d{2})\s*-\s*([^:]+):\s*(.*)$/u',
            // Ano curto com vírgula: 13/03/26, 10:30 - Nome: Mensagem
            '/^(\d{2}\/\d{2}\/\d{2})[,\s]+(\d{2}:\d{2})\s*-\s*([^:]+):\s*(.*)$/u',
            // Ano curto sem vírgula: 13/03/26 10:30 - Nome: Mensagem
            '/^(\d{2}\/\d{2}\/\d{2})\s+(\d{2}:\d{2})\s*-\s*([^:]+):\s*(.*)$/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $line, $matches)) {
                $date = $matches[1];
                $time = $matches[2];
                $sender = trim($matches[3]);
                $text = trim($matches[4]);

                // Converter ano de 2 dígitos para 4
                if (strlen($date) === 8) { // DD/MM/YY
                    $parts = explode('/', $date);
                    $year = (int)$parts[2];
                    $year = $year > 50 ? 1900 + $year : 2000 + $year;
                    $date = "{$parts[0]}/{$parts[1]}/{$year}";
                }

                try {
                    $datetime = Carbon::createFromFormat('d/m/Y H:i', "{$date} {$time}", 'America/Sao_Paulo');
                } catch (\Exception $e) {
                    continue;
                }

                // Determinar se é mensagem enviada ou recebida
                $isFromMe = $this->isSenderOwner($sender);

                // Determinar tipo de mensagem
                $messageType = 'text';
                if ($this->isMediaPlaceholder($text)) {
                    $messageType = $this->detectMediaType($text);
                    $text = $this->cleanMediaText($text);
                }

                return [
                    'datetime' => $datetime,
                    'timestamp' => $datetime->timestamp,
                    'sender' => $sender,
                    'text' => $text,
                    'is_from_me' => $isFromMe,
                    'message_type' => $messageType,
                ];
            }
        }

        return null;
    }

    /**
     * Verifica se o remetente é o owner
     */
    protected function isSenderOwner(string $sender): bool
    {
        return Str::lower(trim($sender)) === Str::lower(trim($this->ownerName));
    }

    /**
     * Verifica se é placeholder de mídia
     */
    protected function isMediaPlaceholder(string $text): bool
    {
        $mediaPatterns = [
            '<Mídia oculta>',
            '<Media omitted>',
            'imagem anexada',
            'image attached',
            'vídeo anexado',
            'video attached',
            'áudio anexado',
            'audio attached',
            'documento anexado',
            'document attached',
            'figurinha anexada',
            'sticker attached',
            'GIF anexado',
            'GIF attached',
            '(arquivo anexado)',
        ];

        foreach ($mediaPatterns as $pattern) {
            if (stripos($text, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detecta tipo de mídia pelo texto
     */
    protected function detectMediaType(string $text): string
    {
        $textLower = Str::lower($text);

        if (Str::contains($textLower, ['imagem', 'image', 'foto', 'photo'])) {
            return 'image';
        }
        if (Str::contains($textLower, ['vídeo', 'video'])) {
            return 'video';
        }
        if (Str::contains($textLower, ['áudio', 'audio', 'voice', 'voz'])) {
            return 'audio';
        }
        if (Str::contains($textLower, ['documento', 'document', 'pdf', 'arquivo'])) {
            return 'document';
        }
        if (Str::contains($textLower, ['figurinha', 'sticker'])) {
            return 'sticker';
        }
        if (Str::contains($textLower, ['gif'])) {
            return 'image';
        }

        return 'media'; // tipo genérico
    }

    /**
     * Limpa texto de placeholder de mídia
     */
    protected function cleanMediaText(string $text): ?string
    {
        $patterns = [
            '/<Mídia oculta>/i',
            '/<Media omitted>/i',
            '/\(arquivo anexado\)/i',
        ];

        $cleaned = preg_replace($patterns, '', $text);
        $cleaned = trim($cleaned);

        return $cleaned ?: null;
    }

    /**
     * Gera chave única para mensagem importada
     */
    protected function generateMessageKey(array $msg): string
    {
        $hash = md5($msg['timestamp'] . $msg['sender'] . $msg['text']);
        return 'IMPORT_' . strtoupper(substr($hash, 0, 24));
    }

    /**
     * Cria a mensagem no banco
     */
    protected function createMessage(array $msg, string $messageKey): void
    {
        $jid = "{$this->contactPhone}@s.whatsapp.net";
        $ownerJid = $this->account->owner_jid ?? (preg_replace('/\D/', '', $this->account->phone_number) . '@s.whatsapp.net');

        Message::create([
            'chat_id' => $this->chat->id,
            'message_key' => $messageKey,
            'from_jid' => $msg['is_from_me'] ? $ownerJid : $jid,
            'sender_name' => $msg['is_from_me'] ? null : $msg['sender'],
            'to_jid' => $msg['is_from_me'] ? $jid : $ownerJid,
            'message_text' => $msg['text'],
            'message_type' => $msg['message_type'],
            'is_from_me' => $msg['is_from_me'],
            'timestamp' => $msg['timestamp'],
            'status' => 'delivered',
            'message_raw' => [
                'imported' => true,
                'source' => 'whatsapp_export',
                'original_sender' => $msg['sender'],
                'original_datetime' => $msg['datetime']->toIso8601String(),
            ],
        ]);
    }

    /**
     * Normaliza o conteúdo do arquivo para parsing consistente
     */
    protected function normalizeContent(string $content): string
    {
        // Remover BOM (Byte Order Mark) se presente
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        // Normalizar quebras de linha (Windows -> Unix)
        $content = str_replace("\r\n", "\n", $content);
        $content = str_replace("\r", "\n", $content);

        // Normalizar espaços não-quebrável para espaço normal
        $content = preg_replace('/\x{00A0}/u', ' ', $content);

        // Normalizar colchetes full-width para ASCII
        $content = str_replace(['［', '］'], ['[', ']'], $content);

        // Normalizar barras full-width para ASCII
        $content = str_replace('／', '/', $content);

        // Normalizar dois-pontos full-width
        $content = str_replace('：', ':', $content);

        // Remover caracteres de controle invisíveis (exceto \n)
        $content = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/u', '', $content);

        return $content;
    }
}
