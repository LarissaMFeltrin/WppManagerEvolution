<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogSistema extends Model
{
    protected $table = 'log_sistemas';

    protected $fillable = [
        'tipo',
        'nivel',
        'evento',
        'instancia',
        'mensagem',
        'dados',
        'ip',
        'criada_em',
    ];

    protected $casts = [
        'dados' => 'array',
        'criada_em' => 'datetime',
    ];

    /**
     * Registrar log de webhook
     */
    public static function webhook(string $evento, string $instancia, string $mensagem, ?array $dados = null, string $nivel = 'info'): self
    {
        return self::create([
            'tipo' => 'webhook',
            'nivel' => $nivel,
            'evento' => $evento,
            'instancia' => $instancia,
            'mensagem' => $mensagem,
            'dados' => $dados,
            'ip' => request()->ip(),
            'criada_em' => now(),
        ]);
    }

    /**
     * Registrar log de envio
     */
    public static function envio(string $instancia, string $mensagem, ?array $dados = null, string $nivel = 'info'): self
    {
        return self::create([
            'tipo' => 'envio',
            'nivel' => $nivel,
            'evento' => 'send.message',
            'instancia' => $instancia,
            'mensagem' => $mensagem,
            'dados' => $dados,
            'ip' => request()->ip(),
            'criada_em' => now(),
        ]);
    }

    /**
     * Registrar log de erro
     */
    public static function erro(string $mensagem, ?array $dados = null, ?string $instancia = null): self
    {
        return self::create([
            'tipo' => 'erro',
            'nivel' => 'error',
            'instancia' => $instancia,
            'mensagem' => $mensagem,
            'dados' => $dados,
            'ip' => request()->ip(),
            'criada_em' => now(),
        ]);
    }

    /**
     * Registrar log de conexão
     */
    public static function conexao(string $instancia, string $mensagem, string $nivel = 'info'): self
    {
        return self::create([
            'tipo' => 'conexao',
            'nivel' => $nivel,
            'evento' => 'connection.update',
            'instancia' => $instancia,
            'mensagem' => $mensagem,
            'criada_em' => now(),
        ]);
    }

    /**
     * Limpar logs antigos
     */
    public static function limparAntigos(int $dias = 30): int
    {
        return self::where('criada_em', '<', now()->subDays($dias))->delete();
    }
}
