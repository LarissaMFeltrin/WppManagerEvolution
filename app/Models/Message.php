<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'chat_id',
        'message_key',
        'from_jid',
        'sender_name',
        'participant_jid',
        'to_jid',
        'message_text',
        'message_type',
        'media_url',
        'media_mime_type',
        'media_filename',
        'media_duration',
        'is_from_me',
        'sent_by_user_id',
        'timestamp',
        'status',
        'is_edited',
        'is_deleted',
        'reactions',
        'quoted_message_id',
        'quoted_text',
        'latitude',
        'longitude',
        'link_preview_title',
        'link_preview_description',
        'link_preview_url',
        'link_preview_thumbnail',
        'remote_media_url',
        'message_raw',
    ];

    protected $casts = [
        'is_from_me' => 'boolean',
        'is_edited' => 'boolean',
        'is_deleted' => 'boolean',
        'timestamp' => 'integer',
        'reactions' => 'array',
        'message_raw' => 'array',
    ];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    public function sentByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    public function quotedMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'quoted_message_id', 'message_key');
    }

    /**
     * Accessor para from_me (compatibilidade com is_from_me)
     */
    public function getFromMeAttribute(): bool
    {
        return $this->is_from_me ?? false;
    }

    /**
     * Accessor para content (alias para message_text)
     */
    public function getContentAttribute(): ?string
    {
        return $this->message_text;
    }

    /**
     * Obter a data/hora real da mensagem (do timestamp)
     */
    public function getMessageTimeAttribute(): string
    {
        if ($this->timestamp) {
            return \Carbon\Carbon::createFromTimestamp($this->timestamp, 'America/Sao_Paulo')->format('H:i');
        }
        return $this->created_at?->format('H:i') ?? '';
    }

    /**
     * Obter data/hora completa da mensagem
     */
    public function getMessageDatetimeAttribute(): ?\Carbon\Carbon
    {
        if ($this->timestamp) {
            return \Carbon\Carbon::createFromTimestamp($this->timestamp, 'America/Sao_Paulo');
        }
        return $this->created_at;
    }

    /**
     * Obter data formatada (para separadores de dia)
     */
    public function getMessageDateAttribute(): string
    {
        if ($this->timestamp) {
            $date = \Carbon\Carbon::createFromTimestamp($this->timestamp, 'America/Sao_Paulo');
            $today = \Carbon\Carbon::today('America/Sao_Paulo');
            $yesterday = \Carbon\Carbon::yesterday('America/Sao_Paulo');

            if ($date->isSameDay($today)) {
                return 'Hoje';
            } elseif ($date->isSameDay($yesterday)) {
                return 'Ontem';
            } else {
                return $date->format('d/m/Y');
            }
        }
        return $this->created_at?->format('d/m/Y') ?? '';
    }
}
