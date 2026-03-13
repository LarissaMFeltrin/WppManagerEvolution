<?php

use App\Http\Controllers\Admin\ChatController;
use App\Http\Controllers\Admin\ContactController;
use App\Http\Controllers\Admin\ConversaController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmpresaController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\LogController;
use App\Http\Controllers\Admin\MonitorController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WhatsappAccountController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

// Redirect root to admin
Route::get('/', fn() => redirect()->route('admin.dashboard'));

// Auth routes
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Admin routes (protected)
Route::prefix('admin')->middleware('auth')->name('admin.')->group(function () {

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Users
    Route::resource('users', UserController::class)->except(['show']);

    // Empresas
    Route::resource('empresas', EmpresaController::class)->except(['show']);

    // WhatsApp Accounts
    Route::get('whatsapp/status/check', [WhatsappAccountController::class, 'checkStatus'])->name('whatsapp.check-status');
    Route::resource('whatsapp', WhatsappAccountController::class)->except(['show']);
    Route::post('whatsapp/{whatsapp}/disconnect', [WhatsappAccountController::class, 'disconnect'])->name('whatsapp.disconnect');
    Route::post('whatsapp/{whatsapp}/restart', [WhatsappAccountController::class, 'restart'])->name('whatsapp.restart');
    Route::get('whatsapp/{whatsapp}/qrcode', [WhatsappAccountController::class, 'qrcode'])->name('whatsapp.qrcode');

    // Conversas
    Route::get('conversas', [ConversaController::class, 'index'])->name('conversas.index');
    Route::get('conversas/{conversa}', [ConversaController::class, 'show'])->name('conversas.show');
    Route::post('conversas/{conversa}/atender', [ConversaController::class, 'atender'])->name('conversas.atender');
    Route::post('conversas/{conversa}/finalizar', [ConversaController::class, 'finalizar'])->name('conversas.finalizar');
    Route::post('conversas/{conversa}/transferir', [ConversaController::class, 'transferir'])->name('conversas.transferir');
    Route::get('conversas/{conversa}/mensagens', [ConversaController::class, 'mensagens'])->name('conversas.mensagens');

    // Chat
    Route::get('chat', [ChatController::class, 'index'])->name('chat');
    Route::post('chat/{conversa}/enviar', [ChatController::class, 'enviar'])->name('chat.enviar');
    Route::get('fila', [ChatController::class, 'fila'])->name('fila');
    Route::get('fila/dados', [ChatController::class, 'filaDados'])->name('fila.dados');

    // Painel de Conversas (Dashboard de Atendimento)
    Route::get('painel', [ChatController::class, 'painel'])->name('painel');
    Route::post('painel/nova-conversa', [ChatController::class, 'novaConversa'])->name('painel.nova-conversa');
    Route::post('painel/{conversa}/enviar', [ChatController::class, 'enviarAjax'])->name('painel.enviar');
    Route::get('painel/{conversa}/mensagens', [ChatController::class, 'mensagens'])->name('painel.mensagens');
    Route::post('painel/{conversa}/finalizar', [ChatController::class, 'finalizarAjax'])->name('painel.finalizar');

    // Envio de mídia
    Route::post('painel/{conversa}/enviar-imagem', [ChatController::class, 'enviarImagem'])->name('painel.enviar-imagem');
    Route::post('painel/{conversa}/enviar-documento', [ChatController::class, 'enviarDocumento'])->name('painel.enviar-documento');
    Route::post('painel/{conversa}/enviar-audio', [ChatController::class, 'enviarAudio'])->name('painel.enviar-audio');
    Route::post('painel/{conversa}/enviar-video', [ChatController::class, 'enviarVideo'])->name('painel.enviar-video');

    // Interações com mensagens
    Route::post('painel/{conversa}/reagir', [ChatController::class, 'reagir'])->name('painel.reagir');
    Route::post('painel/{conversa}/deletar', [ChatController::class, 'deletar'])->name('painel.deletar');
    Route::post('painel/{conversa}/editar', [ChatController::class, 'editar'])->name('painel.editar');
    Route::post('painel/{conversa}/encaminhar', [ChatController::class, 'encaminhar'])->name('painel.encaminhar');
    Route::post('painel/{conversa}/marcar-lido', [ChatController::class, 'marcarLido'])->name('painel.marcar-lido');
    Route::post('painel/{conversa}/digitando', [ChatController::class, 'digitando'])->name('painel.digitando');
    Route::post('painel/{conversa}/devolver', [ChatController::class, 'devolver'])->name('painel.devolver');

    // Sincronização de histórico
    Route::post('painel/{conversa}/sincronizar-historico', [ChatController::class, 'sincronizarHistorico'])->name('painel.sincronizar-historico');
    Route::post('painel/{conversa}/baixar-midias', [ChatController::class, 'baixarMidias'])->name('painel.baixar-midias');
    Route::post('whatsapp/{account}/sincronizar-chats', [ChatController::class, 'sincronizarChats'])->name('whatsapp.sincronizar-chats');

    // Monitor
    Route::get('monitor', [MonitorController::class, 'index'])->name('monitor');
    Route::get('supervisao', [MonitorController::class, 'supervisao'])->name('supervisao');
    Route::get('historico', [MonitorController::class, 'historico'])->name('historico');
    Route::get('saude', [MonitorController::class, 'saude'])->name('saude');

    // Contatos
    Route::get('contatos', [ContactController::class, 'index'])->name('contatos.index');
    Route::get('contatos/sincronizar', [ContactController::class, 'sincronizarPage'])->name('contatos.sincronizar.page');
    Route::get('contatos/duplicados', [ContactController::class, 'duplicados'])->name('contatos.duplicados');
    Route::get('contatos/chats-sem-contato', [ContactController::class, 'chatsSemContato'])->name('contatos.chats-sem-contato');
    Route::post('contatos/criar-do-chat', [ContactController::class, 'criarDoChat'])->name('contatos.criar-do-chat');
    Route::post('contatos/mesclar', [ContactController::class, 'mesclarChats'])->name('contatos.mesclar');
    Route::post('contatos/mesclar-chats', [ContactController::class, 'mesclarChats'])->name('contatos.mesclar-chats');
    Route::post('contatos/atualizar-chat', [ContactController::class, 'atualizarChat'])->name('contatos.atualizar-chat');
    Route::get('contatos/buscar-chats', [ContactController::class, 'buscarChats'])->name('contatos.buscar-chats');
    Route::get('contatos/{contact}/edit', [ContactController::class, 'edit'])->name('contatos.edit');
    Route::put('contatos/{contact}', [ContactController::class, 'update'])->name('contatos.update');
    Route::post('contatos/sincronizar', [ContactController::class, 'sincronizar'])->name('contatos.sincronizar');
    Route::post('contatos/enviar-mensagem', [ContactController::class, 'enviarMensagem'])->name('contatos.enviar-mensagem');
    Route::get('contatos/{contact}/conversa', [ContactController::class, 'abrirConversa'])->name('contatos.abrir-conversa');

    // Logs
    Route::get('logs', [LogController::class, 'index'])->name('logs');
    Route::get('logs/{log}', [LogController::class, 'show'])->name('logs.show');
    Route::post('logs/limpar', [LogController::class, 'limpar'])->name('logs.limpar');

    // Importação de histórico (sem limite de tamanho de upload)
    Route::get('import', [ImportController::class, 'index'])->name('import.index');
    Route::post('import', [ImportController::class, 'store'])
        ->name('import.store')
        ->withoutMiddleware(\Illuminate\Http\Middleware\ValidatePostSize::class);
    Route::post('import/analyze', [ImportController::class, 'analyze'])
        ->name('import.analyze')
        ->withoutMiddleware(\Illuminate\Http\Middleware\ValidatePostSize::class);
});
