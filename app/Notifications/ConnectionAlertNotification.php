<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class ConnectionAlertNotification extends Notification
{
    public function __construct(
        public string $instanceName,
        public string $state,
        public string $message,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'action' => 'connection_' . $this->state,
            'message' => $this->message,
            'instance' => $this->instanceName,
            'state' => $this->state,
        ];
    }
}
