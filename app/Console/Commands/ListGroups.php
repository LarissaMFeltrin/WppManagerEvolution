<?php

namespace App\Console\Commands;

use App\Models\Chat;
use Illuminate\Console\Command;

class ListGroups extends Command
{
    protected $signature = 'groups:list {--account= : ID da conta}';
    protected $description = 'Lista todos os grupos cadastrados';

    public function handle()
    {
        $accountId = $this->option('account');

        $query = Chat::where('chat_type', 'group');

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        $groups = $query->orderBy('chat_name')->get();

        $this->info("Total de grupos: " . $groups->count());
        $this->newLine();

        $headers = ['ID', 'Nome Atual', 'JID'];
        $rows = [];

        foreach ($groups as $group) {
            $rows[] = [
                $group->id,
                $group->chat_name,
                $group->chat_id,
            ];
        }

        $this->table($headers, $rows);

        return 0;
    }
}
