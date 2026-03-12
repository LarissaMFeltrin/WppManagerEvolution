@extends('adminlte::page')

@section('title', 'Historico de Conversas')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1>Historico de Conversas</h1>
        <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Pagina Inicial</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.monitor') }}">Monitor</a></li>
            <li class="breadcrumb-item active">Historico</li>
        </ol>
    </div>
@stop

@section('css')
<style>
.info-box-custom {
    display: flex;
    align-items: center;
    background: #fff;
    border-radius: 5px;
    padding: 15px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    height: 100%;
}

.info-box-custom .icon {
    width: 60px;
    height: 60px;
    border-radius: 5px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
}

.info-box-custom .icon i {
    font-size: 1.8rem;
    color: #fff;
}

.info-box-custom .icon.bg-primary { background: #007bff; }
.info-box-custom .icon.bg-success { background: #28a745; }
.info-box-custom .icon.bg-info { background: #17a2b8; }
.info-box-custom .icon.bg-warning { background: #ffc107; }

.info-box-custom .content .label {
    font-size: 0.85rem;
    color: #6c757d;
}

.info-box-custom .content .value {
    font-size: 1.8rem;
    font-weight: 700;
    line-height: 1;
}

.monitor-card {
    background: #fff;
    border-radius: 5px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 15px;
}

.monitor-card .card-header {
    background: #fff;
    border-bottom: 1px solid #dee2e6;
    padding: 12px 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.monitor-card .card-header h5 {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.monitor-card .card-header h5 i {
    color: #6c757d;
}

.monitor-card .card-body {
    padding: 0;
}

.monitor-card table {
    margin-bottom: 0;
}

.monitor-card th {
    font-size: 0.75rem;
    text-transform: uppercase;
    color: #17a2b8;
    font-weight: 600;
    padding: 10px 15px;
    border-top: none;
}

.monitor-card td {
    padding: 10px 15px;
    vertical-align: middle;
    font-size: 0.9rem;
}

.badge-status {
    font-size: 0.75rem;
    padding: 4px 10px;
}

.filtros-card {
    background: #fff;
    border-radius: 5px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 15px;
    padding: 15px;
}

.filtros-card .form-group {
    margin-bottom: 0;
}

.filtros-card .form-control {
    font-size: 0.9rem;
}

.status-checkboxes {
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
}

.status-checkboxes .form-check {
    margin: 0;
}

.status-checkboxes .form-check-label {
    font-size: 0.9rem;
}

.btn-acao {
    padding: 4px 8px;
    font-size: 0.8rem;
}

/* Sidebar de Conversa */
.conversa-sidebar {
    position: fixed;
    top: 0;
    right: -420px;
    width: 420px;
    height: 100vh;
    background: #fff;
    box-shadow: -5px 0 25px rgba(0,0,0,0.15);
    z-index: 1050;
    transition: right 0.3s ease;
    display: flex;
    flex-direction: column;
}

.conversa-sidebar.open {
    right: 0;
}

.conversa-sidebar-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.4);
    z-index: 1040;
    display: none;
}

.conversa-sidebar-overlay.open {
    display: block;
}

.sidebar-header {
    background: #075e54;
    color: #fff;
    padding: 15px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}

.sidebar-header .cliente-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.sidebar-header .cliente-info .avatar {
    width: 45px;
    height: 45px;
    background: #128c7e;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.sidebar-header .cliente-info .avatar i {
    font-size: 1.3rem;
}

.sidebar-header .cliente-info .nome {
    font-weight: 600;
    font-size: 1.1rem;
}

.sidebar-header .cliente-info .numero {
    font-size: 0.85rem;
    opacity: 0.9;
}

.sidebar-header .btn-close-sidebar {
    background: none;
    border: none;
    color: #fff;
    font-size: 1.5rem;
    cursor: pointer;
    padding: 5px;
    line-height: 1;
}

.sidebar-header .btn-close-sidebar:hover {
    opacity: 0.8;
}

.sidebar-messages {
    flex: 1;
    overflow-y: auto;
    padding: 15px;
    background: #e5ddd5;
    background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23d4cdc4' fill-opacity='0.4'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}

.sidebar-messages .loading {
    text-align: center;
    padding: 40px;
    color: #6c757d;
}

.sidebar-messages .msg-item {
    display: flex;
    margin-bottom: 8px;
}

.sidebar-messages .msg-item.enviada {
    justify-content: flex-end;
}

.sidebar-messages .msg-item.recebida {
    justify-content: flex-start;
}

.sidebar-messages .msg-bubble {
    max-width: 85%;
    padding: 8px 12px;
    border-radius: 8px;
    position: relative;
    word-wrap: break-word;
}

.sidebar-messages .msg-item.enviada .msg-bubble {
    background: #dcf8c6;
    border-top-right-radius: 0;
}

.sidebar-messages .msg-item.recebida .msg-bubble {
    background: #fff;
    border-top-left-radius: 0;
}

.sidebar-messages .msg-bubble .sender {
    font-size: 0.75rem;
    font-weight: 600;
    color: #075e54;
    margin-bottom: 3px;
}

.sidebar-messages .msg-bubble .text {
    font-size: 0.9rem;
    line-height: 1.4;
}

.sidebar-messages .msg-bubble .text img {
    max-width: 100%;
    border-radius: 5px;
    margin-top: 5px;
}

.sidebar-messages .msg-bubble .text audio {
    max-width: 100%;
}

.sidebar-messages .msg-bubble .meta {
    font-size: 0.7rem;
    color: #667781;
    text-align: right;
    margin-top: 4px;
}

.sidebar-messages .msg-bubble .meta i {
    margin-left: 3px;
}

.sidebar-messages .msg-date-divider {
    text-align: center;
    margin: 15px 0;
}

.sidebar-messages .msg-date-divider span {
    background: #e1f3fb;
    padding: 5px 15px;
    border-radius: 10px;
    font-size: 0.75rem;
    color: #54656f;
}

.sidebar-footer {
    background: #f0f2f5;
    padding: 10px 15px;
    border-top: 1px solid #e9ecef;
    flex-shrink: 0;
}

.sidebar-footer .info-row {
    display: flex;
    justify-content: space-between;
    font-size: 0.8rem;
    color: #6c757d;
}

.sidebar-footer .info-row .atendente {
    color: #075e54;
    font-weight: 500;
}

/* Highlight na linha selecionada */
.table tbody tr.selected {
    background: #e3f2fd !important;
}

.table tbody tr {
    cursor: pointer;
}

.table tbody tr:hover {
    background: #f5f5f5;
}

/* Badge registro(s) */
.badge-registros {
    background: #6c757d;
    color: #fff;
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 0.8rem;
}

.card-header .badge-registros {
    margin-left: auto;
}
</style>
@stop

@section('content')
{{-- Cards superiores --}}
<div class="row mb-3">
    <div class="col-lg-3 col-6">
        <div class="info-box-custom">
            <div class="icon bg-primary">
                <i class="fas fa-comments"></i>
            </div>
            <div class="content">
                <div class="label">Total Conversas</div>
                <div class="value">{{ $stats['total'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="info-box-custom">
            <div class="icon bg-success">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="content">
                <div class="label">Finalizadas</div>
                <div class="value">{{ $stats['finalizadas'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="info-box-custom">
            <div class="icon bg-info">
                <i class="fas fa-headset"></i>
            </div>
            <div class="content">
                <div class="label">Em Atendimento</div>
                <div class="value">{{ $stats['em_atendimento'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="info-box-custom">
            <div class="icon bg-warning">
                <i class="fas fa-users"></i>
            </div>
            <div class="content">
                <div class="label">Na Fila</div>
                <div class="value">{{ $stats['na_fila'] }}</div>
            </div>
        </div>
    </div>
</div>

{{-- Desempenho por Atendente --}}
<div class="monitor-card">
    <div class="card-header">
        <h5><i class="fas fa-chart-bar"></i> Desempenho por Atendente</h5>
        <span class="badge badge-secondary">{{ $atendentes->count() }}</span>
    </div>
    <div class="card-body">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Atendente</th>
                    <th>Status</th>
                    <th>Em Atendimento</th>
                    <th>Finalizadas</th>
                    <th>Devolvidas</th>
                    <th>Tempo Medio</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($atendentes as $atendente)
                <tr>
                    <td><strong>{{ $atendente->name }}</strong></td>
                    <td>
                        @if($atendente->status_atendimento === 'online')
                            <span class="badge badge-success badge-status">Online</span>
                        @else
                            <span class="badge badge-danger badge-status">Offline</span>
                        @endif
                    </td>
                    <td>{{ $atendente->em_atendimento }}</td>
                    <td>{{ $atendente->finalizadas }}</td>
                    <td>{{ $atendente->devolvidas }}</td>
                    <td>{{ $atendente->tempo_medio > 0 ? $atendente->tempo_medio . ' min' : '-' }}</td>
                    <td>
                        <a href="{{ route('admin.historico', ['atendente_id' => $atendente->id]) }}"
                           class="btn btn-sm btn-outline-primary btn-acao">
                            <i class="fas fa-eye"></i> Ver
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-3">Nenhum atendente</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Filtros --}}
<div class="filtros-card">
    <form method="GET" action="{{ route('admin.historico') }}">
        <div class="row align-items-end">
            <div class="col-lg-3 col-md-6 mb-2">
                <div class="form-group">
                    <label>Buscar</label>
                    <input type="text" name="search" class="form-control"
                           placeholder="Nome ou numero..." value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-lg-2 col-md-6 mb-2">
                <div class="form-group">
                    <label>Atendente</label>
                    <select name="atendente_id" class="form-control">
                        <option value="">Todos</option>
                        @foreach($atendentes as $atendente)
                            <option value="{{ $atendente->id }}" {{ request('atendente_id') == $atendente->id ? 'selected' : '' }}>
                                {{ $atendente->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-2">
                <div class="form-group">
                    <label>Status</label>
                    <div class="status-checkboxes">
                        <div class="form-check">
                            <input type="checkbox" name="status[]" value="aguardando" class="form-check-input"
                                   id="statusAguardando" {{ in_array('aguardando', (array)request('status', [])) ? 'checked' : '' }}>
                            <label class="form-check-label" for="statusAguardando">Aguardando</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="status[]" value="em_atendimento" class="form-check-input"
                                   id="statusAtendimento" {{ in_array('em_atendimento', (array)request('status', [])) ? 'checked' : '' }}>
                            <label class="form-check-label" for="statusAtendimento">Em Atendimento</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="status[]" value="finalizada" class="form-check-input"
                                   id="statusFinalizada" {{ in_array('finalizada', (array)request('status', [])) ? 'checked' : '' }}>
                            <label class="form-check-label" for="statusFinalizada">Finalizada</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-6 mb-2">
                <div class="form-group">
                    <label>Periodo</label>
                    <select name="periodo" class="form-control">
                        <option value="">Todos</option>
                        <option value="hoje" {{ request('periodo') == 'hoje' ? 'selected' : '' }}>Hoje</option>
                        <option value="semana" {{ request('periodo') == 'semana' ? 'selected' : '' }}>Esta semana</option>
                        <option value="mes" {{ request('periodo') == 'mes' ? 'selected' : '' }}>Este mes</option>
                        <option value="3meses" {{ request('periodo') == '3meses' ? 'selected' : '' }}>Ultimos 3 meses</option>
                    </select>
                </div>
            </div>
            <div class="col-lg-2 col-md-12 mb-2">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                    <a href="{{ route('admin.historico') }}" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Limpar
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

{{-- Historico de Conversas --}}
<div class="monitor-card">
    <div class="card-header">
        <h5><i class="fas fa-history"></i> Historico de Conversas</h5>
        <span class="badge-registros">{{ $conversas->total() }} registro(s)</span>
    </div>
    <div class="card-body">
        <table class="table table-sm table-hover">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Cliente</th>
                    <th>Numero</th>
                    <th>Atendente</th>
                    <th>Status</th>
                    <th>Inicio</th>
                    <th>Atendida</th>
                    <th>Finalizada</th>
                    <th>Duracao</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($conversas as $conversa)
                @php
                    $duracao = null;
                    if ($conversa->atendida_em && $conversa->finalizada_em) {
                        $diff = $conversa->atendida_em->diff($conversa->finalizada_em);
                        $totalMinutos = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
                        $totalSegundos = $diff->s;

                        if ($totalMinutos >= 60) {
                            $horas = floor($totalMinutos / 60);
                            $minutos = $totalMinutos % 60;
                            $duracao = $horas . 'h ' . $minutos . 'min';
                        } elseif ($totalMinutos > 0) {
                            $duracao = $totalMinutos . 'min';
                        } else {
                            $duracao = $totalSegundos . 's';
                        }
                    } elseif ($conversa->atendida_em) {
                        $diff = $conversa->atendida_em->diff(now());
                        $totalMinutos = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
                        $horas = floor($totalMinutos / 60);
                        $minutos = $totalMinutos % 60;
                        if ($horas > 0) {
                            $duracao = $horas . 'h ' . $minutos . 'min';
                        } else {
                            $duracao = $totalMinutos . 'min';
                        }
                    }
                @endphp
                <tr>
                    <td>{{ $conversa->id }}</td>
                    <td><strong>{{ $conversa->cliente_nome ?? 'Cliente' }}</strong></td>
                    <td>{{ $conversa->cliente_numero }}</td>
                    <td>{{ $conversa->atendente?->name ?? '-' }}</td>
                    <td>
                        @if($conversa->status === 'finalizada')
                            <span class="badge badge-success badge-status">Finalizada</span>
                        @elseif($conversa->status === 'em_atendimento')
                            <span class="badge badge-info badge-status">Em atendimento</span>
                        @else
                            <span class="badge badge-warning badge-status">Aguardando</span>
                        @endif
                    </td>
                    <td>{{ $conversa->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
                    <td>{{ $conversa->atendida_em?->format('d/m/Y H:i') ?? '-' }}</td>
                    <td>{{ $conversa->finalizada_em?->format('d/m/Y H:i') ?? '-' }}</td>
                    <td>{{ $duracao ?? '-' }}</td>
                    <td>
                        <a href="{{ route('admin.conversas.show', $conversa) }}"
                           class="btn btn-sm btn-outline-primary btn-acao" title="Ver conversa">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="text-center text-muted py-4">
                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                        Nenhuma conversa encontrada
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($conversas->hasPages())
    <div class="card-footer">
        {{ $conversas->links('pagination::bootstrap-4') }}
    </div>
    @endif
</div>

{{-- Sidebar de Conversa --}}
<div class="conversa-sidebar-overlay" id="sidebarOverlay"></div>
<div class="conversa-sidebar" id="conversaSidebar">
    <div class="sidebar-header">
        <div class="cliente-info">
            <div class="avatar">
                <i class="fas fa-user"></i>
            </div>
            <div>
                <div class="nome" id="sidebarNome">Cliente</div>
                <div class="numero" id="sidebarNumero">Numero</div>
            </div>
        </div>
        <button type="button" class="btn-close-sidebar" id="closeSidebar">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="sidebar-messages" id="sidebarMessages">
        <div class="loading">
            <i class="fas fa-spinner fa-spin fa-2x"></i>
            <p class="mt-2">Carregando mensagens...</p>
        </div>
    </div>
    <div class="sidebar-footer">
        <div class="info-row">
            <span>Atendente: <span class="atendente" id="sidebarAtendente">-</span></span>
            <span id="sidebarStatus">-</span>
        </div>
    </div>
</div>
@stop

@section('js')
<script>
$(function() {
    var currentConversaId = null;

    // Abrir sidebar ao clicar na linha ou no botao
    $('.table tbody tr').on('click', function(e) {
        // Ignorar se clicou no botao de acao
        if ($(e.target).closest('.btn-acao').length) {
            e.preventDefault();
        }

        var conversaId = $(this).find('td:first').text().trim();
        var nome = $(this).find('td:eq(1)').text().trim();
        var numero = $(this).find('td:eq(2)').text().trim();
        var atendente = $(this).find('td:eq(3)').text().trim();
        var status = $(this).find('td:eq(4) .badge').text().trim();

        if (conversaId && !isNaN(conversaId)) {
            abrirSidebar(conversaId, nome, numero, atendente, status);
            $('.table tbody tr').removeClass('selected');
            $(this).addClass('selected');
        }
    });

    // Botao de visualizar abre sidebar
    $(document).on('click', '.btn-acao', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).closest('tr').click();
    });

    // Fechar sidebar
    $('#closeSidebar, #sidebarOverlay').on('click', function() {
        fecharSidebar();
    });

    // Fechar com ESC
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            fecharSidebar();
        }
    });

    function abrirSidebar(conversaId, nome, numero, atendente, status) {
        currentConversaId = conversaId;

        $('#sidebarNome').text(nome || 'Cliente');
        $('#sidebarNumero').text(numero + ' - Atendente: ' + (atendente || '-'));
        $('#sidebarAtendente').text(atendente || '-');
        $('#sidebarStatus').text(status);

        $('#sidebarMessages').html('<div class="loading"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Carregando mensagens...</p></div>');

        $('#conversaSidebar').addClass('open');
        $('#sidebarOverlay').addClass('open');

        carregarMensagens(conversaId);
    }

    function fecharSidebar() {
        $('#conversaSidebar').removeClass('open');
        $('#sidebarOverlay').removeClass('open');
        $('.table tbody tr').removeClass('selected');
        currentConversaId = null;
    }

    function carregarMensagens(conversaId) {
        $.ajax({
            url: '/admin/conversas/' + conversaId + '/mensagens',
            method: 'GET',
            success: function(response) {
                renderizarMensagens(response.mensagens || []);
            },
            error: function(xhr) {
                $('#sidebarMessages').html('<div class="text-center text-danger py-4"><i class="fas fa-exclamation-circle fa-2x"></i><p class="mt-2">Erro ao carregar mensagens</p></div>');
            }
        });
    }

    function renderizarMensagens(mensagens) {
        if (mensagens.length === 0) {
            $('#sidebarMessages').html('<div class="text-center text-muted py-4"><i class="fas fa-comments fa-2x"></i><p class="mt-2">Nenhuma mensagem</p></div>');
            return;
        }

        var html = '';
        var lastDate = '';

        mensagens.forEach(function(msg) {
            // Divisor de data
            var msgDate = formatarData(msg.timestamp || msg.created_at);
            if (msgDate !== lastDate) {
                html += '<div class="msg-date-divider"><span>' + msgDate + '</span></div>';
                lastDate = msgDate;
            }

            var isEnviada = msg.from_me || msg.direction === 'outgoing';
            var classe = isEnviada ? 'enviada' : 'recebida';

            html += '<div class="msg-item ' + classe + '">';
            html += '<div class="msg-bubble">';

            // Sender name para mensagens recebidas
            if (!isEnviada && msg.sender_name) {
                html += '<div class="sender">' + escapeHtml(msg.sender_name) + '</div>';
            }

            // Conteudo da mensagem
            html += '<div class="text">' + formatarConteudo(msg) + '</div>';

            // Meta (hora + status)
            var hora = formatarHora(msg.timestamp || msg.created_at);
            html += '<div class="meta">' + hora;
            if (isEnviada) {
                html += ' <i class="fas fa-check-double" style="color: #53bdeb;"></i>';
            }
            html += '</div>';

            html += '</div></div>';
        });

        $('#sidebarMessages').html(html);

        // Scroll para o final
        var container = document.getElementById('sidebarMessages');
        container.scrollTop = container.scrollHeight;
    }

    function formatarConteudo(msg) {
        var tipo = msg.message_type || msg.type || 'text';
        var conteudo = msg.content || msg.body || '';

        switch(tipo) {
            case 'image':
            case 'imageMessage':
                var imgUrl = msg.media_url || '';
                if (imgUrl) {
                    return '<img src="' + imgUrl + '" alt="Imagem" onclick="window.open(this.src)">';
                }
                return '<i class="fas fa-image"></i> Imagem';

            case 'audio':
            case 'audioMessage':
            case 'ptt':
                var audioUrl = msg.media_url || '';
                if (audioUrl) {
                    return '<audio controls><source src="' + audioUrl + '"></audio>';
                }
                return '<i class="fas fa-microphone"></i> Audio';

            case 'video':
            case 'videoMessage':
                var videoUrl = msg.media_url || '';
                if (videoUrl) {
                    return '<video controls style="max-width:100%;border-radius:5px;"><source src="' + videoUrl + '"></video>';
                }
                return '<i class="fas fa-video"></i> Video';

            case 'document':
            case 'documentMessage':
                var docUrl = msg.media_url || '';
                var docName = msg.media_filename || 'Documento';
                if (docUrl) {
                    return '<a href="' + docUrl + '" target="_blank" class="text-primary"><i class="fas fa-file"></i> ' + escapeHtml(docName) + '</a>';
                }
                return '<i class="fas fa-file"></i> Documento';

            case 'sticker':
            case 'stickerMessage':
                var stickerUrl = msg.media_url || '';
                if (stickerUrl) {
                    return '<img src="' + stickerUrl + '" alt="Sticker" style="max-width:150px;">';
                }
                return '<i class="fas fa-sticky-note"></i> Sticker';

            case 'location':
            case 'locationMessage':
                return '<i class="fas fa-map-marker-alt"></i> Localizacao';

            case 'contact':
            case 'contactMessage':
                return '<i class="fas fa-user"></i> Contato';

            default:
                return formatWhatsApp(escapeHtml(conteudo));
        }
    }

    function formatWhatsApp(text) {
        if (!text) return '';
        // Negrito
        text = text.replace(/\*([^*]+)\*/g, '<strong>$1</strong>');
        // Italico
        text = text.replace(/_([^_]+)_/g, '<em>$1</em>');
        // Tachado
        text = text.replace(/~([^~]+)~/g, '<del>$1</del>');
        // Codigo
        text = text.replace(/```([^`]+)```/g, '<code>$1</code>');
        // Quebras de linha
        text = text.replace(/\n/g, '<br>');
        return text;
    }

    function formatarData(timestamp) {
        if (!timestamp) return '';
        var date = new Date(timestamp * 1000 || timestamp);
        if (isNaN(date.getTime())) {
            date = new Date(timestamp);
        }
        var hoje = new Date();
        var ontem = new Date(hoje);
        ontem.setDate(ontem.getDate() - 1);

        if (date.toDateString() === hoje.toDateString()) {
            return 'Hoje';
        } else if (date.toDateString() === ontem.toDateString()) {
            return 'Ontem';
        } else {
            return date.toLocaleDateString('pt-BR');
        }
    }

    function formatarHora(timestamp) {
        if (!timestamp) return '';
        var date = new Date(timestamp * 1000 || timestamp);
        if (isNaN(date.getTime())) {
            date = new Date(timestamp);
        }
        return date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    }

    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>
@stop
