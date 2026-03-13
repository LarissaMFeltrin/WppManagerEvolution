<?php

namespace App\DTOs;

use App\Models\Chat;

class SyncResult
{
    public string $status = 'pending';
    public string $source = '';
    public int $imported = 0;
    public int $skipped = 0;
    public ?string $message = null;
    public ?array $importInstructions = null;
    public ?Chat $chat = null;
    public array $attempts = []; // Rastreia cada tentativa

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function needsImport(): bool
    {
        return $this->status === 'needs_import';
    }

    public function addAttempt(string $source, string $status, ?string $detail = null): self
    {
        $this->attempts[] = [
            'source' => $source,
            'status' => $status,
            'detail' => $detail,
            'time' => now()->toTimeString(),
        ];
        return $this;
    }

    public static function success(string $source, int $imported, int $skipped): self
    {
        $result = new self();
        $result->status = 'success';
        $result->source = $source;
        $result->imported = $imported;
        $result->skipped = $skipped;
        $result->message = "Sincronizado via {$source}: {$imported} mensagens importadas";
        return $result;
    }

    public static function failed(string $source, string $error): self
    {
        $result = new self();
        $result->status = 'failed';
        $result->source = $source;
        $result->message = $error;
        return $result;
    }

    public static function needsManualImport(Chat $chat, int $partialImported = 0, int $partialSkipped = 0): self
    {
        $result = new self();
        $result->status = 'needs_import';
        $result->source = 'manual';
        $result->chat = $chat;
        $result->imported = $partialImported;
        $result->skipped = $partialSkipped;
        $result->message = 'Não foi possível sincronizar o histórico completo automaticamente.';
        $result->importInstructions = [
            'title' => 'Importação Manual Necessária',
            'steps' => [
                '1. Abra a conversa no WhatsApp do celular',
                '2. Toque no menu (3 pontos) > Mais > Exportar conversa',
                '3. Escolha "Incluir mídia" para importar fotos e áudios',
                '4. Salve o arquivo .zip',
                '5. Acesse a página de importação e faça upload',
            ],
            'import_url' => route('admin.import.index'),
            'phone' => preg_replace('/@.*/', '', $chat->chat_id),
        ];
        return $result;
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'source' => $this->source,
            'imported' => $this->imported,
            'skipped' => $this->skipped,
            'message' => $this->message,
            'needs_import' => $this->needsImport(),
            'import_instructions' => $this->importInstructions,
            'attempts' => $this->attempts,
        ];
    }
}
