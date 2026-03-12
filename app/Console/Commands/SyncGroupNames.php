<?php

namespace App\Console\Commands;

use App\Models\Chat;
use App\Models\Conversa;
use App\Models\WhatsappAccount;
use App\Services\EvolutionApiService;
use Illuminate\Console\Command;

class SyncGroupNames extends Command
{
    protected $signature = 'groups:sync {--account= : ID da conta específica}';
    protected $description = 'Sincroniza nomes dos grupos buscando TODOS de uma vez da Evolution API';

    public function handle(EvolutionApiService $evolution)
    {
        $accountId = $this->option('account');

        $query = WhatsappAccount::where('is_connected', true);
        if ($accountId) {
            $query->where('id', $accountId);
        }

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            $this->error('Nenhuma conta conectada encontrada.');
            return 1;
        }

        foreach ($accounts as $account) {
            $this->info("Processando conta: {$account->session_name}");

            try {
                // Buscar TODOS os grupos de uma vez (muito mais rápido)
                $result = $evolution->fetchGroups($account->session_name);

                $groups = $result['data'] ?? $result['groups'] ?? $result ?? [];

                if (!is_array($groups)) {
                    $this->warn("  Resposta inesperada da API");
                    continue;
                }

                $this->info("  Encontrados " . count($groups) . " grupos na API");

                $updated = 0;
                foreach ($groups as $group) {
                    $groupJid = $group['id'] ?? $group['jid'] ?? null;
                    $groupName = $group['subject'] ?? $group['name'] ?? null;

                    if (!$groupJid || !$groupName) {
                        continue;
                    }

                    // Buscar chat local
                    $chat = Chat::where('account_id', $account->id)
                        ->where('chat_id', $groupJid)
                        ->first();

                    if (!$chat) {
                        continue;
                    }

                    // Verificar se precisa atualizar
                    if ($chat->chat_name !== $groupName) {
                        $oldName = $chat->chat_name;
                        $chat->update(['chat_name' => $groupName]);

                        // Atualizar conversas ativas também
                        Conversa::where('chat_id', $chat->id)
                            ->whereIn('status', ['aguardando', 'em_atendimento'])
                            ->update(['cliente_nome' => $groupName]);

                        $this->line("    ✓ {$oldName} -> {$groupName}");
                        $updated++;
                    }
                }

                $this->info("  {$updated} grupos atualizados");

            } catch (\Exception $e) {
                $this->error("  Erro: {$e->getMessage()}");
            }
        }

        $this->info('Concluído!');
        return 0;
    }
}
