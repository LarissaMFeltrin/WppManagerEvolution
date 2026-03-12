<?php

namespace App\Console\Commands;

use App\Models\Chat;
use App\Models\Conversa;
use App\Models\WhatsappAccount;
use App\Services\EvolutionApiService;
use Illuminate\Console\Command;

class FixGroupNames extends Command
{
    protected $signature = 'groups:fix-names {--account= : ID da conta específica}';
    protected $description = 'Corrige os nomes dos grupos buscando da Evolution API';

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

            // Buscar todos os chats de grupo
            $groupChats = Chat::where('account_id', $account->id)
                ->where('chat_type', 'group')
                ->get();

            $this->info("  Encontrados {$groupChats->count()} grupos");

            $updated = 0;
            foreach ($groupChats as $chat) {
                // Verificar se o nome parece ser um ID numérico ou pushName de pessoa
                $needsUpdate = preg_match('/^\d+(-\d+)?$/', $chat->chat_name) ||
                               $this->looksLikePersonName($chat->chat_name);

                if (!$needsUpdate) {
                    continue;
                }

                try {
                    $result = $evolution->getGroupInfo($account->session_name, $chat->chat_id);
                    $groupName = $result['subject'] ??
                                 $result['data']['subject'] ??
                                 $result['groupMetadata']['subject'] ??
                                 null;

                    if ($groupName && $groupName !== $chat->chat_name) {
                        $oldName = $chat->chat_name;
                        $chat->update(['chat_name' => $groupName]);

                        // Atualizar conversa também
                        Conversa::where('chat_id', $chat->id)
                            ->whereIn('status', ['aguardando', 'em_atendimento'])
                            ->update(['cliente_nome' => $groupName]);

                        $this->line("    ✓ {$oldName} -> {$groupName}");
                        $updated++;
                    }
                } catch (\Exception $e) {
                    $this->warn("    ✗ Erro ao buscar grupo {$chat->chat_id}: {$e->getMessage()}");
                }

                // Pequena pausa para não sobrecarregar a API
                usleep(200000); // 200ms
            }

            $this->info("  {$updated} grupos atualizados");
        }

        $this->info('Concluído!');
        return 0;
    }

    private function looksLikePersonName(string $name): bool
    {
        // Nomes de pessoas geralmente têm primeira letra maiúscula e são curtos
        // Nomes de grupos geralmente são mais descritivos ou contêm emojis/caracteres especiais
        $words = explode(' ', $name);

        // Se tem apenas 1-2 palavras e todas começam com maiúscula, provavelmente é nome de pessoa
        if (count($words) <= 2) {
            foreach ($words as $word) {
                if (!empty($word) && !ctype_upper(mb_substr($word, 0, 1))) {
                    return false;
                }
            }
            return true;
        }

        return false;
    }
}
