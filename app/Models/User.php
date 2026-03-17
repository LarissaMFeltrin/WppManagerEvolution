<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'empresa_id',
        'auth_token',
        'token_expires_at',
        'role',
        'status_atendimento',
        'max_conversas',
        'conversas_ativas',
        'ultimo_acesso',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'auth_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'token_expires_at' => 'datetime',
            'ultimo_acesso' => 'datetime',
            'password' => 'hashed',
            'max_conversas' => 'integer',
            'conversas_ativas' => 'integer',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function whatsappAccountsOwned(): HasMany
    {
        return $this->hasMany(WhatsappAccount::class);
    }

    public function whatsappAccounts(): BelongsToMany
    {
        return $this->belongsToMany(WhatsappAccount::class, 'user_account', 'user_id', 'account_id')
            ->withTimestamps();
    }

    public function conversas(): HasMany
    {
        return $this->hasMany(Conversa::class, 'atendente_id');
    }

    public function conversasDevolvidas(): HasMany
    {
        return $this->hasMany(Conversa::class, 'devolvida_por');
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sent_by_user_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Admin SaaS: empresa_id null = acesso a todas as empresas
     */
    public function isSuperAdmin(): bool
    {
        return $this->isAdmin() && $this->empresa_id === null;
    }

    /**
     * Retorna IDs das contas WhatsApp que o usuário pode acessar
     * Super admin (sem empresa) = todas as contas
     * Demais = só contas da empresa dele
     */
    public function getAccountIds(): \Illuminate\Support\Collection
    {
        if ($this->isSuperAdmin()) {
            return WhatsappAccount::pluck('id');
        }

        return WhatsappAccount::where('empresa_id', $this->empresa_id)->pluck('id');
    }

    public function isSupervisor(): bool
    {
        return $this->role === 'supervisor';
    }

    public function isAgent(): bool
    {
        return $this->role === 'agent';
    }

    public function isOnline(): bool
    {
        return $this->status_atendimento === 'online';
    }

    public function isOcupado(): bool
    {
        return $this->status_atendimento === 'ocupado';
    }

    public function isOffline(): bool
    {
        return $this->status_atendimento === 'offline';
    }

    public function podeReceberConversa(): bool
    {
        return $this->isOnline() && $this->conversas_ativas < $this->max_conversas;
    }

    // AdminLTE required methods
    public function adminlte_desc(): string
    {
        return match ($this->role) {
            'admin' => 'Administrador',
            'supervisor' => 'Supervisor',
            default => 'Agente',
        };
    }

    public function adminlte_image(): ?string
    {
        return null; // Retorna null para usar o avatar padrão
    }

    public function adminlte_profile_url(): ?string
    {
        return null; // Pode ser configurado para uma rota de perfil futuramente
    }
}
