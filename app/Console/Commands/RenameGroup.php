<?php

namespace App\Console\Commands;

use App\Models\Chat;
use App\Models\Conversa;
use Illuminate\Console\Command;

class RenameGroup extends Command
{
    protected $signature = 'group:rename {jid : JID do grupo (ex: 554497285348-1556315939@g.us)} {name : Novo nome do grupo}';
    protected $description = 'Renomeia um grupo manualmente';

    public function handle()
    {
        $jid = $this->argument('jid');
        $name = $this->argument('name');

        // Adicionar @g.us se não tiver
        if (!str_contains($jid, '@')) {
            $jid .= '@g.us';
        }

        $chat = Chat::where('chat_id', $jid)->first();

        if (!$chat) {
            $this->error("Grupo não encontrado: {$jid}");
            return 1;
        }

        $oldName = $chat->chat_name;
        $chat->update(['chat_name' => $name]);

        // Atualizar conversas ativas
        $updated = Conversa::where('chat_id', $chat->id)
            ->whereIn('status', ['aguardando', 'em_atendimento'])
            ->update(['cliente_nome' => $name]);

        $this->info("Grupo renomeado:");
        $this->line("  JID: {$jid}");
        $this->line("  Nome anterior: {$oldName}");
        $this->line("  Novo nome: {$name}");
        $this->line("  Conversas atualizadas: {$updated}");

        return 0;
    }
}
