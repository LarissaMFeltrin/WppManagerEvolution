<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_sistemas', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 50)->index();          // webhook, envio, erro, conexao, sistema
            $table->string('nivel', 20)->default('info');  // info, warning, error
            $table->string('evento', 100)->nullable();     // messages.upsert, send.message, etc
            $table->string('instancia', 50)->nullable();   // nome da instância
            $table->text('mensagem');                       // descrição do evento
            $table->json('dados')->nullable();              // payload/dados extras
            $table->string('ip', 45)->nullable();
            $table->timestamp('criada_em')->useCurrent()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_sistemas');
    }
};
