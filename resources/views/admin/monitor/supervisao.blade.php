@extends('adminlte::page')

@section('title', 'Supervisao em Tempo Real')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1>Supervisao em Tempo Real</h1>
        <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Pagina Inicial</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.monitor') }}">Monitor</a></li>
            <li class="breadcrumb-item active">Supervisao em Tempo Real</li>
        </ol>
    </div>
@stop

@section('css')
<style>
/* Header e KPIs */
.supervisao-header {
    background: #fff;
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.header-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.stats-row {
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
    padding: 5px 0;
}

.stat-item i {
    color: #6c757d;
    width: 20px;
    text-align: center;
}

.stat-item.titulo {
    font-weight: 600;
    font-size: 1rem;
}

.stat-item.titulo i {
    color: #28a745;
}

/* KPI Cards */
.kpi-cards {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.kpi-card {
    background: #fff;
    border-radius: 8px;
    padding: 15px 20px;
    min-width: 180px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 15px;
}

.kpi-card .icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    color: #fff;
}

.kpi-card .icon.tempo { background: linear-gradient(135deg, #667eea, #764ba2); }
.kpi-card .icon.sla { background: linear-gradient(135deg, #11998e, #38ef7d); }
.kpi-card .icon.finalizadas { background: linear-gradient(135deg, #ee0979, #ff6a00); }
.kpi-card .icon.ocupacao { background: linear-gradient(135deg, #4facfe, #00f2fe); }

.kpi-card .info .valor {
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1;
}

.kpi-card .info .label {
    font-size: 0.8rem;
    color: #6c757d;
}

/* Filtros */
.filtros-bar {
    background: #fff;
    padding: 12px 20px;
    border-radius: 8px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.filtros-bar .filtro-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.filtros-bar .filtro-group label {
    margin: 0;
    font-size: 0.85rem;
    color: #495057;
}

.filtros-bar select {
    min-width: 160px;
}

.filtros-bar .auto-refresh {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.85rem;
    color: #6c757d;
}

.filtros-bar .auto-refresh .refresh-time {
    font-weight: 600;
    color: #28a745;
}

.filtros-bar .btn-refresh {
    padding: 5px 12px;
}

/* Cards de Atendentes */
.atendentes-bar {
    background: #fff;
    padding: 12px 20px;
    border-radius: 8px;
    margin-bottom: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.atendentes-bar h6 {
    margin-bottom: 10px;
    font-weight: 600;
    color: #495057;
}

.atendentes-list {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.atendente-chip {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 20px;
    padding: 6px 15px;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.atendente-chip:hover {
    background: #e9ecef;
}

.atendente-chip.selected {
    background: #075e54;
    color: #fff;
    border-color: #075e54;
}

.atendente-chip .badge {
    font-size: 0.7rem;
    padding: 2px 6px;
}

.atendente-chip .ocupacao {
    font-size: 0.75rem;
    opacity: 0.8;
}

/* Grid de conversas */
.conversas-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
}

.conversa-card {
    width: 320px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
    cursor: pointer;
}

.conversa-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
}

.conversa-card-header {
    background: linear-gradient(135deg, #075e54 0%, #128c7e 100%);
    color: #fff;
    padding: 12px 15px;
    display: flex;
    align-items: center;
    gap: 10px;
    position: relative;
}

.conversa-card-header .avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 0.9rem;
}

.conversa-card-header .info {
    flex: 1;
    min-width: 0;
}

.conversa-card-header .info .nome {
    font-weight: 600;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.conversa-card-header .info .nome .tempo {
    font-size: 0.75rem;
    background: rgba(0,0,0,0.2);
    padding: 2px 8px;
    border-radius: 10px;
    flex-shrink: 0;
}

.conversa-card-header .info .numero {
    font-size: 0.8rem;
    opacity: 0.9;
}

.conversa-card-header .badge-grupo {
    background: rgba(255,255,255,0.2);
    font-size: 0.7rem;
    padding: 2px 8px;
    border-radius: 10px;
}

.conversa-card-header .status-indicator {
    position: absolute;
    right: 15px;
    top: 15px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #25d366;
}

/* Waiting Badge */
.waiting-badge {
    position: absolute;
    top: 8px;
    right: 30px;
    background: #dc3545;
    color: white;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: bold;
    display: flex;
    align-items: center;
    gap: 5px;
    animation: pulse-badge 1.5s infinite;
}

@keyframes pulse-badge {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.8; transform: scale(1.05); }
}

.waiting-badge.critical {
    background: #b91c1c;
    animation: pulse-critical 0.8s infinite;
}

@keyframes pulse-critical {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.conversa-card.cliente-aguardando {
    border: 2px solid #dc3545;
}

.conversa-card.cliente-aguardando .conversa-card-header {
    background: linear-gradient(135deg, #c0392b 0%, #e74c3c 100%);
}

.conversa-card.cliente-aguardando.critical {
    border-color: #b91c1c;
    animation: border-pulse 1s infinite;
}

@keyframes border-pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(185, 28, 28, 0.4); }
    50% { box-shadow: 0 0 0 8px rgba(185, 28, 28, 0); }
}

.conversa-card:not(.cliente-aguardando) {
    border: 2px solid #28a745;
}

/* Body mensagens */
.conversa-card-body {
    flex: 1;
    background: #e5ddd5;
    background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23c8c8c8' fill-opacity='0.1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    padding: 10px;
    max-height: 250px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}

.msg-preview {
    max-width: 85%;
    padding: 6px 10px;
    border-radius: 8px;
    margin-bottom: 5px;
    font-size: 0.8rem;
    box-shadow: 0 1px 0.5px rgba(0,0,0,0.13);
}

.msg-preview.sent {
    background: #dcf8c6;
    align-self: flex-end;
    border-radius: 8px 0 8px 8px;
}

.msg-preview.received {
    background: #fff;
    align-self: flex-start;
    border-radius: 0 8px 8px 8px;
}

.msg-preview .sender {
    font-weight: 600;
    font-size: 0.75rem;
    color: #075e54;
    margin-bottom: 2px;
}

.msg-preview .text {
    word-wrap: break-word;
}

.msg-preview .time {
    font-size: 0.65rem;
    color: #667781;
    text-align: right;
    margin-top: 2px;
}

.msg-preview.sent .time {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 3px;
}

.msg-preview.sent .time .check {
    color: #53bdeb;
}

.msg-preview-image {
    max-width: 120px;
    border-radius: 5px;
    margin-bottom: 3px;
}

/* Footer */
.conversa-card-footer {
    background: #f0f2f5;
    padding: 8px 15px;
    display: flex;
    align-items: center;
    gap: 8px;
    border-top: 1px solid #ddd;
}

.conversa-card-footer i {
    color: #6c757d;
}

.conversa-card-footer .atendente-nome {
    font-size: 0.85rem;
    color: #495057;
    flex: 1;
}

.conversa-card-footer .acoes {
    display: flex;
    gap: 5px;
}

.conversa-card-footer .btn-acao {
    padding: 3px 8px;
    font-size: 0.75rem;
    border-radius: 4px;
}

/* Empty state */
.empty-state {
    width: 100%;
    text-align: center;
    padding: 60px 20px;
    background: #fff;
    border-radius: 8px;
}

.empty-state i {
    font-size: 4rem;
    color: #6c757d;
    margin-bottom: 20px;
}

/* Scrollbar */
.conversa-card-body::-webkit-scrollbar {
    width: 5px;
}

.conversa-card-body::-webkit-scrollbar-track {
    background: transparent;
}

.conversa-card-body::-webkit-scrollbar-thumb {
    background: rgba(0,0,0,0.2);
    border-radius: 3px;
}

/* Modal de detalhes */
.modal-conversa .modal-body {
    max-height: 70vh;
    overflow-y: auto;
}

.modal-conversa .mensagens-lista {
    background: #e5ddd5;
    padding: 15px;
    border-radius: 8px;
    max-height: 400px;
    overflow-y: auto;
}

/* Notificacao sonora */
.sound-toggle {
    cursor: pointer;
    padding: 5px 10px;
    border-radius: 5px;
    transition: background 0.2s;
}

.sound-toggle:hover {
    background: #e9ecef;
}

.sound-toggle.muted i {
    color: #dc3545;
}
</style>
@stop

@section('content')
{{-- KPI Cards --}}
<div class="kpi-cards">
    <div class="kpi-card">
        <div class="icon tempo">
            <i class="fas fa-clock"></i>
        </div>
        <div class="info">
            <div class="valor">{{ $stats['tempo_medio'] }}min</div>
            <div class="label">Tempo Medio</div>
        </div>
    </div>
    <div class="kpi-card">
        <div class="icon sla">
            <i class="fas fa-tachometer-alt"></i>
        </div>
        <div class="info">
            <div class="valor">{{ $stats['sla'] }}%</div>
            <div class="label">SLA (< 5min)</div>
        </div>
    </div>
    <div class="kpi-card">
        <div class="icon finalizadas">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="info">
            <div class="valor">{{ $stats['finalizadas_hoje'] }}</div>
            <div class="label">Finalizadas Hoje</div>
        </div>
    </div>
    <div class="kpi-card">
        <div class="icon ocupacao">
            <i class="fas fa-users"></i>
        </div>
        <div class="info">
            <div class="valor">{{ $conversasEmAtendimento->count() }}</div>
            <div class="label">Em Atendimento</div>
        </div>
    </div>
</div>

{{-- Header com Stats --}}
<div class="supervisao-header">
    <div class="header-row">
        <div class="stats-row">
            <div class="stat-item titulo">
                <i class="fas fa-eye"></i>
                <span>Supervisao</span>
            </div>
            <div class="stat-item">
                <i class="fas fa-user"></i>
                <span>{{ $stats['online'] }} online</span>
            </div>
            <div class="stat-item">
                <i class="fas fa-headset"></i>
                <span>{{ $stats['atendendo'] }} atendendo</span>
            </div>
            <div class="stat-item">
                <i class="fas fa-users"></i>
                <span>{{ $stats['na_fila'] }} na fila</span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="sound-toggle" id="soundToggle" title="Som de alerta">
                <i class="fas fa-volume-up"></i>
            </span>
        </div>
    </div>
</div>

{{-- Barra de Atendentes --}}
<div class="atendentes-bar">
    <h6><i class="fas fa-headset"></i> Atendentes</h6>
    <div class="atendentes-list">
        <div class="atendente-chip selected" data-atendente="">
            Todos
            <span class="badge badge-primary">{{ $conversasEmAtendimento->count() }}</span>
        </div>
        @foreach($atendentes as $atendente)
        <div class="atendente-chip" data-atendente="{{ $atendente->id }}">
            {{ $atendente->name }}
            <span class="badge {{ $atendente->conversas_ativas > 0 ? 'badge-success' : 'badge-secondary' }}">
                {{ $atendente->conversas_ativas }}
            </span>
            <span class="ocupacao">({{ $atendente->taxa_ocupacao }}%)</span>
        </div>
        @endforeach
    </div>
</div>

{{-- Filtros --}}
<div class="filtros-bar">
    <div class="filtro-group">
        <label><i class="fas fa-filter"></i> Filtrar:</label>
        <select class="form-control form-control-sm" id="filtroTempo">
            <option value="">Todos tempos</option>
            <option value="5">Aguardando > 5min</option>
            <option value="10">Aguardando > 10min</option>
            <option value="30">Aguardando > 30min</option>
        </select>
    </div>
    <div class="filtro-group">
        <label>Ordenar:</label>
        <select class="form-control form-control-sm" id="ordenacao">
            <option value="tempo-espera">Mais tempo esperando</option>
            <option value="recente">Mais recente</option>
            <option value="nome">Nome A-Z</option>
        </select>
    </div>
    <div class="auto-refresh">
        <i class="fas fa-sync-alt"></i>
        <span>Atualiza em <span class="refresh-time" id="refreshTimer">30</span>s</span>
        <button class="btn btn-sm btn-outline-success btn-refresh" onclick="location.reload()">
            <i class="fas fa-redo"></i>
        </button>
    </div>
</div>

{{-- Grid de Conversas --}}
<div class="conversas-grid" id="conversasGrid">
    @forelse($conversasEmAtendimento as $conversa)
    @php
        $isGroup = $conversa->chat?->chat_type === 'group';

        // Tempo em atendimento
        $tempoAtendimento = $conversa->atendida_em ?? $conversa->created_at;
        $diffTotal = now()->diff($tempoAtendimento);
        $diffHoras = $diffTotal->h + ($diffTotal->days * 24);
        $diffMinutos = $diffTotal->i;

        if ($diffHoras > 0) {
            $tempoFormatado = $diffHoras . 'h ' . $diffMinutos . 'min';
        } else {
            $tempoFormatado = $diffMinutos . 'min';
        }

        // Buscar mensagens
        $mensagensRecentes = $conversa->chat?->messages()
            ->where('is_deleted', false)
            ->orderBy('timestamp', 'desc')
            ->limit(10)
            ->get() ?? collect();
        $mensagens = $mensagensRecentes->reverse();

        // Verificar aguardando
        $aguardandoResposta = false;
        $tempoEspera = null;
        $tempoEsperaMin = 0;
        $ultimaMensagem = $mensagensRecentes->first();

        if ($ultimaMensagem && !$ultimaMensagem->is_from_me) {
            $aguardandoResposta = true;
            $tempoEspera = $ultimaMensagem->timestamp;
            $tempoEsperaMin = (int) ((time() - $tempoEspera) / 60);
        }

        $isCritical = $tempoEsperaMin >= 10;
    @endphp
    <div class="conversa-card {{ $aguardandoResposta ? 'cliente-aguardando' : '' }} {{ $isCritical ? 'critical' : '' }}"
         data-atendente="{{ $conversa->atendente_id }}"
         data-tempo-espera="{{ $tempoEspera }}"
         data-tempo-espera-min="{{ $tempoEsperaMin }}"
         data-aguardando="{{ $aguardandoResposta ? '1' : '0' }}"
         data-nome="{{ $conversa->cliente_nome }}"
         data-conversa-id="{{ $conversa->id }}"
         onclick="abrirDetalhes({{ $conversa->id }})">
        {{-- Header --}}
        <div class="conversa-card-header">
            <div class="avatar">
                {{ strtoupper(substr($conversa->cliente_nome ?? 'C', 0, 2)) }}
            </div>
            <div class="info">
                <div class="nome">
                    {{ Str::limit($conversa->cliente_nome ?? 'Cliente', 20) }}
                    @if($isGroup)
                    <span class="badge-grupo">Grupo</span>
                    @endif
                    <span class="tempo" title="Em atendimento">{{ $tempoFormatado }}</span>
                </div>
                <div class="numero">{{ $conversa->cliente_numero }}</div>
            </div>
            <div class="status-indicator"></div>
        </div>

        {{-- Body --}}
        <div class="conversa-card-body">
            @foreach($mensagens as $msg)
            <div class="msg-preview {{ $msg->is_from_me ? 'sent' : 'received' }}">
                @if(!$msg->is_from_me && $isGroup)
                <div class="sender">{{ $msg->sender_name ?? 'Participante' }}</div>
                @endif
                @if($msg->message_type === 'image')
                    @if($msg->media_url)
                        <img src="{{ $msg->media_url }}" class="msg-preview-image">
                    @else
                        <div class="text"><i class="fas fa-image"></i> Imagem</div>
                    @endif
                @elseif($msg->message_type === 'audio')
                    <div class="text"><i class="fas fa-microphone"></i> Audio</div>
                @elseif($msg->message_type === 'video')
                    <div class="text"><i class="fas fa-video"></i> Video</div>
                @elseif($msg->message_type === 'document')
                    <div class="text"><i class="fas fa-file"></i> {{ Str::limit($msg->message_text ?? 'Documento', 20) }}</div>
                @else
                    <div class="text">{{ Str::limit($msg->message_text ?? '', 80) }}</div>
                @endif
                <div class="time">
                    {{ $msg->created_at->format('H:i') }}
                    @if($msg->is_from_me)
                        <i class="fas fa-check-double check"></i>
                    @endif
                </div>
            </div>
            @endforeach
        </div>

        {{-- Footer --}}
        <div class="conversa-card-footer">
            <i class="fas fa-headset"></i>
            <span class="atendente-nome">{{ $conversa->atendente?->name ?? 'Sem atendente' }}</span>
            <div class="acoes" onclick="event.stopPropagation();">
                <button class="btn btn-warning btn-acao" onclick="transferirConversa({{ $conversa->id }})" title="Transferir">
                    <i class="fas fa-exchange-alt"></i>
                </button>
                <a href="{{ route('admin.painel', ['conversa' => $conversa->id]) }}" class="btn btn-success btn-acao" title="Abrir Chat" target="_blank">
                    <i class="fas fa-external-link-alt"></i>
                </a>
            </div>
        </div>
    </div>
    @empty
    <div class="empty-state">
        <i class="fas fa-comments"></i>
        <h4>Nenhuma conversa em atendimento</h4>
        <p class="text-muted">As conversas ativas aparecerao aqui em tempo real.</p>
    </div>
    @endforelse
</div>

{{-- Modal Detalhes --}}
<div class="modal fade modal-conversa" id="modalDetalhes" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-comments"></i> <span id="modalNomeCliente"></span></h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Atendente:</strong> <span id="modalAtendente"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Tempo:</strong> <span id="modalTempo"></span>
                    </div>
                </div>
                <hr>
                <h6>Mensagens Recentes</h6>
                <div class="mensagens-lista" id="modalMensagens">
                    <!-- Carregado via JS -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-warning" onclick="transferirConversaModal()">
                    <i class="fas fa-exchange-alt"></i> Transferir
                </button>
                <a href="#" class="btn btn-success" id="btnAbrirChat" target="_blank">
                    <i class="fas fa-external-link-alt"></i> Abrir Chat
                </a>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal Transferir --}}
<div class="modal fade" id="modalTransferir" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-exchange-alt"></i> Transferir Conversa</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Transferir para:</p>
                <select class="form-control" id="selectTransferir">
                    <option value="">Selecione um atendente</option>
                    @foreach($atendentes as $atendente)
                    <option value="{{ $atendente->id }}">{{ $atendente->name }} ({{ $atendente->conversas_ativas }} ativas)</option>
                    @endforeach
                </select>
                <div class="mt-3">
                    <button class="btn btn-outline-secondary btn-block" onclick="devolverParaFila()">
                        <i class="fas fa-undo"></i> Devolver para Fila
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" id="btnConfirmarTransferencia">
                    <i class="fas fa-check"></i> Transferir
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Audio de alerta --}}
<audio id="alertSound" preload="auto">
    <source src="data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2teleR8NQaHZ2KFRHBQ8p+/jmTIjJk+k8vqbMh0lVKzt+5k2HS1gpff/ljseM22r/v+TNh86caz8/5E2ITZ5q/z/jTQhOX6p+/+JMiE7gaj6/4YwIT2Dpvn/gyohP4Sk+P+ALyFBhaP3/30tIUOGoff/eishRIef9v93KSFGiJ31/3QnIUiJm/T/cSUhSoqZ8v9uIyFMi5fw/2shIk6MlO7/aCEiUI2S7P9lHyJSkJDq/2IeI1SRjuj/XxwjVpOM5v9cGyNYlIrk/1kaI1qViOL/VhgjXJaG4P9TFyNemYTe/1AWI2CbgtH/ThYkYpsA0P9MFiRkm37O/0oVJGacfMz/RxQkaaB6yv9FFCR9" type="audio/wav">
</audio>
@stop

@section('js')
<script>
var soundEnabled = true;
var currentConversaId = null;
var refreshInterval = 30;
var timerInterval;
var lastAlertTime = 0;

$(function() {
    // Timer de refresh
    startRefreshTimer();

    // Scroll para baixo em cada card
    $('.conversa-card-body').each(function() {
        this.scrollTop = this.scrollHeight;
    });

    // Badges de aguardando
    updateWaitingBadges();
    setInterval(updateWaitingBadges, 1000);

    // Verificar alertas sonoros
    checkSoundAlerts();
    setInterval(checkSoundAlerts, 5000);

    // Filtro por atendente (chips)
    $('.atendente-chip').on('click', function() {
        $('.atendente-chip').removeClass('selected');
        $(this).addClass('selected');

        var atendenteId = $(this).data('atendente');
        filterCards();
    });

    // Filtro por tempo
    $('#filtroTempo').on('change', filterCards);

    // Ordenacao
    $('#ordenacao').on('change', sortCards);

    // Toggle som
    $('#soundToggle').on('click', function() {
        soundEnabled = !soundEnabled;
        $(this).toggleClass('muted');
        $(this).find('i').toggleClass('fa-volume-up fa-volume-mute');
    });

    // Confirmar transferencia
    $('#btnConfirmarTransferencia').on('click', confirmarTransferencia);
});

function startRefreshTimer() {
    var timer = refreshInterval;
    $('#refreshTimer').text(timer);

    timerInterval = setInterval(function() {
        timer--;
        $('#refreshTimer').text(timer);

        if (timer <= 0) {
            location.reload();
        }
    }, 1000);
}

function filterCards() {
    var atendenteId = $('.atendente-chip.selected').data('atendente');
    var tempoMin = parseInt($('#filtroTempo').val()) || 0;

    $('.conversa-card').each(function() {
        var card = $(this);
        var cardAtendente = card.data('atendente');
        var cardTempo = parseInt(card.data('tempo-espera-min')) || 0;
        var show = true;

        // Filtro atendente
        if (atendenteId && cardAtendente != atendenteId) {
            show = false;
        }

        // Filtro tempo
        if (tempoMin > 0 && cardTempo < tempoMin) {
            show = false;
        }

        card.toggle(show);
    });
}

function sortCards() {
    var ordem = $('#ordenacao').val();
    var grid = $('#conversasGrid');
    var cards = grid.find('.conversa-card').toArray();

    cards.sort(function(a, b) {
        if (ordem === 'tempo-espera') {
            return (parseInt($(b).data('tempo-espera-min')) || 0) - (parseInt($(a).data('tempo-espera-min')) || 0);
        } else if (ordem === 'nome') {
            return ($(a).data('nome') || '').localeCompare($(b).data('nome') || '');
        } else {
            return (parseInt($(b).data('tempo-espera')) || 0) - (parseInt($(a).data('tempo-espera')) || 0);
        }
    });

    cards.forEach(function(card) {
        grid.append(card);
    });
}

function updateWaitingBadges() {
    $('.conversa-card').each(function() {
        var card = $(this);
        var aguardando = card.data('aguardando') == '1';
        var tempoEspera = card.data('tempo-espera');
        var badgeEl = card.find('.waiting-badge');

        if (aguardando && tempoEspera) {
            var now = Math.floor(Date.now() / 1000);
            var diff = now - tempoEspera;
            var mins = Math.floor(diff / 60);

            card.data('tempo-espera-min', mins);

            var timeStr = '';
            if (diff < 60) {
                timeStr = diff + 's';
            } else if (diff < 3600) {
                timeStr = mins + 'min';
            } else {
                var hrs = Math.floor(diff / 3600);
                mins = Math.floor((diff % 3600) / 60);
                timeStr = hrs + 'h ' + mins + 'min';
            }

            // Classe critical para > 10min
            if (mins >= 10) {
                card.addClass('critical');
                if (badgeEl.length) badgeEl.addClass('critical');
            }

            if (badgeEl.length === 0) {
                card.find('.conversa-card-header').append(
                    '<span class="waiting-badge' + (mins >= 10 ? ' critical' : '') + '"><i class="fas fa-clock"></i> Aguardando ' + timeStr + '</span>'
                );
            } else {
                badgeEl.html('<i class="fas fa-clock"></i> Aguardando ' + timeStr);
            }
        } else {
            badgeEl.remove();
            card.removeClass('critical');
        }
    });
}

function checkSoundAlerts() {
    if (!soundEnabled) return;

    var now = Date.now();
    if (now - lastAlertTime < 30000) return; // Min 30s entre alertas

    var criticalCount = $('.conversa-card.critical').length;
    if (criticalCount > 0) {
        playAlert();
        lastAlertTime = now;
    }
}

function playAlert() {
    try {
        var audio = document.getElementById('alertSound');
        if (audio) {
            audio.currentTime = 0;
            audio.play().catch(function(){});
        }
    } catch(e) {}
}

function abrirDetalhes(conversaId) {
    currentConversaId = conversaId;
    var card = $('.conversa-card[data-conversa-id="' + conversaId + '"]');

    $('#modalNomeCliente').text(card.data('nome') || 'Cliente');
    $('#modalAtendente').text(card.find('.atendente-nome').text());
    $('#modalTempo').text(card.find('.tempo').text());
    $('#btnAbrirChat').attr('href', '/admin/painel?conversa=' + conversaId);

    // Copiar mensagens
    var mensagens = card.find('.conversa-card-body').html();
    $('#modalMensagens').html(mensagens);

    $('#modalDetalhes').modal('show');
}

function transferirConversa(conversaId) {
    currentConversaId = conversaId;
    $('#modalTransferir').modal('show');
}

function transferirConversaModal() {
    $('#modalDetalhes').modal('hide');
    $('#modalTransferir').modal('show');
}

function confirmarTransferencia() {
    var atendenteId = $('#selectTransferir').val();
    if (!atendenteId) {
        toastr.warning('Selecione um atendente');
        return;
    }

    $.post('/admin/conversas/' + currentConversaId + '/transferir', {
        _token: '{{ csrf_token() }}',
        atendente_id: atendenteId
    }).done(function() {
        toastr.success('Conversa transferida!');
        location.reload();
    }).fail(function() {
        toastr.error('Erro ao transferir');
    });
}

function devolverParaFila() {
    $.post('/admin/conversas/' + currentConversaId + '/transferir', {
        _token: '{{ csrf_token() }}',
        devolver_fila: true
    }).done(function() {
        toastr.success('Conversa devolvida para a fila!');
        location.reload();
    }).fail(function() {
        toastr.error('Erro ao devolver');
    });
}
</script>
@stop
