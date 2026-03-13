@php
use App\Helpers\WhatsAppFormatter;
@endphp
@extends('adminlte::page')

@section('title', 'Painel de Conversas')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1>Dashboard de Atendimento</h1>
        <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Pagina Inicial</a></li>
            <li class="breadcrumb-item active">Dashboard de Atendimento</li>
        </ol>
    </div>
@stop

@section('css')
<style>
:root {
    --chat-bg: #e5ddd5;
    --msg-sent-bg: #dcf8c6;
    --msg-received-bg: #ffffff;
    --header-bg: #f0f2f5;
}

.painel-header {
    background: #075e54;
    color: white;
    padding: 10px 20px;
    border-radius: 5px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.painel-header .info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.painel-header .info i {
    font-size: 1.5rem;
}

.painel-header .actions {
    display: flex;
    gap: 10px;
}

.chats-container {
    display: flex;
    gap: 15px;
    overflow-x: auto;
    padding-bottom: 15px;
    min-height: calc(100vh - 250px);
}

.chat-column {
    min-width: 380px;
    max-width: 420px;
    flex: 1;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.12);
    display: flex;
    flex-direction: column;
    height: calc(100vh - 250px);
}

.chat-column-header {
    background: var(--header-bg);
    padding: 10px 15px;
    border-radius: 8px 8px 0 0;
    display: flex;
    align-items: center;
    position: relative;
    gap: 10px;
    border-bottom: 1px solid #ddd;
}

.chat-column-header .avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #dfe5e7;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    color: #fff;
    font-size: 0.9rem;
}

.chat-column-header .avatar.color-1 { background: #25d366; }
.chat-column-header .avatar.color-2 { background: #128c7e; }
.chat-column-header .avatar.color-3 { background: #075e54; }
.chat-column-header .avatar.color-4 { background: #34b7f1; }
.chat-column-header .avatar.color-5 { background: #00a884; }

.chat-column-header .info {
    flex: 1;
}

.chat-column-header .info .name {
    font-weight: 600;
    font-size: 0.95rem;
}

.chat-column-header .info .number {
    font-size: 0.8rem;
    color: #667781;
}

.chat-column-header .info .badge-group {
    font-size: 0.7rem;
    background: #25d366;
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
    margin-left: 5px;
}

.chat-column-header .actions {
    display: flex;
    gap: 5px;
}

.chat-column-header .actions .btn {
    padding: 5px 8px;
    font-size: 0.85rem;
}

.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 15px;
    background: var(--chat-bg);
    background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23c8c8c8' fill-opacity='0.1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}

.message {
    margin-bottom: 8px;
    display: flex;
    position: relative;
}

.message.sent {
    justify-content: flex-end;
}

.message.received {
    justify-content: flex-start;
}

.message-bubble {
    max-width: 85%;
    padding: 6px 10px;
    border-radius: 8px;
    position: relative;
    box-shadow: 0 1px 0.5px rgba(0,0,0,0.13);
    cursor: pointer;
}

.message.sent .message-bubble {
    background: var(--msg-sent-bg);
    border-radius: 8px 0 8px 8px;
}

.message.received .message-bubble {
    background: var(--msg-received-bg);
    border-radius: 0 8px 8px 8px;
}

.message-sender {
    font-size: 0.75rem;
    font-weight: 600;
    color: #075e54;
    margin-bottom: 2px;
}

.message-quoted {
    background: rgba(0,0,0,0.05);
    border-left: 3px solid #25d366;
    padding: 5px 8px;
    margin-bottom: 5px;
    border-radius: 3px;
    font-size: 0.8rem;
    color: #667781;
}

.message-text {
    word-wrap: break-word;
    font-size: 0.9rem;
    line-height: 1.4;
}

.message-text .wa-code {
    background: rgba(0, 0, 0, 0.05);
    padding: 2px 5px;
    border-radius: 3px;
    font-family: monospace;
    font-size: 0.85rem;
}

.message-text .wa-code-block {
    background: rgba(0, 0, 0, 0.05);
    padding: 10px;
    border-radius: 5px;
    font-family: monospace;
    font-size: 0.85rem;
    margin: 5px 0;
    white-space: pre-wrap;
    overflow-x: auto;
}

.message-time {
    font-size: 0.7rem;
    color: #667781;
    text-align: right;
    margin-top: 3px;
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 3px;
}

.message.sent .message-time .check {
    color: #53bdeb;
}

.message-deleted {
    font-style: italic;
    color: #8696a0;
}

.message-edited {
    font-size: 0.7rem;
    color: #8696a0;
    margin-right: 5px;
}

.message-reactions {
    display: flex;
    gap: 3px;
    margin-top: 3px;
}

.message-reactions .reaction {
    background: rgba(0,0,0,0.05);
    padding: 2px 5px;
    border-radius: 10px;
    font-size: 0.8rem;
}

/* Date separator */
.message-date-separator {
    display: flex;
    justify-content: center;
    margin: 15px 0;
}

.message-date-separator span {
    background: #e1f3fb;
    color: #54656f;
    padding: 5px 12px;
    border-radius: 8px;
    font-size: 0.75rem;
    box-shadow: 0 1px 0.5px rgba(0,0,0,0.13);
}

/* Media */
.message-media-img {
    max-width: 250px;
    max-height: 300px;
    border-radius: 5px;
    cursor: pointer;
}

.message-media-video {
    max-width: 250px;
    border-radius: 5px;
}

.message-media-audio {
    width: 220px;
}

.audio-container {
    display: flex;
    align-items: center;
    gap: 8px;
}

.audio-duration {
    font-size: 12px;
    color: #667781;
    font-weight: 500;
}

.message-document {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background: rgba(0,0,0,0.05);
    border-radius: 5px;
    cursor: pointer;
    min-width: 180px;
}

.message-document i {
    font-size: 2rem;
    color: #8696a0;
}

.message-document .doc-info {
    flex: 1;
}

.message-document .doc-name {
    font-size: 0.85rem;
    font-weight: 500;
    word-break: break-all;
}

.message-document .doc-download {
    color: #25d366;
}

/* Context Menu */
.message-context-menu {
    position: fixed;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    z-index: 9998;
    min-width: 150px;
    display: none;
}

.message-context-menu.show {
    display: block;
}

.message-context-menu .menu-item {
    padding: 10px 15px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.9rem;
}

.message-context-menu .menu-item:hover {
    background: #f5f5f5;
}

.message-context-menu .menu-item i {
    width: 18px;
    text-align: center;
}

/* Emoji Picker */
.emoji-picker {
    position: fixed;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    padding: 10px;
    display: none;
    z-index: 9999;
}

.emoji-picker.show {
    display: block;
}

.emoji-picker .emoji-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    max-width: 280px;
}

.emoji-picker .emoji {
    font-size: 1.3rem;
    cursor: pointer;
    padding: 3px;
    text-align: center;
    border-radius: 3px;
}

.emoji-picker .emoji:hover {
    background: #f0f0f0;
}

/* Input */
.chat-input {
    padding: 10px;
    background: var(--header-bg);
    border-radius: 0 0 8px 8px;
    border-top: 1px solid #ddd;
}

.chat-input-reply {
    background: #e5f7e5;
    padding: 8px 12px;
    border-radius: 5px;
    margin-bottom: 8px;
    display: none;
    align-items: center;
    gap: 10px;
}

.chat-input-reply.show {
    display: flex;
}

.chat-input-reply .reply-text {
    flex: 1;
    font-size: 0.85rem;
    color: #667781;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.chat-input-reply .reply-close {
    cursor: pointer;
    color: #8696a0;
}

.chat-input form {
    display: flex;
    gap: 8px;
    align-items: center;
}

.chat-input .input-icons {
    display: flex;
    gap: 3px;
    color: #8696a0;
}

.chat-input .input-icons .icon-btn {
    cursor: pointer;
    padding: 8px;
    border-radius: 50%;
    transition: background 0.2s;
}

.chat-input .input-icons .icon-btn:hover {
    background: #ddd;
    color: #075e54;
}

.chat-input input[type="text"] {
    flex: 1;
    border: none;
    border-radius: 20px;
    padding: 10px 15px;
    font-size: 0.9rem;
}

.chat-input input[type="text"]:focus {
    outline: none;
    box-shadow: none;
}

.chat-input .btn-send {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.chat-input .btn-mic {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #075e54;
    color: white;
    border: none;
}

.chat-input .btn-mic.recording {
    background: #dc3545;
    animation: pulse 1s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

/* Attachment Menu */
.attachment-menu {
    position: absolute;
    bottom: 100%;
    left: 0;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    padding: 10px;
    display: none;
    z-index: 1000;
}

.attachment-menu.show {
    display: block;
}

.attachment-menu .attachment-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 15px;
    cursor: pointer;
    border-radius: 5px;
}

.attachment-menu .attachment-item:hover {
    background: #f5f5f5;
}

.attachment-menu .attachment-item i {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.attachment-menu .attachment-item.img i { background: #7f66ff; }
.attachment-menu .attachment-item.video i { background: #ec407a; }
.attachment-menu .attachment-item.doc i { background: #5157ae; }
.attachment-menu .attachment-item.audio i { background: #ee6e25; }

.slot-disponivel {
    min-width: 150px;
    max-width: 200px;
    background: #f8f9fa;
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: calc(100vh - 250px);
    color: #adb5bd;
}

.slot-disponivel i {
    font-size: 2rem;
    margin-bottom: 10px;
}

.slot-disponivel span {
    font-size: 0.85rem;
}

/* Scrollbar */
.chat-messages::-webkit-scrollbar {
    width: 6px;
}

.chat-messages::-webkit-scrollbar-track {
    background: transparent;
}

.chat-messages::-webkit-scrollbar-thumb {
    background: #ccc;
    border-radius: 3px;
}

.chats-container::-webkit-scrollbar {
    height: 8px;
}

.chats-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.chats-container::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

/* Image Modal */
.image-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.9);
    z-index: 2000;
    display: none;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.image-modal.show {
    display: flex;
}

.image-modal img {
    max-width: 90%;
    max-height: 85%;
}

.image-modal-toolbar {
    position: absolute;
    top: 15px;
    right: 15px;
    display: flex;
    gap: 10px;
}

/* Timer de espera do cliente */
.waiting-timer {
    position: absolute;
    top: 5px;
    right: 5px;
    background: #dc3545;
    color: white;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: bold;
    animation: pulse 1s infinite;
}

.chat-column.cliente-aguardando {
    border-color: #dc3545 !important;
    animation: borderPulse 1s infinite;
}

@keyframes borderPulse {
    0%, 100% { box-shadow: 0 0 5px rgba(220, 53, 69, 0.5); }
    50% { box-shadow: 0 0 15px rgba(220, 53, 69, 0.8); }
}

/* Paste preview */
.paste-preview-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.7);
    z-index: 2001;
    display: none;
    align-items: center;
    justify-content: center;
}

.paste-preview-modal.show {
    display: flex;
}

.paste-preview-content {
    background: white;
    padding: 20px;
    border-radius: 10px;
    text-align: center;
    max-width: 400px;
}

.paste-preview-content img {
    max-width: 100%;
    max-height: 300px;
    margin-bottom: 15px;
    border-radius: 5px;
}

/* Sidebar da Fila */
.fila-sidebar-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.3);
    z-index: 1040;
    display: none;
}

.fila-sidebar-overlay.show {
    display: block;
}

.fila-sidebar {
    position: fixed;
    top: 0;
    right: -400px;
    width: 400px;
    height: 100vh;
    background: #f4f6f9;
    z-index: 1050;
    box-shadow: -4px 0 15px rgba(0,0,0,0.15);
    transition: right 0.3s ease;
    display: flex;
    flex-direction: column;
}

.fila-sidebar.show {
    right: 0;
}

.fila-sidebar-header {
    background: #ffc107;
    color: #212529;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}

.fila-sidebar-header h5 {
    margin: 0;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.fila-sidebar-header .badge {
    font-size: 0.9rem;
}

.fila-sidebar-header .close-btn {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #212529;
    padding: 0;
    line-height: 1;
}

.fila-sidebar-body {
    flex: 1;
    overflow-y: auto;
    padding: 15px;
}

.fila-sidebar-item {
    background: #fff;
    border-radius: 8px;
    padding: 12px 15px;
    margin-bottom: 10px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    border-left: 4px solid #ffc107;
    display: flex;
    align-items: center;
    gap: 12px;
}

.fila-sidebar-item .avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    color: #fff;
    font-size: 0.85rem;
    flex-shrink: 0;
}

.fila-sidebar-item .avatar.color-1 { background: #25d366; }
.fila-sidebar-item .avatar.color-2 { background: #128c7e; }
.fila-sidebar-item .avatar.color-3 { background: #075e54; }
.fila-sidebar-item .avatar.color-4 { background: #34b7f1; }
.fila-sidebar-item .avatar.color-5 { background: #00a884; }

.fila-sidebar-item .info {
    flex: 1;
    min-width: 0;
}

.fila-sidebar-item .info .nome {
    font-weight: 600;
    font-size: 0.9rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.fila-sidebar-item .info .numero {
    font-size: 0.8rem;
    color: #6c757d;
}

.fila-sidebar-item .info .preview {
    font-size: 0.8rem;
    color: #6c757d;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 3px;
}

.fila-sidebar-item .info .tempo {
    font-size: 0.75rem;
    color: #dc3545;
    margin-top: 3px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.fila-sidebar-item .btn-pegar {
    flex-shrink: 0;
}

.fila-sidebar-empty {
    text-align: center;
    padding: 40px 20px;
    color: #6c757d;
}

.fila-sidebar-empty i {
    font-size: 3rem;
    color: #28a745;
    margin-bottom: 15px;
}

.fila-sidebar-loading {
    text-align: center;
    padding: 40px 20px;
    color: #6c757d;
}

</style>
@stop

@section('content')
{{-- Header do Painel --}}
<div class="painel-header">
    <div class="info">
        <i class="fas fa-headset"></i>
        <div>
            <strong>Dashboard de Atendimento</strong>
            <span class="ml-3">{{ $user->name }} &mdash; {{ $slotsUsados }}/{{ $maxSlots }}</span>
        </div>
    </div>
    <div class="actions">
        <button class="btn btn-success btn-sm" id="btnNovaConversa">
            <i class="fas fa-plus"></i> Nova Conversa
        </button>
        <button class="btn btn-danger btn-sm" id="btnFinalizarTodas" {{ $slotsUsados == 0 ? 'disabled' : '' }}>
            <i class="fas fa-times-circle"></i> Finalizar Todas
        </button>
        <button class="btn btn-warning btn-sm" id="btnFilaSidebar">
            <i class="fas fa-users"></i> Fila <span class="badge badge-light" id="filaCountBadge">{{ $filaCount }}</span>
        </button>
        <button class="btn btn-light btn-sm" id="btnRefresh">
            <i class="fas fa-sync-alt"></i>
        </button>
    </div>
</div>

{{-- Container de Chats --}}
<div class="chats-container">
    @foreach($conversas as $index => $conversa)
    @php
        $isGroup = $conversa->chat && $conversa->chat->chat_type === 'group';
        // Calcular se cliente está aguardando resposta
        // As mensagens vêm ordenadas DESC (mais recente primeiro)
        $lastClientMsgTime = null;
        if ($conversa->chat && $conversa->chat->messages->count() > 0) {
            $lastMsg = $conversa->chat->messages->first(); // Mais recente
            // Se a última mensagem NÃO é minha E tem mensagens não lidas, cliente está aguardando
            // Se unread_count = 0, foi marcado como lido e não deve mostrar timer
            if (!$lastMsg->is_from_me && ($conversa->chat->unread_count ?? 0) > 0) {
                $lastClientMsgTime = $lastMsg->timestamp;
            }
        }
    @endphp
    <div class="chat-column {{ $lastClientMsgTime ? 'cliente-aguardando' : '' }}"
         data-conversa-id="{{ $conversa->id }}"
         data-is-group="{{ $isGroup ? '1' : '0' }}"
         data-last-client-msg="{{ $lastClientMsgTime }}">
        {{-- Header --}}
        <div class="chat-column-header">
            <div class="avatar color-{{ ($index % 5) + 1 }}">
                @if($isGroup)
                    <i class="fas fa-users" style="font-size: 1rem;"></i>
                @else
                    {{ strtoupper(substr($conversa->cliente_nome ?? 'C', 0, 2)) }}
                @endif
            </div>
            <div class="info">
                <div class="name">
                    {{ $conversa->cliente_nome ?? 'Cliente' }}
                    @if($isGroup)
                        <span class="badge-group">Grupo</span>
                    @endif
                </div>
                <div class="number">{{ $conversa->cliente_numero }}</div>
            </div>
            <div class="actions">
                <button class="btn btn-sm btn-light btn-refresh-chat" title="Atualizar">
                    <i class="fas fa-sync-alt"></i>
                </button>
                <div class="dropdown">
                    <button class="btn btn-sm btn-light dropdown-toggle" data-toggle="dropdown">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item btn-sincronizar-historico" href="#" data-id="{{ $conversa->id }}">
                            <i class="fas fa-history text-warning"></i> Buscar historico
                        </a>
                        <a class="dropdown-item btn-baixar-midias" href="#" data-id="{{ $conversa->id }}">
                            <i class="fas fa-download text-info"></i> Baixar midias
                        </a>
                        <a class="dropdown-item btn-editar-contato" href="#"
                           data-id="{{ $conversa->id }}"
                           data-chat-id="{{ $conversa->chat?->id }}"
                           data-nome="{{ $conversa->chat?->chat_name }}"
                           data-jid="{{ $conversa->chat?->chat_id }}"
                           data-numero="{{ $conversa->cliente_numero }}">
                            <i class="fas fa-user-edit text-primary"></i> Editar Contato
                        </a>
                        <a class="dropdown-item btn-mesclar-chat" href="#"
                           data-chat-id="{{ $conversa->chat?->id }}"
                           data-nome="{{ $conversa->chat?->chat_name }}">
                            <i class="fas fa-compress-arrows-alt text-warning"></i> Mesclar Chat
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item btn-marcar-lido" href="#" data-id="{{ $conversa->id }}">
                            <i class="fas fa-check-double text-primary"></i> Marcar como lido
                        </a>
                        <a class="dropdown-item btn-devolver" href="#" data-id="{{ $conversa->id }}">
                            <i class="fas fa-undo text-warning"></i> Devolver p/ Fila
                        </a>
                        <a class="dropdown-item btn-finalizar" href="#" data-id="{{ $conversa->id }}">
                            <i class="fas fa-check text-success"></i> Finalizar
                        </a>
                        <a class="dropdown-item" href="{{ route('admin.conversas.show', $conversa) }}">
                            <i class="fas fa-info-circle text-info"></i> Detalhes
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Messages --}}
        <div class="chat-messages" id="messages-{{ $conversa->id }}">
            @if($conversa->chat)
                @php
                    $lastDate = null;
                    // Inverte para ordem cronológica (antigas primeiro, novas no final)
                    $mensagens = $conversa->chat->messages->reverse();
                @endphp
                @foreach($mensagens as $msg)
                @php $currentDate = $msg->message_date; @endphp
                @if($currentDate !== $lastDate)
                    <div class="message-date-separator">
                        <span>{{ $currentDate }}</span>
                    </div>
                    @php $lastDate = $currentDate; @endphp
                @endif
                <div class="message {{ $msg->is_from_me ? 'sent' : 'received' }}"
                     data-msg-id="{{ $msg->id }}"
                     data-message-key="{{ $msg->message_key }}"
                     data-message-text="{{ $msg->message_text }}">
                    <div class="message-bubble">
                        @if(!$msg->is_from_me && $isGroup && $msg->sender_name)
                            <div class="message-sender">{{ $msg->sender_name }}</div>
                        @endif

                        @if($msg->quoted_text)
                            <div class="message-quoted">{{ Str::limit($msg->quoted_text, 100) }}</div>
                        @endif

                        @if($msg->is_deleted)
                            <div class="message-text message-deleted">
                                <i class="fas fa-ban"></i> Mensagem apagada
                            </div>
                        @elseif($msg->message_type === 'image')
                            @if($msg->media_url)
                                <img src="{{ $msg->media_url }}" class="message-media-img" alt="Imagem" onclick="openImageModal(this.src)">
                            @else
                                <div class="message-text"><i class="fas fa-image"></i> Imagem</div>
                            @endif
                            @if($msg->message_text)
                                <div class="message-text">{!! WhatsAppFormatter::format($msg->message_text) !!}</div>
                            @endif
                        @elseif($msg->message_type === 'video')
                            @if($msg->media_url)
                                <video class="message-media-video" controls>
                                    <source src="{{ $msg->media_url }}" type="{{ $msg->media_mime_type ?? 'video/mp4' }}">
                                </video>
                            @else
                                <div class="message-text"><i class="fas fa-video"></i> Video</div>
                            @endif
                            @if($msg->message_text)
                                <div class="message-text">{!! WhatsAppFormatter::format($msg->message_text) !!}</div>
                            @endif
                        @elseif($msg->message_type === 'audio')
                            @if($msg->media_url)
                                <div class="audio-container">
                                    <audio class="message-media-audio" controls>
                                        <source src="{{ $msg->media_url }}" type="{{ $msg->media_mime_type ?? 'audio/ogg' }}">
                                    </audio>
                                    @if($msg->media_duration)
                                        <span class="audio-duration">{{ floor($msg->media_duration / 60) }}:{{ str_pad($msg->media_duration % 60, 2, '0', STR_PAD_LEFT) }}</span>
                                    @endif
                                </div>
                            @else
                                <div class="message-text">
                                    <i class="fas fa-microphone"></i> Audio
                                    @if($msg->media_duration)
                                        <span class="audio-duration">{{ floor($msg->media_duration / 60) }}:{{ str_pad($msg->media_duration % 60, 2, '0', STR_PAD_LEFT) }}</span>
                                    @endif
                                </div>
                            @endif
                        @elseif($msg->message_type === 'document')
                            <a href="{{ $msg->media_url }}" target="_blank" class="message-document" download>
                                <i class="fas fa-file-alt"></i>
                                <div class="doc-info">
                                    <div class="doc-name">{{ $msg->media_filename ?? $msg->message_text ?? 'Documento' }}</div>
                                </div>
                                <i class="fas fa-download doc-download"></i>
                            </a>
                        @elseif($msg->message_type === 'sticker')
                            @if($msg->media_url)
                                <img src="{{ $msg->media_url }}" class="message-media-img" alt="Sticker" style="max-width: 150px;">
                            @else
                                <div class="message-text"><i class="fas fa-sticky-note"></i> Sticker</div>
                            @endif
                        @else
                            <div class="message-text">{!! WhatsAppFormatter::format($msg->message_text) !!}</div>
                        @endif

                        @if($msg->reactions && count($msg->reactions) > 0)
                            <div class="message-reactions">
                                @foreach($msg->reactions as $reaction)
                                    <span class="reaction">{{ $reaction['emoji'] }}</span>
                                @endforeach
                            </div>
                        @endif

                        <div class="message-time">
                            @if($msg->is_edited)
                                <span class="message-edited">editado</span>
                            @endif
                            {{ $msg->message_time }}
                            @if($msg->is_from_me)
                                <i class="fas fa-check-double check"></i>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            @endif
        </div>

        {{-- Input --}}
        <div class="chat-input">
            <div class="chat-input-reply" id="reply-{{ $conversa->id }}">
                <i class="fas fa-reply"></i>
                <span class="reply-text"></span>
                <i class="fas fa-times reply-close" onclick="cancelReply({{ $conversa->id }})"></i>
            </div>

            <form class="form-enviar" data-conversa-id="{{ $conversa->id }}">
                @csrf
                <input type="hidden" name="quoted_message_id" id="quoted-{{ $conversa->id }}" value="">

                <div class="input-icons" style="position: relative;">
                    <div class="attachment-menu" id="attach-menu-{{ $conversa->id }}">
                        <div class="attachment-item img" onclick="triggerFileInput({{ $conversa->id }}, 'imagem')">
                            <i class="fas fa-image"></i>
                            <span>Imagem</span>
                        </div>
                        <div class="attachment-item video" onclick="triggerFileInput({{ $conversa->id }}, 'video')">
                            <i class="fas fa-video"></i>
                            <span>Video</span>
                        </div>
                        <div class="attachment-item doc" onclick="triggerFileInput({{ $conversa->id }}, 'documento')">
                            <i class="fas fa-file-alt"></i>
                            <span>Documento</span>
                        </div>
                        <div class="attachment-item audio" onclick="triggerFileInput({{ $conversa->id }}, 'audio')">
                            <i class="fas fa-music"></i>
                            <span>Audio</span>
                        </div>
                    </div>
                    <i class="fas fa-paperclip icon-btn btn-attach" onclick="toggleAttachMenu({{ $conversa->id }})" title="Anexar"></i>
                    <i class="far fa-smile icon-btn btn-emoji" title="Emoji"></i>
                </div>

                <input type="text" name="mensagem" placeholder="Digite uma mensagem..." autocomplete="off">

                <button type="submit" class="btn btn-success btn-send">
                    <i class="fas fa-paper-plane"></i>
                </button>
                <button type="button" class="btn-mic" onclick="toggleRecording(this, {{ $conversa->id }})" title="Gravar audio">
                    <i class="fas fa-microphone"></i>
                </button>
            </form>

            {{-- Hidden file inputs --}}
            <input type="file" id="file-imagem-{{ $conversa->id }}" accept="image/*" style="display:none" onchange="uploadFile({{ $conversa->id }}, 'imagem', this)">
            <input type="file" id="file-video-{{ $conversa->id }}" accept="video/*" style="display:none" onchange="uploadFile({{ $conversa->id }}, 'video', this)">
            <input type="file" id="file-documento-{{ $conversa->id }}" accept="*/*" style="display:none" onchange="uploadFile({{ $conversa->id }}, 'documento', this)">
            <input type="file" id="file-audio-{{ $conversa->id }}" accept="audio/*" style="display:none" onchange="uploadFile({{ $conversa->id }}, 'audio', this)">
        </div>
    </div>
    @endforeach

    {{-- Slots disponiveis --}}
    @for($i = 0; $i < $slotsDisponiveis; $i++)
    <div class="slot-disponivel">
        <i class="fas fa-comment-slash"></i>
        <span>Slot disponivel</span>
    </div>
    @endfor
</div>

{{-- Context Menu --}}
<div class="message-context-menu" id="contextMenu">
    <div class="menu-item" onclick="replyToMessage()"><i class="fas fa-reply"></i> Responder</div>
    <div class="menu-item" onclick="showEmojiPicker()"><i class="far fa-smile"></i> Reagir</div>
    <div class="menu-item" onclick="copyMessageText()"><i class="fas fa-copy"></i> Copiar</div>
    <div class="menu-item" onclick="forwardMessage()"><i class="fas fa-share"></i> Encaminhar</div>
    <div class="menu-item menu-edit" onclick="editMessage()"><i class="fas fa-edit"></i> Editar</div>
    <div class="menu-item menu-delete" onclick="deleteMessage()"><i class="fas fa-trash"></i> Apagar</div>
</div>

{{-- Forward Modal --}}
<div class="modal fade" id="forwardModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Encaminhar para</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="list-group" id="forwardTargets">
                    @foreach($conversas as $conv)
                    <a href="#" class="list-group-item list-group-item-action forward-target"
                       data-conversa-id="{{ $conv->id }}" data-nome="{{ $conv->cliente_nome }}">
                        <i class="fas fa-user"></i> {{ $conv->cliente_nome }}
                    </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Edit Contact Modal --}}
<div class="modal fade" id="editContactModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-edit"></i> Editar Contato</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editContactChatId">
                <input type="hidden" id="editContactConversaId">

                <div class="form-group">
                    <label>Nome do Contato</label>
                    <input type="text" class="form-control" id="editContactNome" placeholder="Nome">
                </div>

                <div class="form-group">
                    <label>Numero Real (WhatsApp)</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="editContactNumero" placeholder="Ex: 5544999999999">
                        <div class="input-group-append">
                            <span class="input-group-text">@s.whatsapp.net</span>
                        </div>
                    </div>
                    <small class="text-muted">Informe apenas os numeros (codigo pais + DDD + numero)</small>
                </div>

                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>JID Atual:</strong> <code id="editContactJidAtual"></code>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnSalvarContato">
                    <i class="fas fa-save"></i> Salvar
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Merge Chat Modal --}}
<div class="modal fade" id="mergeChatModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-compress-arrows-alt"></i> Mesclar Chat</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="mergeChatId">

                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Mesclando: <strong id="mergeNomeAtual"></strong>
                </div>

                <p class="text-muted">
                    Busque o chat principal para onde as mensagens serao movidas.
                    O chat atual sera removido apos a mesclagem.
                </p>

                <div class="form-group">
                    <label>Buscar chat por nome ou numero</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="mergeTargetJid" placeholder="Digite nome ou numero...">
                        <div class="input-group-append">
                            <button class="btn btn-outline-primary" type="button" id="btnBuscarMerge">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="list-group" id="mergeSearchResults" style="max-height: 200px; overflow-y: auto;">
                    <!-- Resultados da busca -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
            </div>
        </div>
    </div>
</div>

{{-- Nova Conversa Modal --}}
<div class="modal fade" id="novaConversaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Nova Conversa</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Instancia</label>
                    <select class="form-control" id="novaConversaInstancia">
                        @foreach(\App\Models\WhatsappAccount::where('empresa_id', Auth::user()->empresa_id)->get() as $acc)
                            <option value="{{ $acc->id }}" data-session="{{ $acc->session_name }}">{{ $acc->session_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Numero do WhatsApp</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="novaConversaNumero" placeholder="Ex: 5544999999999">
                        <div class="input-group-append">
                            <span class="input-group-text">@s.whatsapp.net</span>
                        </div>
                    </div>
                    <small class="text-muted">Codigo do pais + DDD + numero (sem espacos ou tracos)</small>
                </div>

                <div class="form-group">
                    <label>Nome do Contato (opcional)</label>
                    <input type="text" class="form-control" id="novaConversaNome" placeholder="Nome para identificar">
                </div>

                <hr>

                <div class="form-group">
                    <label>Mensagem Inicial</label>
                    <textarea class="form-control" id="novaConversaMensagem" rows="3" placeholder="Digite a primeira mensagem..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="btnIniciarConversa">
                    <i class="fas fa-paper-plane"></i> Iniciar Conversa
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Emoji Picker for Reactions --}}
<div class="emoji-picker" id="reactionPicker" style="display:none; position:fixed; z-index:99999; background:#fff; padding:15px; border-radius:10px; box-shadow:0 4px 20px rgba(0,0,0,0.3);">
    <div class="emoji-grid" style="display:flex; gap:8px; flex-wrap:wrap;">
        @foreach(['👍','❤️','😂','😮','😢','🙏','👏','🔥','🎉','💯'] as $emoji)
            <span class="emoji" onclick="sendReaction('{{ $emoji }}')" style="font-size:1.5rem; cursor:pointer; padding:5px;">{{ $emoji }}</span>
        @endforeach
    </div>
</div>

{{-- Image Modal --}}
<div class="image-modal" id="imageModal" onclick="closeImageModal()">
    <div class="image-modal-toolbar" onclick="event.stopPropagation()">
        <a href="#" id="downloadImageBtn" download class="btn btn-light btn-sm">
            <i class="fas fa-download"></i> Download
        </a>
        <button class="btn btn-light btn-sm" onclick="closeImageModal()">
            <i class="fas fa-times"></i> Fechar
        </button>
    </div>
    <img src="" id="modalImage" onclick="event.stopPropagation()">
</div>

{{-- Paste Preview Modal --}}
<div class="paste-preview-modal" id="pastePreviewModal">
    <div class="paste-preview-content">
        <h5>Enviar imagem colada?</h5>
        <img src="" id="pastePreviewImage">
        <input type="text" class="form-control mb-3" id="pasteCaption" placeholder="Legenda (opcional)">
        <div class="d-flex gap-2 justify-content-center">
            <button class="btn btn-secondary" onclick="cancelPaste()">Cancelar</button>
            <button class="btn btn-success" onclick="confirmPaste()">Enviar</button>
        </div>
    </div>
</div>

{{-- Sidebar da Fila --}}
<div class="fila-sidebar-overlay" id="filaSidebarOverlay"></div>
<div class="fila-sidebar" id="filaSidebar">
    <div class="fila-sidebar-header">
        <h5>
            <i class="fas fa-users"></i> Fila de Espera
            <span class="badge badge-light" id="filaSidebarCount">0</span>
        </h5>
        <button class="close-btn" id="closeFilaSidebar">&times;</button>
    </div>
    <div class="fila-sidebar-body" id="filaSidebarBody">
        <div class="fila-sidebar-loading">
            <i class="fas fa-spinner fa-spin fa-2x"></i>
            <p class="mt-2">Carregando...</p>
        </div>
    </div>
</div>
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let currentMessageKey = null;
let currentConversaId = null;
let currentMessageElement = null;
let mediaRecorder = null;
let audioChunks = [];

// Limpar estado de "cliente aguardando" quando enviamos mensagem
function clearWaitingState(conversaId) {
    var column = $('.chat-column[data-conversa-id="' + conversaId + '"]');
    column.removeClass('cliente-aguardando');
    column.removeAttr('data-last-client-msg');
    column.find('.waiting-timer').remove();
}

// Atualizar estado de "cliente aguardando" quando recebemos mensagem do cliente
function setWaitingState(conversaId, timestamp) {
    var column = $('.chat-column[data-conversa-id="' + conversaId + '"]');
    column.addClass('cliente-aguardando');
    column.attr('data-last-client-msg', timestamp);
}

// Toast usando SweetAlert2
function showToast(message, type = 'info', duration = null) {
    const icons = {
        success: 'success',
        error: 'error',
        warning: 'warning',
        info: 'info'
    };

    const defaultDurations = {
        success: 3000,
        error: 5000,
        warning: 4000,
        info: 3000
    };

    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: icons[type] || 'info',
        title: message,
        showConfirmButton: false,
        timer: duration || defaultDurations[type] || 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });
}

// Função para scrollar todas as conversas para o final
function scrollAllChatsToBottom() {
    $('.chat-messages').each(function() {
        this.scrollTop = this.scrollHeight;
    });
}

$(function() {
    // Scroll inicial com delays progressivos para garantir renderização
    scrollAllChatsToBottom();
    setTimeout(scrollAllChatsToBottom, 100);
    setTimeout(scrollAllChatsToBottom, 300);
    setTimeout(scrollAllChatsToBottom, 500);

    // Scroll novamente após imagens carregarem
    $(window).on('load', scrollAllChatsToBottom);

    // Context menu nas mensagens
    $(document).on('contextmenu', '.message-bubble', function(e) {
        e.preventDefault();

        var msg = $(this).closest('.message');
        currentMessageKey = msg.data('message-key');
        currentConversaId = msg.closest('.chat-column').data('conversa-id');
        currentMessageElement = msg;

        var isFromMe = msg.hasClass('sent');

        // Mostrar/esconder opcoes baseado em quem enviou
        $('.menu-edit, .menu-delete').toggle(isFromMe);

        var menu = $('#contextMenu');
        menu.css({
            top: e.pageY + 'px',
            left: e.pageX + 'px'
        }).addClass('show');
    });

    // Fechar menus ao clicar fora
    $(document).on('click', function(e) {
        // Não fechar se clicou no menu de contexto (incluindo "Reagir")
        if ($(e.target).closest('.message-context-menu').length) {
            return;
        }

        $('#contextMenu').removeClass('show');

        if (!$(e.target).closest('.emoji-picker').length && !$(e.target).hasClass('btn-emoji')) {
            $('#reactionPicker').hide();
        }
        if (!$(e.target).closest('.attachment-menu').length && !$(e.target).hasClass('btn-attach')) {
            $('.attachment-menu').removeClass('show');
        }
    });

    // Enviar mensagem via AJAX
    $('.form-enviar').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var conversaId = form.data('conversa-id');
        var input = form.find('input[name="mensagem"]');
        var mensagem = input.val().trim();
        var quotedId = $('#quoted-' + conversaId).val();

        if (!mensagem) return;

        var btn = form.find('.btn-send');
        btn.prop('disabled', true);

        $.ajax({
            url: '/admin/painel/' + conversaId + '/enviar',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                mensagem: mensagem,
                quoted_message_id: quotedId
            },
            success: function(response) {
                if (response.success && response.message) {
                    var container = $('#messages-' + conversaId);
                    container.append(buildMessageHtml(response.message));
                    container[0].scrollTop = container[0].scrollHeight;
                    // Limpar estado de aguardando após enviar mensagem
                    clearWaitingState(conversaId);
                }
                input.val('');
                cancelReply(conversaId);
            },
            error: function(xhr) {
                showToast(xhr.responseJSON?.error || 'Erro ao enviar mensagem', 'error');
            },
            complete: function() {
                btn.prop('disabled', false);
                input.focus();
            }
        });
    });

    // Enviar ao digitar (typing indicator) - com debounce para evitar requisições excessivas
    var typingTimeouts = {};
    var lastTypingSent = {};
    $('.form-enviar input[name="mensagem"]').on('input', function() {
        var conversaId = $(this).closest('form').data('conversa-id');
        var now = Date.now();

        // Só enviar se passou mais de 2 segundos desde o último envio
        if (!lastTypingSent[conversaId] || now - lastTypingSent[conversaId] > 2000) {
            lastTypingSent[conversaId] = now;
            $.post('/admin/painel/' + conversaId + '/digitando', { _token: '{{ csrf_token() }}' });
        }

        clearTimeout(typingTimeouts[conversaId]);
        typingTimeouts[conversaId] = setTimeout(function() {
            lastTypingSent[conversaId] = 0;
        }, 3000);
    });

    // Finalizar conversa
    $('.btn-finalizar').on('click', function(e) {
        e.preventDefault();
        var conversaId = $(this).data('id');

        Swal.fire({
            title: 'Finalizar Conversa',
            text: 'Deseja realmente finalizar esta conversa?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sim, finalizar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '/admin/painel/' + conversaId + '/finalizar',
                    method: 'POST',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function() {
                        Swal.fire({
                            title: 'Finalizado!',
                            text: 'Conversa finalizada com sucesso.',
                            icon: 'success',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    },
                    error: function() {
                        showToast('Erro ao finalizar conversa', 'error');
                    }
                });
            }
        });
    });

    // Devolver para fila
    $('.btn-devolver').on('click', function(e) {
        e.preventDefault();
        var conversaId = $(this).data('id');

        Swal.fire({
            title: 'Devolver para Fila',
            text: 'Deseja devolver esta conversa para a fila de espera?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sim, devolver',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '/admin/painel/' + conversaId + '/devolver',
                    method: 'POST',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function() {
                        Swal.fire({
                            title: 'Devolvido!',
                            text: 'Conversa devolvida para a fila.',
                            icon: 'success',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    },
                    error: function() {
                        showToast('Erro ao devolver conversa', 'error');
                    }
                });
            }
        });
    });

    // Marcar como lido
    $('.btn-marcar-lido').on('click', function(e) {
        e.preventDefault();
        var conversaId = $(this).data('id');

        $.post('/admin/painel/' + conversaId + '/marcar-lido', { _token: '{{ csrf_token() }}' })
            .done(function() {
                // Limpar estado de aguardando ao marcar como lido
                clearWaitingState(conversaId);
                showToast('Marcado como lido', 'success');
            });
    });

    // Finalizar todas
    $('#btnFinalizarTodas').on('click', function() {
        var total = $('.chat-column').length;

        Swal.fire({
            title: 'Finalizar Todas',
            html: 'Deseja finalizar <strong>' + total + ' conversas</strong>?<br><small class="text-muted">Esta acao nao pode ser desfeita.</small>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sim, finalizar todas',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                var conversas = $('.chat-column');
                var done = 0;

                Swal.fire({
                    title: 'Finalizando...',
                    html: 'Aguarde enquanto as conversas sao finalizadas.',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                conversas.each(function() {
                    var id = $(this).data('conversa-id');
                    $.ajax({
                        url: '/admin/painel/' + id + '/finalizar',
                        method: 'POST',
                        data: { _token: '{{ csrf_token() }}' },
                        complete: function() {
                            done++;
                            if (done >= total) {
                                location.reload();
                            }
                        }
                    });
                });
            }
        });
    });

    // Refresh
    $('#btnRefresh').on('click', function() {
        location.reload();
    });

    // Refresh individual chat
    $('.btn-refresh-chat').on('click', function() {
        refreshChat($(this).closest('.chat-column').data('conversa-id'));
    });

    // Sincronizar histórico
    $('.btn-sincronizar-historico').on('click', function(e) {
        e.preventDefault();
        var conversaId = $(this).data('id');
        var btn = $(this);

        Swal.fire({
            title: 'Buscar Historico',
            html: `
                <p>Quantas mensagens deseja buscar?</p>
                <input type="number" id="swal-limit" class="swal2-input" value="500" min="50" max="5000" placeholder="Quantidade">
                <p style="font-size:0.75em;color:#999;margin-top:5px;">Quanto maior, mais mensagens antigas serao importadas</p>
                <p style="margin-top:15px;font-size:0.9em;color:#666;">Numero real do WhatsApp (opcional):</p>
                <input type="text" id="swal-numero" class="swal2-input" placeholder="Ex: 5544999887766">
                <p style="font-size:0.75em;color:#999;">Use se o contato usa LID em vez do numero</p>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Buscar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#25d366',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                var limit = document.getElementById('swal-limit').value || 100;
                var numero = document.getElementById('swal-numero').value || '';
                return $.ajax({
                    url: '/admin/painel/' + conversaId + '/sincronizar-historico',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        limit: limit,
                        numero_real: numero
                    }
                }).then(response => response)
                .catch(error => {
                    Swal.showValidationMessage(error.responseJSON?.error || 'Erro ao sincronizar');
                });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                var data = result.value;

                // Verificar se precisa importação manual
                if (data.needs_import && data.import_instructions) {
                    var instructions = data.import_instructions;
                    var stepsHtml = instructions.steps.map(s => `<li>${s}</li>`).join('');

                    // Mostrar tentativas
                    var attemptsHtml = '';
                    if (data.attempts && data.attempts.length > 0) {
                        attemptsHtml = '<div style="background:#f8f9fa;padding:10px;border-radius:5px;margin-bottom:15px;"><p style="font-size:0.85em;text-align:left;margin:0;"><strong>O que foi tentado:</strong></p><ul style="text-align:left;font-size:0.8em;margin:5px 0 0 0;padding-left:20px;">';
                        data.attempts.forEach(function(a) {
                            var icon = a.status === 'success' ? '✅' : (a.status === 'failed' ? '❌' : (a.status === 'skipped' ? '⏭️' : '⏳'));
                            attemptsHtml += `<li>${icon} <strong>${a.source.toUpperCase()}</strong>: ${a.detail || a.status}</li>`;
                        });
                        attemptsHtml += '</ul></div>';
                    }

                    Swal.fire({
                        title: instructions.title,
                        html: `
                            <p style="color:#856404;margin-bottom:15px;">
                                ${data.message}
                            </p>
                            ${attemptsHtml}
                            ${data.imported > 0 ? `<p><strong>Importadas parcialmente:</strong> ${data.imported} mensagens</p>` : ''}
                            <hr>
                            <p><strong>Para importar o historico completo:</strong></p>
                            <ol style="text-align:left;padding-left:20px;font-size:0.9em;">
                                ${stepsHtml}
                            </ol>
                            <p style="margin-top:15px;">
                                <strong>Numero do contato:</strong> ${instructions.phone}
                            </p>
                        `,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Ir para Importacao',
                        cancelButtonText: 'Fechar',
                        confirmButtonColor: '#007bff',
                    }).then((importResult) => {
                        if (importResult.isConfirmed) {
                            window.open(instructions.import_url, '_blank');
                        }
                    });

                    if (data.imported > 0) {
                        refreshChat(conversaId);
                    }
                } else if (data.status === 'success') {
                    // Mostrar resultado com tentativas
                    var attemptsHtml = '';
                    if (data.attempts && data.attempts.length > 0) {
                        attemptsHtml = '<hr><p style="font-size:0.85em;text-align:left;"><strong>Tentativas:</strong></p><ul style="text-align:left;font-size:0.8em;margin:0;padding-left:20px;">';
                        data.attempts.forEach(function(a) {
                            var icon = a.status === 'success' ? '✅' : (a.status === 'failed' ? '❌' : (a.status === 'skipped' ? '⏭️' : '⏳'));
                            attemptsHtml += `<li>${icon} <strong>${a.source.toUpperCase()}</strong>: ${a.detail || a.status}</li>`;
                        });
                        attemptsHtml += '</ul>';
                    }

                    Swal.fire({
                        title: 'Sincronização Concluída',
                        html: `
                            <p><strong>${data.imported}</strong> mensagens importadas via <strong>${data.source.toUpperCase()}</strong></p>
                            <p style="color:#666;font-size:0.9em;">${data.skipped} ja existiam no banco</p>
                            ${attemptsHtml}
                        `,
                        icon: 'success',
                        confirmButtonText: 'OK'
                    });
                    refreshChat(conversaId);
                } else {
                    // Mostrar erro com tentativas
                    var attemptsHtml = '';
                    if (data.attempts && data.attempts.length > 0) {
                        attemptsHtml = '<hr><p style="font-size:0.85em;text-align:left;"><strong>Tentativas:</strong></p><ul style="text-align:left;font-size:0.8em;margin:0;padding-left:20px;">';
                        data.attempts.forEach(function(a) {
                            var icon = a.status === 'success' ? '✅' : (a.status === 'failed' ? '❌' : (a.status === 'skipped' ? '⏭️' : '⏳'));
                            attemptsHtml += `<li>${icon} <strong>${a.source.toUpperCase()}</strong>: ${a.detail || a.status}</li>`;
                        });
                        attemptsHtml += '</ul>';
                    }

                    Swal.fire({
                        title: 'Erro na Sincronização',
                        html: `<p>${data.message || 'Erro desconhecido'}</p>${attemptsHtml}`,
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            }
        });
    });

    // Baixar mídias pendentes
    $('.btn-baixar-midias').on('click', function(e) {
        e.preventDefault();
        var conversaId = $(this).data('id');

        Swal.fire({
            title: 'Baixar Midias',
            text: 'Isso vai baixar imagens, audios e videos que ainda nao foram baixados.',
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Baixar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#17a2b8',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return $.ajax({
                    url: '/admin/painel/' + conversaId + '/baixar-midias',
                    method: 'POST',
                    data: { _token: '{{ csrf_token() }}' }
                }).then(response => response)
                .catch(error => {
                    Swal.showValidationMessage(error.responseJSON?.error || 'Erro ao baixar');
                });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                var msg = `Baixadas ${result.value.downloaded} midias`;
                if (result.value.failed > 0) {
                    msg += ` (${result.value.failed} falharam)`;
                }
                if (result.value.pending > 0) {
                    msg += `. Ainda restam ${result.value.pending} pendentes.`;
                }
                showToast(msg, result.value.downloaded > 0 ? 'success' : 'warning');
                refreshChat(conversaId);
            }
        });
    });

    // Auto-refresh every 5 seconds - buscar mensagens novas
    setInterval(function() {
        $('.chat-column').each(function() {
            var conversaId = $(this).data('conversa-id');
            var container = $('#messages-' + conversaId);
            var isGroup = $(this).data('is-group') === 1;

            // Pegar o ID da última mensagem (id do banco, não message_key)
            var lastMsg = container.find('.message').last();
            var lastMsgId = lastMsg.data('msg-id') || 0;

            $.ajax({
                url: '/admin/painel/' + conversaId + '/mensagens',
                method: 'GET',
                data: lastMsgId ? { after_id: lastMsgId } : {},
                success: function(response) {
                    if (!response.messages || response.messages.length === 0) return;

                    // Se não temos lastMsgId, é carregamento inicial - não faz nada
                    if (!lastMsgId) return;

                    var lastDate = container.find('.message-date-separator').last().find('span').text() || null;

                    // Adicionar apenas as mensagens novas
                    var hasNewClientMessage = false;
                    var lastClientTimestamp = null;
                    response.messages.forEach(function(msg) {
                        // Verificar se já não existe no container
                        if (container.find('[data-msg-id="' + msg.id + '"]').length === 0) {
                            if (msg.message_date && msg.message_date !== lastDate) {
                                container.append('<div class="message-date-separator"><span>' + msg.message_date + '</span></div>');
                                lastDate = msg.message_date;
                            }
                            container.append(buildMessageHtml(msg, isGroup));

                            // Verificar se é mensagem do cliente
                            if (!msg.is_from_me) {
                                hasNewClientMessage = true;
                                lastClientTimestamp = msg.timestamp;
                            }
                        }
                    });
                    container[0].scrollTop = container[0].scrollHeight;

                    // Atualizar estado de aguardando se recebeu mensagem do cliente
                    if (hasNewClientMessage && lastClientTimestamp) {
                        setWaitingState(conversaId, lastClientTimestamp);
                    }
                }
            });
        });
    }, 5000);
});

function refreshChat(conversaId) {
    var container = $('#messages-' + conversaId);
    var isGroup = container.closest('.chat-column').data('is-group') === 1;

    $.ajax({
        url: '/admin/painel/' + conversaId + '/mensagens',
        method: 'GET',
        success: function(response) {
            container.empty();
            var lastDate = null;
            response.messages.forEach(function(msg) {
                // Adicionar separador de data se mudou
                if (msg.message_date && msg.message_date !== lastDate) {
                    container.append('<div class="message-date-separator"><span>' + msg.message_date + '</span></div>');
                    lastDate = msg.message_date;
                }
                container.append(buildMessageHtml(msg, isGroup));
            });
            container[0].scrollTop = container[0].scrollHeight;
        }
    });
}

function buildMessageHtml(msg, isGroup) {
    var typeClass = msg.is_from_me ? 'sent' : 'received';
    var content = '';

    // Sender name for groups
    var senderHtml = '';
    if (!msg.is_from_me && isGroup && msg.sender_name) {
        senderHtml = '<div class="message-sender">' + escapeHtml(msg.sender_name) + '</div>';
    }

    // Quoted message
    var quotedHtml = '';
    if (msg.quoted_text) {
        quotedHtml = '<div class="message-quoted">' + escapeHtml(msg.quoted_text).substring(0, 100) + '</div>';
    }

    // Content based on type
    if (msg.is_deleted) {
        content = '<div class="message-text message-deleted"><i class="fas fa-ban"></i> Mensagem apagada</div>';
    } else if (msg.message_type === 'image') {
        content = msg.media_url
            ? '<img src="' + msg.media_url + '" class="message-media-img" onclick="openImageModal(this.src)">'
            : '<div class="message-text"><i class="fas fa-image"></i> Imagem</div>';
        if (msg.message_text) {
            content += '<div class="message-text">' + formatWhatsApp(msg.message_text) + '</div>';
        }
    } else if (msg.message_type === 'video') {
        content = msg.media_url
            ? '<video class="message-media-video" controls><source src="' + msg.media_url + '"></video>'
            : '<div class="message-text"><i class="fas fa-video"></i> Video</div>';
    } else if (msg.message_type === 'audio') {
        var durationStr = '';
        if (msg.media_duration) {
            var mins = Math.floor(msg.media_duration / 60);
            var secs = (msg.media_duration % 60).toString().padStart(2, '0');
            durationStr = '<span class="audio-duration">' + mins + ':' + secs + '</span>';
        }
        content = msg.media_url
            ? '<div class="audio-container"><audio class="message-media-audio" controls><source src="' + msg.media_url + '"></audio>' + durationStr + '</div>'
            : '<div class="message-text"><i class="fas fa-microphone"></i> Audio' + durationStr + '</div>';
    } else if (msg.message_type === 'document') {
        var docName = msg.media_filename || msg.message_text || 'Documento';
        content = '<a href="' + (msg.media_url || '#') + '" target="_blank" class="message-document" download>' +
            '<i class="fas fa-file-alt"></i>' +
            '<div class="doc-info"><div class="doc-name">' + escapeHtml(docName) + '</div></div>' +
            '<i class="fas fa-download doc-download"></i></a>';
    } else {
        content = '<div class="message-text">' + formatWhatsApp(msg.message_text || '') + '</div>';
    }

    // Reactions
    var reactionsHtml = '';
    if (msg.reactions && msg.reactions.length > 0) {
        reactionsHtml = '<div class="message-reactions">';
        msg.reactions.forEach(function(r) {
            reactionsHtml += '<span class="reaction">' + r.emoji + '</span>';
        });
        reactionsHtml += '</div>';
    }

    var editedHtml = msg.is_edited ? '<span class="message-edited">editado</span>' : '';
    var checkHtml = msg.is_from_me ? '<i class="fas fa-check-double check"></i>' : '';

    return '<div class="message ' + typeClass + '" data-msg-id="' + msg.id + '" data-message-key="' + msg.message_key + '" data-message-text="' + escapeHtml(msg.message_text || '') + '">' +
        '<div class="message-bubble">' +
        senderHtml +
        quotedHtml +
        content +
        reactionsHtml +
        '<div class="message-time">' + editedHtml + msg.created_at + ' ' + checkHtml + '</div>' +
        '</div></div>';
}

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Formatar texto WhatsApp (negrito, itálico, etc)
function formatWhatsApp(text) {
    if (!text) return '';
    var html = escapeHtml(text);
    // Bloco de código
    html = html.replace(/```([\s\S]*?)```/g, '<pre class="wa-code-block">$1</pre>');
    // Código inline
    html = html.replace(/`([^`]+)`/g, '<code class="wa-code">$1</code>');
    // Negrito
    html = html.replace(/\*([^\*]+)\*/g, '<strong>$1</strong>');
    // Itálico
    html = html.replace(/\_([^\_]+)\_/g, '<em>$1</em>');
    // Tachado
    html = html.replace(/\~([^\~]+)\~/g, '<s>$1</s>');
    // Quebras de linha
    html = html.replace(/\n/g, '<br>');
    return html;
}

// Context menu actions
function replyToMessage() {
    $('#contextMenu').removeClass('show');
    var text = currentMessageElement.data('message-text') || '[Midia]';

    $('#reply-' + currentConversaId).addClass('show');
    $('#reply-' + currentConversaId + ' .reply-text').text(text.substring(0, 50));
    $('#quoted-' + currentConversaId).val(currentMessageKey);

    $('[data-conversa-id="' + currentConversaId + '"] input[name="mensagem"]').focus();
}

function cancelReply(conversaId) {
    $('#reply-' + conversaId).removeClass('show');
    $('#quoted-' + conversaId).val('');
}

function showEmojiPicker() {
    $('#contextMenu').removeClass('show');

    if (!currentMessageElement) {
        return;
    }

    var picker = $('#reactionPicker');
    if (picker.length === 0) {
        return;
    }

    // Usar posição relativa à janela (viewport) para position:fixed
    var rect = currentMessageElement[0].getBoundingClientRect();
    var pickerHeight = 80;
    var pickerWidth = 320;

    // Calcular posição - acima da mensagem se possível
    var top = rect.top - pickerHeight - 10;
    var left = rect.left;

    // Se ficar fora da tela (acima), colocar abaixo
    if (top < 10) {
        top = rect.bottom + 10;
    }

    // Se ficar fora da tela (abaixo), ajustar para caber
    if (top + pickerHeight > window.innerHeight - 10) {
        top = window.innerHeight - pickerHeight - 10;
    }

    // Se ficar fora da tela (direita), ajustar
    if (left + pickerWidth > window.innerWidth) {
        left = window.innerWidth - pickerWidth - 10;
    }

    // Garantir que não fique fora da tela (esquerda)
    if (left < 10) {
        left = 10;
    }

    // Forçar exibição com display:block
    picker.css({
        top: top + 'px',
        left: left + 'px',
        display: 'block'
    });
}

function sendReaction(emoji) {
    $('#reactionPicker').hide();

    $.post('/admin/painel/' + currentConversaId + '/reagir', {
        _token: '{{ csrf_token() }}',
        message_key: currentMessageKey,
        emoji: emoji
    }).done(function() {
        refreshChat(currentConversaId);
    }).fail(function(xhr) {
        alert('Erro ao enviar reação: ' + (xhr.responseJSON?.error || 'Erro desconhecido'));
    });
}

function editMessage() {
    $('#contextMenu').removeClass('show');
    var currentText = currentMessageElement.data('message-text');
    var newText = prompt('Editar mensagem:', currentText);

    if (newText && newText !== currentText) {
        $.post('/admin/painel/' + currentConversaId + '/editar', {
            _token: '{{ csrf_token() }}',
            message_key: currentMessageKey,
            new_text: newText
        }).done(function(response) {
            if (response.success) {
                refreshChat(currentConversaId);
            }
        }).fail(function(xhr) {
            showToast(xhr.responseJSON?.error || 'Erro ao editar mensagem', 'error');
        });
    }
}

function deleteMessage() {
    $('#contextMenu').removeClass('show');

    Swal.fire({
        title: 'Apagar Mensagem',
        text: 'Deseja apagar esta mensagem para todos?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sim, apagar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('/admin/painel/' + currentConversaId + '/deletar', {
                _token: '{{ csrf_token() }}',
                message_key: currentMessageKey
            }).done(function(response) {
                if (response.success) {
                    showToast('Mensagem apagada', 'success');
                    refreshChat(currentConversaId);
                }
            }).fail(function(xhr) {
                showToast(xhr.responseJSON?.error || 'Erro ao apagar mensagem', 'error');
            });
        }
    });
}

// Attachment menu
function toggleAttachMenu(conversaId) {
    $('.attachment-menu').removeClass('show');
    $('#attach-menu-' + conversaId).toggleClass('show');
}

function triggerFileInput(conversaId, type) {
    $('.attachment-menu').removeClass('show');
    $('#file-' + type + '-' + conversaId).click();
}

function uploadFile(conversaId, type, input) {
    if (!input.files || !input.files[0]) return;

    var file = input.files[0];
    var formData = new FormData();
    formData.append('_token', '{{ csrf_token() }}');
    formData.append(type === 'imagem' ? 'imagem' : type === 'video' ? 'video' : type === 'documento' ? 'documento' : 'audio', file);

    var endpoint = type === 'imagem' ? 'enviar-imagem' :
                   type === 'video' ? 'enviar-video' :
                   type === 'documento' ? 'enviar-documento' : 'enviar-audio';

    $.ajax({
        url: '/admin/painel/' + conversaId + '/' + endpoint,
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success && response.message) {
                var container = $('#messages-' + conversaId);
                container.append(buildMessageHtml(response.message));
                container[0].scrollTop = container[0].scrollHeight;
                clearWaitingState(conversaId);
            }
        },
        error: function(xhr) {
            showToast(xhr.responseJSON?.error || 'Erro ao enviar arquivo', 'error');
        }
    });

    input.value = '';
}

// Audio recording
function toggleRecording(btn, conversaId) {
    // Verificar se a API de gravacao esta disponivel (requer HTTPS)
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        showToast('Gravacao de audio nao disponivel. Acesse via HTTPS ou use localhost.', 'warning', 6000);
        return;
    }

    if (mediaRecorder && mediaRecorder.state === 'recording') {
        mediaRecorder.stop();
        $(btn).removeClass('recording');
        $(btn).find('i').removeClass('fa-stop').addClass('fa-microphone');
    } else {
        navigator.mediaDevices.getUserMedia({ audio: true })
            .then(function(stream) {
                audioChunks = [];
                mediaRecorder = new MediaRecorder(stream);

                mediaRecorder.ondataavailable = function(e) {
                    audioChunks.push(e.data);
                };

                mediaRecorder.onstop = function() {
                    var audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                    var formData = new FormData();
                    formData.append('_token', '{{ csrf_token() }}');
                    formData.append('audio', audioBlob, 'audio.webm');

                    $.ajax({
                        url: '/admin/painel/' + conversaId + '/enviar-audio',
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            if (response.success && response.message) {
                                var container = $('#messages-' + conversaId);
                                container.append(buildMessageHtml(response.message));
                                container[0].scrollTop = container[0].scrollHeight;
                                clearWaitingState(conversaId);
                            }
                        },
                        error: function(xhr) {
                            showToast(xhr.responseJSON?.error || 'Erro ao enviar audio', 'error');
                        }
                    });

                    stream.getTracks().forEach(track => track.stop());
                };

                mediaRecorder.start();
                $(btn).addClass('recording');
                $(btn).find('i').removeClass('fa-microphone').addClass('fa-stop');
                showToast('Gravando audio... Clique novamente para parar', 'info', 2000);
            })
            .catch(function(err) {
                showToast('Erro ao acessar microfone: ' + err.message, 'error');
            });
    }
}

// Image modal
function openImageModal(src) {
    $('#modalImage').attr('src', src);
    $('#downloadImageBtn').attr('href', src);
    $('#imageModal').addClass('show');
}

function closeImageModal() {
    $('#imageModal').removeClass('show');
}

// Copiar texto da mensagem
function copyMessageText() {
    $('#contextMenu').removeClass('show');
    var text = currentMessageElement.data('message-text');
    if (text) {
        navigator.clipboard.writeText(text).then(function() {
            showToast('Texto copiado!', 'success');
        }).catch(function() {
            // Fallback para navegadores antigos
            var temp = $('<textarea>').val(text).appendTo('body').select();
            document.execCommand('copy');
            temp.remove();
            showToast('Texto copiado!', 'success');
        });
    }
}

// Encaminhar mensagem
function forwardMessage() {
    $('#contextMenu').removeClass('show');
    $('#forwardModal').modal('show');
}

$(document).on('click', '.forward-target', function(e) {
    e.preventDefault();
    var targetConversaId = $(this).data('conversa-id');
    var targetNome = $(this).data('nome');

    $.post('/admin/painel/' + currentConversaId + '/encaminhar', {
        _token: '{{ csrf_token() }}',
        message_key: currentMessageKey,
        target_conversa_id: targetConversaId
    }).done(function(response) {
        $('#forwardModal').modal('hide');
        showToast('Mensagem encaminhada para ' + targetNome, 'success');
    }).fail(function(xhr) {
        showToast(xhr.responseJSON?.error || 'Erro ao encaminhar', 'error');
    });
});

// Colar imagem (Ctrl+V)
var pasteTargetConversaId = null;
var pasteImageBlob = null;

$(document).on('paste', function(e) {
    var items = (e.originalEvent || e).clipboardData.items;
    for (var i = 0; i < items.length; i++) {
        if (items[i].type.indexOf('image') !== -1) {
            var blob = items[i].getAsFile();
            var reader = new FileReader();

            // Determinar qual conversa está focada
            var focusedInput = $('input[name="mensagem"]:focus');
            if (focusedInput.length) {
                pasteTargetConversaId = focusedInput.closest('.chat-column').data('conversa-id');
            } else {
                // Pegar a primeira conversa ativa
                pasteTargetConversaId = $('.chat-column:first').data('conversa-id');
            }

            reader.onload = function(event) {
                $('#pastePreviewImage').attr('src', event.target.result);
                $('#pasteCaption').val('');
                $('#pastePreviewModal').addClass('show');
            };
            reader.readAsDataURL(blob);
            pasteImageBlob = blob;

            e.preventDefault();
            break;
        }
    }
});

function cancelPaste() {
    $('#pastePreviewModal').removeClass('show');
    pasteImageBlob = null;
}

function confirmPaste() {
    if (!pasteImageBlob || !pasteTargetConversaId) return;

    var caption = $('#pasteCaption').val();
    var formData = new FormData();
    formData.append('_token', '{{ csrf_token() }}');
    formData.append('imagem', pasteImageBlob, 'pasted-image.png');
    if (caption) {
        formData.append('caption', caption);
    }

    $.ajax({
        url: '/admin/painel/' + pasteTargetConversaId + '/enviar-imagem',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success && response.message) {
                var container = $('#messages-' + pasteTargetConversaId);
                container.append(buildMessageHtml(response.message));
                container[0].scrollTop = container[0].scrollHeight;
                clearWaitingState(pasteTargetConversaId);
            }
            cancelPaste();
        },
        error: function(xhr) {
            showToast(xhr.responseJSON?.error || 'Erro ao enviar imagem', 'error');
        }
    });
}

// Formatar tempo de espera de forma legível
function formatWaitingTime(seconds) {
    if (seconds < 60) {
        return seconds + 's';
    } else if (seconds < 3600) {
        // Menos de 1 hora: mostra MM:SS
        var mins = Math.floor(seconds / 60);
        var secs = seconds % 60;
        return mins + ':' + secs.toString().padStart(2, '0');
    } else if (seconds < 86400) {
        // Menos de 24 horas: mostra Xh Xm
        var hours = Math.floor(seconds / 3600);
        var mins = Math.floor((seconds % 3600) / 60);
        return hours + 'h ' + mins + 'm';
    } else {
        // Mais de 24 horas: mostra Xd Xh
        var days = Math.floor(seconds / 86400);
        var hours = Math.floor((seconds % 86400) / 3600);
        return days + 'd ' + hours + 'h';
    }
}

// Timer de espera do cliente
function updateWaitingTimers() {
    $('.chat-column').each(function() {
        var column = $(this);
        // Usar attr() em vez de data() para pegar valores atualizados dinamicamente
        var lastMsgTime = column.attr('data-last-client-msg');
        var timerEl = column.find('.waiting-timer');

        if (lastMsgTime) {
            var now = Math.floor(Date.now() / 1000);
            var diff = now - parseInt(lastMsgTime);

            if (diff > 0) {
                var timeStr = formatWaitingTime(diff);

                if (timerEl.length === 0) {
                    column.find('.chat-column-header').append('<span class="waiting-timer">' + timeStr + '</span>');
                    column.addClass('cliente-aguardando');
                } else {
                    timerEl.text(timeStr);
                }
            }
        } else {
            timerEl.remove();
            column.removeClass('cliente-aguardando');
        }
    });
}

setInterval(updateWaitingTimers, 1000);

// ===== Nova Conversa =====
$('#btnNovaConversa').on('click', function() {
    $('#novaConversaNumero').val('');
    $('#novaConversaNome').val('');
    $('#novaConversaMensagem').val('');
    $('#novaConversaModal').modal('show');
});

$('#btnIniciarConversa').on('click', function() {
    var btn = $(this);
    var accountId = $('#novaConversaInstancia').val();
    var numero = $('#novaConversaNumero').val().trim();
    var nome = $('#novaConversaNome').val().trim();
    var mensagem = $('#novaConversaMensagem').val().trim();

    if (!numero) {
        showToast('Informe o numero do WhatsApp', 'error');
        return;
    }

    if (!mensagem) {
        showToast('Digite uma mensagem para iniciar a conversa', 'error');
        return;
    }

    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Enviando...');

    $.post('/admin/painel/nova-conversa', {
        _token: '{{ csrf_token() }}',
        account_id: accountId,
        numero: numero,
        nome: nome,
        mensagem: mensagem
    }).done(function(response) {
        showToast(response.message || 'Conversa iniciada!', 'success');
        $('#novaConversaModal').modal('hide');
        // Recarregar para mostrar a nova conversa
        setTimeout(function() {
            location.reload();
        }, 1000);
    }).fail(function(xhr) {
        showToast(xhr.responseJSON?.error || 'Erro ao iniciar conversa', 'error');
    }).always(function() {
        btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Iniciar Conversa');
    });
});

// ===== Editar Contato =====
$(document).on('click', '.btn-editar-contato', function(e) {
    e.preventDefault();
    var chatId = $(this).data('chat-id');
    var conversaId = $(this).data('id');
    var nome = $(this).data('nome');
    var jid = $(this).data('jid');
    var numero = $(this).data('numero');

    $('#editContactChatId').val(chatId);
    $('#editContactConversaId').val(conversaId);
    $('#editContactNome').val(nome);
    $('#editContactJidAtual').text(jid);

    // Extrair número do JID
    var numLimpo = jid ? jid.replace(/@.*$/, '') : numero;
    $('#editContactNumero').val(numLimpo);

    $('#editContactModal').modal('show');
});

$('#btnSalvarContato').on('click', function() {
    var btn = $(this);
    var chatId = $('#editContactChatId').val();
    var nome = $('#editContactNome').val().trim();
    var numero = $('#editContactNumero').val().trim();

    if (!nome) {
        showToast('Informe o nome do contato', 'error');
        return;
    }

    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Salvando...');

    $.post('/admin/contatos/atualizar-chat', {
        _token: '{{ csrf_token() }}',
        chat_id: chatId,
        nome: nome,
        numero: numero
    }).done(function(response) {
        showToast(response.message || 'Contato atualizado!', 'success');
        $('#editContactModal').modal('hide');

        // Atualizar header do chat
        var conversaId = $('#editContactConversaId').val();
        var column = $('.chat-column[data-conversa-id="' + conversaId + '"]');

        // Atualizar nome e número
        column.find('.chat-column-header .info .name').contents().first().replaceWith(nome + ' ');
        column.find('.chat-column-header .info .number').text(numero);

        // Atualizar avatar (iniciais)
        var initials = nome.substring(0, 2).toUpperCase();
        column.find('.chat-column-header .avatar').not(':has(i)').text(initials);

        // Atualizar data attributes do botão editar
        column.find('.btn-editar-contato').data('nome', nome).data('numero', numero);
    }).fail(function(xhr) {
        showToast(xhr.responseJSON?.error || 'Erro ao salvar', 'error');
    }).always(function() {
        btn.prop('disabled', false).html('<i class="fas fa-save"></i> Salvar');
    });
});

// ===== Mesclar Chat =====
$(document).on('click', '.btn-mesclar-chat', function(e) {
    e.preventDefault();
    var chatId = $(this).data('chat-id');
    var nome = $(this).data('nome');

    $('#mergeChatId').val(chatId);
    $('#mergeNomeAtual').text(nome);
    $('#mergeTargetJid').val('');
    $('#mergeSearchResults').html('');
    $('#mergeChatModal').modal('show');
});

$('#btnBuscarMerge').on('click', function() {
    var termo = $('#mergeTargetJid').val().trim();
    if (!termo) return;

    $.get('/admin/contatos/buscar-chats', { termo: termo }).done(function(response) {
        var html = '';
        if (response.chats && response.chats.length > 0) {
            response.chats.forEach(function(chat) {
                html += '<a href="#" class="list-group-item list-group-item-action merge-target" data-id="' + chat.id + '">';
                html += '<strong>' + chat.chat_name + '</strong><br>';
                html += '<small class="text-muted">' + chat.chat_id + '</small>';
                html += '</a>';
            });
        } else {
            html = '<div class="text-muted p-3">Nenhum chat encontrado</div>';
        }
        $('#mergeSearchResults').html(html);
    });
});

$(document).on('click', '.merge-target', function(e) {
    e.preventDefault();
    var targetId = $(this).data('id');
    var targetName = $(this).find('strong').text();
    var chatId = $('#mergeChatId').val();

    Swal.fire({
        title: 'Mesclar Chats',
        html: 'As mensagens serao movidas para:<br><strong>' + targetName + '</strong><br><br><small class="text-warning">Esta acao nao pode ser desfeita.</small>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ffc107',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sim, mesclar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('/admin/contatos/mesclar-chats', {
                _token: '{{ csrf_token() }}',
                primary_chat_id: targetId,
                secondary_chat_id: chatId
            }).done(function(response) {
                Swal.fire({
                    title: 'Mesclado!',
                    text: 'Os chats foram mesclados com sucesso.',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    $('#mergeChatModal').modal('hide');
                    location.reload();
                });
            }).fail(function(xhr) {
                showToast(xhr.responseJSON?.error || 'Erro ao mesclar', 'error');
            });
        }
    });
});

// ===== Sidebar da Fila =====
function openFilaSidebar() {
    $('#filaSidebarOverlay').addClass('show');
    $('#filaSidebar').addClass('show');
    loadFilaData();
}

function closeFilaSidebar() {
    $('#filaSidebarOverlay').removeClass('show');
    $('#filaSidebar').removeClass('show');
}

function loadFilaData() {
    $('#filaSidebarBody').html('<div class="fila-sidebar-loading"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Carregando...</p></div>');

    $.get('/admin/fila/dados').done(function(response) {
        var conversas = response.conversas || [];
        $('#filaSidebarCount').text(conversas.length);
        $('#filaCountBadge').text(conversas.length);

        if (conversas.length === 0) {
            $('#filaSidebarBody').html(
                '<div class="fila-sidebar-empty">' +
                '<i class="fas fa-check-circle"></i>' +
                '<h5>Nenhuma conversa na fila!</h5>' +
                '<p>Todas as conversas estao sendo atendidas.</p>' +
                '</div>'
            );
            return;
        }

        var html = '';
        conversas.forEach(function(conv, index) {
            var initials = (conv.cliente_nome || 'C').substring(0, 2).toUpperCase();
            var colorClass = 'color-' + ((index % 5) + 1);
            var preview = conv.ultima_mensagem ? conv.ultima_mensagem.substring(0, 40) + (conv.ultima_mensagem.length > 40 ? '...' : '') : '';

            html += '<div class="fila-sidebar-item" data-id="' + conv.id + '">';
            html += '<div class="avatar ' + colorClass + '">' + initials + '</div>';
            html += '<div class="info">';
            html += '<div class="nome">' + (conv.cliente_nome || 'Cliente') + '</div>';
            html += '<div class="numero">' + conv.cliente_numero + '</div>';
            if (preview) {
                html += '<div class="preview"><i class="fas fa-comment"></i> ' + escapeHtml(preview) + '</div>';
            }
            html += '<div class="tempo"><i class="fas fa-clock"></i> ' + conv.tempo_na_fila + '</div>';
            html += '</div>';
            html += '<button class="btn btn-success btn-sm btn-pegar" data-id="' + conv.id + '"><i class="fas fa-hand-paper"></i></button>';
            html += '</div>';
        });

        $('#filaSidebarBody').html(html);
    }).fail(function() {
        $('#filaSidebarBody').html(
            '<div class="fila-sidebar-empty">' +
            '<i class="fas fa-exclamation-triangle text-warning"></i>' +
            '<h5>Erro ao carregar</h5>' +
            '<p>Tente novamente.</p>' +
            '</div>'
        );
    });
}

$('#btnFilaSidebar').on('click', function() {
    openFilaSidebar();
});

$('#closeFilaSidebar, #filaSidebarOverlay').on('click', function() {
    closeFilaSidebar();
});

// Pegar conversa da fila
$(document).on('click', '.fila-sidebar-item .btn-pegar', function(e) {
    e.stopPropagation();
    var btn = $(this);
    var conversaId = btn.data('id');

    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

    $.post('/admin/conversas/' + conversaId + '/atender', {
        _token: '{{ csrf_token() }}'
    }).done(function(response) {
        showToast('Conversa pega com sucesso!', 'success');
        closeFilaSidebar();
        setTimeout(function() {
            location.reload();
        }, 500);
    }).fail(function(xhr) {
        btn.prop('disabled', false).html('<i class="fas fa-hand-paper"></i>');
        showToast(xhr.responseJSON?.error || 'Erro ao pegar conversa', 'error');
    });
});

// Auto-refresh da fila a cada 30 segundos se o sidebar estiver aberto
setInterval(function() {
    if ($('#filaSidebar').hasClass('show')) {
        loadFilaData();
    }
}, 30000);
</script>
@stop
