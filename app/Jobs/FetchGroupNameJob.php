<?php

namespace App\Jobs;

use App\Models\Chat;
use App\Models\Conversa;
use App\Models\WhatsappAccount;
use App\Services\EvolutionApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchGroupNameJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30; // 30 segundos entre tentativas
    public int $timeout = 60; // timeout do job

    public function __construct(
        public int $chatId,
        public int $accountId,
        public string $groupJid
    ) {}

    public function handle(EvolutionApiService $evolution): void
    {
        $chat = Chat::find($this->chatId);
        $account = WhatsappAccount::find($this->accountId);

        if (!$chat || !$account) {
            Log::warning('FetchGroupNameJob: Chat ou Account não encontrado', [
                'chat_id' => $this->chatId,
                'account_id' => $this->accountId
            ]);
            return;
        }

        // Verificar se o nome já foi atualizado (outro job pode ter rodado)
        if (!$this->needsUpdate($chat->chat_name)) {
            Log::info('FetchGroupNameJob: Nome já atualizado, pulando', [
                'chat_id' => $this->chatId,
                'chat_name' => $chat->chat_name
            ]);
            return;
        }

        try {
            $result = $evolution->getGroupInfo($account->session_name, $this->groupJid);

            $groupName = $result['subject'] ??
                         $result['data']['subject'] ??
                         $result['groupMetadata']['subject'] ??
                         null;

            if ($groupName) {
                // Atualizar chat
                $chat->update(['chat_name' => $groupName]);

                // Atualizar conversa se existir
                Conversa::where('chat_id', $chat->id)
                    ->whereIn('status', ['aguardando', 'em_atendimento'])
                    ->update(['cliente_nome' => $groupName]);

                Log::info('FetchGroupNameJob: Nome do grupo atualizado', [
                    'chat_id' => $this->chatId,
                    'group_jid' => $this->groupJid,
                    'group_name' => $groupName
                ]);
            }
        } catch (\Exception $e) {
            Log::error('FetchGroupNameJob: Erro ao buscar nome do grupo', [
                'chat_id' => $this->chatId,
                'group_jid' => $this->groupJid,
                'error' => $e->getMessage()
            ]);

            // Re-throw para que o job seja retentado
            throw $e;
        }
    }

    /**
     * Verifica se o nome precisa ser atualizado
     * Retorna true se o nome parece ser um ID numérico ou está vazio
     */
    private function needsUpdate(?string $name): bool
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

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('FetchGroupNameJob: Job falhou após todas tentativas', [
            'chat_id' => $this->chatId,
            'group_jid' => $this->groupJid,
            'error' => $exception->getMessage()
        ]);
    }
}
