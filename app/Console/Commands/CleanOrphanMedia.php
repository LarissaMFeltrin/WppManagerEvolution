<?php

namespace App\Console\Commands;

use App\Models\Message;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanOrphanMedia extends Command
{
    protected $signature = 'media:clean-orphans {--dry-run : Mostrar o que seria removido sem remover}';
    protected $description = 'Remove arquivos de mídia órfãos (sem mensagem correspondente no banco)';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $dryRun = $this->option('dry-run');
        $deleted = 0;
        $totalSize = 0;

        $directories = $disk->directories('media');

        foreach ($directories as $accountDir) {
            $typesDirs = $disk->directories($accountDir);
            foreach ($typesDirs as $typeDir) {
                $files = $disk->files($typeDir);
                foreach ($files as $file) {
                    // Extrair message_key do nome do arquivo
                    $filename = pathinfo($file, PATHINFO_FILENAME);
                    // Formato: messageKey.ext ou messageKey_filename.ext
                    $messageKey = explode('_', $filename)[0];

                    // Verificar se existe mensagem com esse media_url
                    $exists = Message::where('media_url', 'like', '%' . basename($file))
                        ->exists();

                    if (!$exists) {
                        $size = $disk->size($file);
                        $totalSize += $size;

                        if ($dryRun) {
                            $this->line("  [DRY] {$file} (" . round($size / 1024) . "KB)");
                        } else {
                            $disk->delete($file);
                        }
                        $deleted++;
                    }
                }
            }
        }

        $action = $dryRun ? 'Encontrados' : 'Removidos';
        $this->info("{$action}: {$deleted} arquivos órfãos (" . round($totalSize / 1024 / 1024, 1) . "MB)");

        return Command::SUCCESS;
    }
}
