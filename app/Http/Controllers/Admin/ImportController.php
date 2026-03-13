<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Contact;
use App\Models\Message;
use App\Models\WhatsappAccount;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class ImportController extends Controller
{
    protected ?string $extractedPath = null;
    protected array $mediaFiles = [];

    /**
     * Página de importação de histórico
     */
    public function index()
    {
        $user = Auth::user();
        $accounts = WhatsappAccount::where('empresa_id', $user->empresa_id)->get();
        $accountIds = $accounts->pluck('id');
        $contacts = Contact::whereIn('account_id', $accountIds)
            ->orderBy('name')
            ->get();

        return view('admin.import.index', compact('accounts', 'contacts'));
    }

    /**
     * Processar upload e importação
     */
    public function store(Request $request)
    {
        // Validação base
        $rules = [
            'account_id' => 'required|exists:whatsapp_accounts,id',
            'phone' => 'required|string',
            'owner_name' => 'required|string',
            'contact_name' => 'nullable|string',
            'skip_media' => 'nullable|boolean',
            'file_path' => 'nullable|string',
        ];

        // Se não tem file_path, exige file
        if (!$request->filled('file_path')) {
            $rules['file'] = 'required|file|max:512000';
        }

        $request->validate($rules, [
            'file.required' => 'Selecione um arquivo ou informe o caminho',
            'file.max' => 'O arquivo deve ter no máximo 500MB',
            'account_id.required' => 'Selecione uma instância WhatsApp',
            'phone.required' => 'Informe o número do contato',
            'owner_name.required' => 'Informe seu nome como aparece no WhatsApp',
        ]);

        $user = Auth::user();
        $account = WhatsappAccount::where('id', $request->account_id)
            ->where('empresa_id', $user->empresa_id)
            ->first();

        if (!$account) {
            return back()->withInput()->with('error', 'Instância não encontrada');
        }

        $phone = preg_replace('/\D/', '', $request->phone);
        $ownerName = $request->owner_name;
        $contactName = $request->contact_name;
        $skipMedia = false; // Sempre importar mídias quando disponíveis
        $jid = "{$phone}@s.whatsapp.net";

        // Determinar origem do arquivo (upload ou caminho local)
        if ($request->filled('file_path')) {
            $filePath = $request->file_path;

            if (!file_exists($filePath)) {
                return back()->withInput()->withErrors(['file_path' => 'Arquivo não encontrado: ' . $filePath]);
            }

            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

            if (!in_array($extension, ['txt', 'zip'])) {
                return back()->withInput()->withErrors(['file_path' => 'O arquivo deve ser .txt ou .zip']);
            }

            // Processar arquivo do caminho local
            if ($extension === 'zip') {
                $result = $this->processZipFilePath($filePath);
                if (!$result['success']) {
                    return back()->withInput()->withErrors(['file_path' => $result['error']]);
                }
                $content = $result['content'];
                $this->mediaFiles = $result['media_files'];
                $this->extractedPath = $result['extracted_path'];
            } else {
                $content = file_get_contents($filePath);
                $this->mediaFiles = [];
            }
        } else {
            $file = $request->file('file');
            $extension = strtolower($file->getClientOriginalExtension());

            if (!in_array($extension, ['txt', 'zip'])) {
                return back()->withInput()->withErrors(['file' => 'O arquivo deve ser .txt ou .zip']);
            }

            // Processar arquivo (TXT ou ZIP)
            if ($extension === 'zip') {
                $result = $this->processZipFile($file);
                if (!$result['success']) {
                    return back()->withInput()->withErrors(['file' => $result['error']]);
                }
                $content = $result['content'];
                $this->mediaFiles = $result['media_files'];
                $this->extractedPath = $result['extracted_path'];
            } else {
                $content = file_get_contents($file->getRealPath());
                $this->mediaFiles = [];
            }
        }

        // Criar ou buscar chat
        $chat = Chat::firstOrCreate(
            ['account_id' => $account->id, 'chat_id' => $jid],
            [
                'chat_name' => $contactName ?? $phone,
                'chat_type' => 'individual',
            ]
        );

        // Parse e importação
        $messages = $this->parseWhatsAppExport($content);
        $ownerJid = $account->owner_jid ?? (preg_replace('/\D/', '', $account->phone_number) . '@s.whatsapp.net');

        $imported = 0;
        $skipped = 0;
        $mediaSkipped = 0;
        $mediaImported = 0;

        foreach ($messages as $msg) {
            // Verificar se é mídia
            $isMedia = $this->isMediaMessage($msg['text']);

            // Pular mídias se configurado
            if ($skipMedia && $isMedia) {
                $mediaSkipped++;
                continue;
            }

            $messageKey = $this->generateMessageKey($msg);

            if (Message::where('message_key', $messageKey)->exists()) {
                $skipped++;
                continue;
            }

            $isFromMe = Str::lower(trim($msg['sender'])) === Str::lower(trim($ownerName));

            // Processar mídia se houver
            $mediaPath = null;
            $mediaUrl = null;
            $messageType = $msg['message_type'];
            $messageText = $msg['text'];

            if ($isMedia && !$skipMedia && !empty($this->mediaFiles)) {
                $mediaResult = $this->processMediaForMessage($msg['text'], $account->id, $chat->id);
                if ($mediaResult) {
                    $mediaPath = $mediaResult['path'];
                    $mediaUrl = $mediaResult['url'];
                    $messageType = $mediaResult['type'];
                    $messageText = $mediaResult['caption'] ?? null;
                    $mediaImported++;
                }
            }

            try {
                Message::create([
                    'chat_id' => $chat->id,
                    'message_key' => $messageKey,
                    'from_jid' => $isFromMe ? $ownerJid : $jid,
                    'sender_name' => $isFromMe ? null : $msg['sender'],
                    'to_jid' => $isFromMe ? $jid : $ownerJid,
                    'message_text' => $messageText,
                    'message_type' => $messageType,
                    'media_url' => $mediaUrl,
                    'media_path' => $mediaPath,
                    'is_from_me' => $isFromMe,
                    'timestamp' => $msg['timestamp'],
                    'status' => 'delivered',
                    'message_raw' => [
                        'imported' => true,
                        'source' => 'whatsapp_export',
                        'original_sender' => $msg['sender'],
                        'original_text' => $msg['text'],
                    ],
                ]);
                $imported++;
            } catch (\Exception $e) {
                Log::warning('Erro ao importar mensagem', ['error' => $e->getMessage()]);
            }
        }

        // Limpar pasta temporária
        $this->cleanupExtractedFiles();

        // Atualizar chat
        $latestTimestamp = Message::where('chat_id', $chat->id)->max('timestamp');
        if ($latestTimestamp) {
            $chat->update(['last_message_timestamp' => $latestTimestamp]);
        }
        if ($contactName) {
            $chat->update(['chat_name' => $contactName]);
        }

        $msg = "Importação concluída! {$imported} mensagens importadas, {$skipped} já existiam.";
        if ($mediaImported > 0) {
            $msg .= " {$mediaImported} mídias importadas.";
        }
        if ($mediaSkipped > 0) {
            $msg .= " {$mediaSkipped} mídias ignoradas.";
        }
        return back()->with('success', $msg);
    }

    /**
     * Processar arquivo ZIP
     */
    protected function processZipFile($file): array
    {
        $zip = new ZipArchive();
        $zipPath = $file->getRealPath();

        if ($zip->open($zipPath) !== true) {
            return ['success' => false, 'error' => 'Não foi possível abrir o arquivo ZIP'];
        }

        // Criar pasta temporária para extração
        $extractPath = storage_path('app/temp/import_' . uniqid());
        if (!mkdir($extractPath, 0755, true)) {
            return ['success' => false, 'error' => 'Não foi possível criar pasta temporária'];
        }

        $zip->extractTo($extractPath);
        $zip->close();

        // Encontrar arquivo _chat.txt
        $chatContent = null;
        $mediaFiles = [];

        $this->scanExtractedFiles($extractPath, $chatContent, $mediaFiles);

        if (!$chatContent) {
            $this->deleteDirectory($extractPath);
            return ['success' => false, 'error' => 'Arquivo _chat.txt não encontrado no ZIP'];
        }

        return [
            'success' => true,
            'content' => $chatContent,
            'media_files' => $mediaFiles,
            'extracted_path' => $extractPath,
        ];
    }

    /**
     * Processar arquivo ZIP do caminho local
     */
    protected function processZipFilePath(string $filePath): array
    {
        $zip = new ZipArchive();

        if ($zip->open($filePath) !== true) {
            return ['success' => false, 'error' => 'Não foi possível abrir o arquivo ZIP'];
        }

        // Criar pasta temporária para extração
        $extractPath = storage_path('app/temp/import_' . uniqid());
        if (!mkdir($extractPath, 0755, true)) {
            return ['success' => false, 'error' => 'Não foi possível criar pasta temporária'];
        }

        $zip->extractTo($extractPath);
        $zip->close();

        // Encontrar arquivo _chat.txt
        $chatContent = null;
        $mediaFiles = [];

        $this->scanExtractedFiles($extractPath, $chatContent, $mediaFiles);

        if (!$chatContent) {
            $this->deleteDirectory($extractPath);
            return ['success' => false, 'error' => 'Arquivo _chat.txt não encontrado no ZIP'];
        }

        return [
            'success' => true,
            'content' => $chatContent,
            'media_files' => $mediaFiles,
            'extracted_path' => $extractPath,
        ];
    }

    /**
     * Escanear arquivos extraídos
     */
    protected function scanExtractedFiles(string $path, ?string &$chatContent, array &$mediaFiles): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $filename = $file->getFilename();
                $filepath = $file->getPathname();

                if ($filename === '_chat.txt') {
                    $chatContent = file_get_contents($filepath);
                } else {
                    // Mapear arquivos de mídia pelo nome
                    $mediaFiles[strtolower($filename)] = $filepath;
                }
            }
        }
    }

    /**
     * Processar mídia para uma mensagem
     */
    protected function processMediaForMessage(string $text, int $accountId, int $chatId): ?array
    {
        // Extrair nome do arquivo da mensagem
        // Formatos: "IMG-20240423-WA0001.jpg (arquivo anexado)" ou apenas "IMG-20240423-WA0001.jpg"
        $filename = $this->extractMediaFilename($text);

        if (!$filename) {
            return null;
        }

        $filenameLower = strtolower($filename);

        if (!isset($this->mediaFiles[$filenameLower])) {
            return null;
        }

        $sourcePath = $this->mediaFiles[$filenameLower];

        if (!file_exists($sourcePath)) {
            return null;
        }

        // Determinar tipo de mídia
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mediaType = $this->getMediaTypeFromExtension($extension);

        // Criar caminho de destino
        $destFolder = "whatsapp/import/{$accountId}/{$chatId}";
        $destFilename = uniqid() . '_' . $filename;
        $destPath = "{$destFolder}/{$destFilename}";

        // Copiar arquivo para storage
        Storage::disk('public')->makeDirectory($destFolder);
        $fullDestPath = Storage::disk('public')->path($destPath);
        copy($sourcePath, $fullDestPath);

        // Extrair caption (texto após o nome do arquivo)
        $caption = $this->extractMediaCaption($text, $filename);

        return [
            'path' => $destPath,
            'url' => '/storage/' . $destPath,
            'type' => $mediaType,
            'caption' => $caption,
        ];
    }

    /**
     * Extrair nome do arquivo de mídia do texto
     */
    protected function extractMediaFilename(string $text): ?string
    {
        // Padrões de nome de arquivo WhatsApp
        $patterns = [
            // Formato iOS: 00000025-AUDIO-2024-04-23-16-24-29.opus
            '/\b(\d{8}-(?:AUDIO|PHOTO|VIDEO|STICKER)-\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}\.\w+)/i',
            // IMG-20240423-WA0001.jpg
            '/\b(IMG-\d{8}-WA\d+\.\w+)/i',
            // VID-20240423-WA0001.mp4
            '/\b(VID-\d{8}-WA\d+\.\w+)/i',
            // AUD-20240423-WA0001.opus ou .mp3
            '/\b(AUD-\d{8}-WA\d+\.\w+)/i',
            // PTT-20240423-WA0001.opus (push to talk)
            '/\b(PTT-\d{8}-WA\d+\.\w+)/i',
            // DOC-20240423-WA0001.pdf
            '/\b(DOC-\d{8}-WA\d+\.\w+)/i',
            // STK-20240423-WA0001.webp (sticker)
            '/\b(STK-\d{8}-WA\d+\.\w+)/i',
            // Formato genérico com WA
            '/\b(\w+[-_]\d+[-_]WA\d+\.\w+)/i',
            // Formato genérico com data
            '/\b(\d+-(?:AUDIO|PHOTO|VIDEO|STICKER|DOC)-[\d-]+\.\w+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Extrair caption da mensagem de mídia
     */
    protected function extractMediaCaption(string $text, string $filename): ?string
    {
        // Remover o nome do arquivo e textos padrão
        $caption = $text;
        $caption = str_ireplace($filename, '', $caption);
        $caption = preg_replace('/\(arquivo anexado\)/i', '', $caption);
        $caption = preg_replace('/\(file attached\)/i', '', $caption);
        $caption = trim($caption);

        return $caption ?: null;
    }

    /**
     * Obter tipo de mídia pela extensão
     */
    protected function getMediaTypeFromExtension(string $extension): string
    {
        $types = [
            'jpg' => 'image',
            'jpeg' => 'image',
            'png' => 'image',
            'gif' => 'image',
            'webp' => 'sticker',
            'mp4' => 'video',
            'mov' => 'video',
            'avi' => 'video',
            'mkv' => 'video',
            'mp3' => 'audio',
            'opus' => 'audio',
            'ogg' => 'audio',
            'm4a' => 'audio',
            'wav' => 'audio',
            'pdf' => 'document',
            'doc' => 'document',
            'docx' => 'document',
            'xls' => 'document',
            'xlsx' => 'document',
            'ppt' => 'document',
            'pptx' => 'document',
            'txt' => 'document',
        ];

        return $types[$extension] ?? 'document';
    }

    /**
     * Verificar se mensagem é de mídia
     */
    protected function isMediaMessage(string $text): bool
    {
        // Verificar se é placeholder de mídia oculta
        if ($this->isMediaPlaceholder($text)) {
            return true;
        }

        // Verificar se tem nome de arquivo de mídia
        if ($this->extractMediaFilename($text)) {
            return true;
        }

        return false;
    }

    /**
     * Limpar arquivos extraídos
     */
    protected function cleanupExtractedFiles(): void
    {
        if ($this->extractedPath && is_dir($this->extractedPath)) {
            $this->deleteDirectory($this->extractedPath);
        }
    }

    /**
     * Deletar diretório recursivamente
     */
    protected function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "{$dir}/{$file}";
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Analisar arquivo antes de importar (AJAX)
     */
    public function analyze(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:512000',
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        if (!in_array($extension, ['txt', 'zip'])) {
            return response()->json(['success' => false, 'error' => 'Arquivo deve ser .txt ou .zip']);
        }

        // Processar arquivo
        if ($extension === 'zip') {
            $result = $this->processZipFile($file);
            if (!$result['success']) {
                return response()->json(['success' => false, 'error' => $result['error']]);
            }
            $content = $result['content'];
            $mediaCount = count($result['media_files']);
            $this->extractedPath = $result['extracted_path'];
            $this->cleanupExtractedFiles();
        } else {
            $content = file_get_contents($file->getRealPath());
            $mediaCount = 0;
        }

        $messages = $this->parseWhatsAppExport($content);

        // Extrair remetentes únicos
        $senders = [];
        foreach ($messages as $msg) {
            $sender = $msg['sender'];
            $senders[$sender] = ($senders[$sender] ?? 0) + 1;
        }

        // Encontrar período
        $timestamps = array_column($messages, 'timestamp');
        $minDate = !empty($timestamps) ? Carbon::createFromTimestamp(min($timestamps))->format('d/m/Y') : null;
        $maxDate = !empty($timestamps) ? Carbon::createFromTimestamp(max($timestamps))->format('d/m/Y') : null;

        return response()->json([
            'success' => true,
            'total_messages' => count($messages),
            'senders' => $senders,
            'media_files' => $mediaCount,
            'period' => [
                'start' => $minDate,
                'end' => $maxDate,
            ],
        ]);
    }

    /**
     * Parse do arquivo exportado
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

            $parsed = $this->parseMessageLine($line);

            if ($parsed) {
                if ($currentMessage) {
                    $messages[] = $currentMessage;
                }
                $currentMessage = $parsed;
            } elseif ($currentMessage) {
                $currentMessage['text'] .= "\n" . $line;
            }
        }

        if ($currentMessage) {
            $messages[] = $currentMessage;
        }

        return $messages;
    }

    protected function parseMessageLine(string $line): ?array
    {
        $patterns = [
            // Com colchetes, vírgula e segundos: [09/03/2026, 18:01:55] Nome: Mensagem
            '/^\[(\d{2}\/\d{2}\/\d{4}),[ ]+(\d{2}:\d{2}:\d{2})\][ ]+([^:]+):[ ]*(.*)$/u',
            // Com colchetes, vírgula sem segundos: [09/03/2026, 18:01] Nome: Mensagem
            '/^\[(\d{2}\/\d{2}\/\d{4}),[ ]+(\d{2}:\d{2})\][ ]+([^:]+):[ ]*(.*)$/u',
            // Com colchetes, espaço e segundos: [09/03/2026 18:01:55] Nome: Mensagem
            '/^\[(\d{2}\/\d{2}\/\d{4})[ ]+(\d{2}:\d{2}:\d{2})\][ ]+([^:]+):[ ]*(.*)$/u',
            // Com colchetes, espaço sem segundos: [09/03/2026 18:01] Nome: Mensagem
            '/^\[(\d{2}\/\d{2}\/\d{4})[ ]+(\d{2}:\d{2})\][ ]+([^:]+):[ ]*(.*)$/u',
            // Com colchetes ano curto: [13/03/26, 10:30] Nome: Mensagem
            '/^\[(\d{2}\/\d{2}\/\d{2}),?[ ]+(\d{2}:\d{2})(?::\d{2})?\][ ]+([^:]+):[ ]*(.*)$/u',
            // Sem colchetes com vírgula: 13/03/2026, 10:30 - Nome: Mensagem
            '/^(\d{2}\/\d{2}\/\d{4}),[ ]+(\d{2}:\d{2})[ ]*-[ ]*([^:]+):[ ]*(.*)$/u',
            // Sem colchetes sem vírgula: 13/03/2026 10:30 - Nome: Mensagem
            '/^(\d{2}\/\d{2}\/\d{4})[ ]+(\d{2}:\d{2})[ ]*-[ ]*([^:]+):[ ]*(.*)$/u',
            // Ano curto: 13/03/26, 10:30 - Nome: Mensagem
            '/^(\d{2}\/\d{2}\/\d{2}),?[ ]+(\d{2}:\d{2})[ ]*-[ ]*([^:]+):[ ]*(.*)$/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $line, $matches)) {
                $date = $matches[1];
                $time = $matches[2];
                $sender = trim($matches[3]);
                $text = trim($matches[4]);

                // Converter ano de 2 dígitos para 4 dígitos
                $parts = explode('/', $date);
                if (count($parts) === 3 && strlen($parts[2]) === 2) {
                    $year = (int)$parts[2];
                    $year = $year > 50 ? 1900 + $year : 2000 + $year;
                    $date = "{$parts[0]}/{$parts[1]}/{$year}";
                }

                try {
                    if (strlen($time) === 8) {
                        $datetime = Carbon::createFromFormat('d/m/Y H:i:s', "{$date} {$time}", 'America/Sao_Paulo');
                    } else {
                        $datetime = Carbon::createFromFormat('d/m/Y H:i', "{$date} {$time}", 'America/Sao_Paulo');
                    }
                } catch (\Exception) {
                    continue;
                }

                $messageType = 'text';
                if ($this->isMediaPlaceholder($text)) {
                    $messageType = $this->detectMediaType($text);
                } elseif ($this->extractMediaFilename($text)) {
                    $messageType = $this->detectMediaType($text);
                }

                return [
                    'timestamp' => $datetime->timestamp,
                    'sender' => $sender,
                    'text' => $text,
                    'message_type' => $messageType,
                ];
            }
        }

        return null;
    }

    protected function isMediaPlaceholder(?string $text): bool
    {
        if (empty($text)) {
            return false;
        }

        $patterns = [
            '<mídia oculta>',
            'mídia oculta',
            'áudio ocultado',
            'vídeo ocultado',
            'imagem ocultada',
            'figurinha omitida',
            'sticker omitido',
            'gif omitido',
            '<media omitted>',
            'media omitted',
            'audio omitted',
            'video omitted',
            'image omitted',
            'sticker omitted',
            'gif omitted',
        ];

        $textLower = Str::lower($text);
        foreach ($patterns as $pattern) {
            if (stripos($textLower, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }

    protected function detectMediaType(?string $text): string
    {
        if (empty($text)) {
            return 'media';
        }

        $textLower = Str::lower($text);

        // Por extensão de arquivo
        if (preg_match('/\.(jpg|jpeg|png|gif)$/i', $text)) return 'image';
        if (preg_match('/\.(mp4|mov|avi|mkv)$/i', $text)) return 'video';
        if (preg_match('/\.(mp3|opus|ogg|m4a|wav)$/i', $text)) return 'audio';
        if (preg_match('/\.webp$/i', $text)) return 'sticker';
        if (preg_match('/\.(pdf|doc|docx|xls|xlsx)$/i', $text)) return 'document';

        // Por prefixo WhatsApp
        if (Str::startsWith($textLower, 'img-')) return 'image';
        if (Str::startsWith($textLower, 'vid-')) return 'video';
        if (Str::startsWith($textLower, 'aud-') || Str::startsWith($textLower, 'ptt-')) return 'audio';
        if (Str::startsWith($textLower, 'stk-')) return 'sticker';
        if (Str::startsWith($textLower, 'doc-')) return 'document';

        // Por texto
        if (Str::contains($textLower, ['imagem', 'image', 'foto'])) return 'image';
        if (Str::contains($textLower, ['vídeo', 'video'])) return 'video';
        if (Str::contains($textLower, ['áudio', 'audio'])) return 'audio';
        if (Str::contains($textLower, ['figurinha', 'sticker'])) return 'sticker';

        return 'media';
    }

    protected function generateMessageKey(array $msg): string
    {
        $hash = md5($msg['timestamp'] . $msg['sender'] . $msg['text']);
        return 'IMPORT_' . strtoupper(substr($hash, 0, 24));
    }

    protected function normalizeContent(string $content): string
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $content = str_replace("\r\n", "\n", $content);
        $content = str_replace("\r", "\n", $content);
        $content = preg_replace('/\x{00A0}/u', ' ', $content);
        $content = str_replace(['［', '］'], ['[', ']'], $content);
        $content = str_replace('／', '/', $content);
        $content = str_replace('：', ':', $content);
        $content = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/u', '', $content);

        return $content;
    }
}
