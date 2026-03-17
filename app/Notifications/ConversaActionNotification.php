<?php

namespace App\Notifications;

use App\Models\Conversa;
use App\Models\User;
use Illuminate\Notifications\Notification;

class ConversaActionNotification extends Notification
{
    public function __construct(
        public string $action,      // 'devolvida' | 'finalizada_fila'
        public Conversa $conversa,
        public User $agent,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $messages = [
            'devolvida' => "{$this->agent->name} devolveu a conversa de {$this->conversa->cliente_nome} ({$this->conversa->cliente_numero}) para a fila.",
            'finalizada_fila' => "{$this->agent->name} finalizou a conversa de {$this->conversa->cliente_nome} ({$this->conversa->cliente_numero}) direto da fila.",
        ];

        return [
            'action' => $this->action,
            'message' => $messages[$this->action] ?? "Ação {$this->action} na conversa {$this->conversa->id}",
            'conversa_id' => $this->conversa->id,
            'agent_id' => $this->agent->id,
            'agent_name' => $this->agent->name,
            'cliente_nome' => $this->conversa->cliente_nome,
            'cliente_numero' => $this->conversa->cliente_numero,
        ];
    }
}
