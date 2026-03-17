@extends('adminlte::page')

@section('title', 'Logs do Sistema')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1>Logs do Sistema</h1>
        <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Pagina Inicial</a></li>
            <li class="breadcrumb-item active">Logs do Sistema</li>
        </ol>
    </div>
@stop

@section('css')
<style>
.logs-card {
    background: #fff;
    border-radius: 5px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.logs-card .card-header {
    background: #fff;
    border-bottom: 1px solid #dee2e6;
    padding: 15px;
}

.logs-card .card-header h5 {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.logs-card .card-header h5 i {
    color: #6c757d;
}

.logs-card table {
    margin-bottom: 0;
}

.logs-card th {
    font-size: 0.75rem;
    text-transform: uppercase;
    color: #17a2b8;
    font-weight: 600;
    padding: 10px 15px;
    border-top: none;
}

.logs-card td {
    padding: 10px 15px;
    vertical-align: middle;
    font-size: 0.9rem;
}

.filtros-inline {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.filtros-inline .form-control {
    min-width: 200px;
}

.badge-tipo {
    font-size: 0.75rem;
    padding: 4px 10px;
    border-radius: 3px;
}

.badge-tipo.erro {
    background: #dc3545;
    color: #fff;
}

.badge-tipo.info {
    background: #17a2b8;
    color: #fff;
}

.badge-tipo.atendimento {
    background: #20c997;
    color: #fff;
}

.badge-tipo.webhook {
    background: #6f42c1;
    color: #fff;
}

.badge-tipo.envio {
    background: #28a745;
    color: #fff;
}

.badge-tipo.conexao {
    background: #007bff;
    color: #fff;
}

.badge-nivel {
    font-size: 0.75rem;
    padding: 4px 10px;
    border-radius: 3px;
}

.badge-nivel.debug {
    background: #6c757d;
    color: #fff;
}

.badge-nivel.info {
    background: #17a2b8;
    color: #fff;
}

.badge-nivel.warning {
    background: #ffc107;
    color: #212529;
}

.badge-nivel.error {
    background: #dc3545;
    color: #fff;
}

.btn-detalhes {
    background: #17a2b8;
    color: #fff;
    font-size: 0.8rem;
    padding: 4px 12px;
    border-radius: 3px;
    border: none;
}

.btn-detalhes:hover {
    background: #138496;
    color: #fff;
}

.btn-detalhes i {
    margin-right: 4px;
}
</style>
@stop

@section('content')
{{-- Estatísticas --}}
@if(isset($stats))
<div class="row mb-3">
    <div class="col-md-3">
        <div class="small-box bg-info">
            <div class="inner"><h3>{{ $stats['total'] }}</h3><p>Total de Logs</p></div>
            <div class="icon"><i class="fas fa-file-alt"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="small-box bg-purple">
            <div class="inner"><h3>{{ $stats['webhooks_hoje'] }}</h3><p>Webhooks Hoje</p></div>
            <div class="icon"><i class="fas fa-plug"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="small-box bg-danger">
            <div class="inner"><h3>{{ $stats['erros'] }}</h3><p>Erros (24h)</p></div>
            <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="small-box bg-success">
            <div class="inner"><h3>{{ $stats['conexoes'] }}</h3><p>Conexoes (24h)</p></div>
            <div class="icon"><i class="fas fa-link"></i></div>
        </div>
    </div>
</div>
@endif

{{-- Card com filtros e tabela --}}
<div class="logs-card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5><i class="fas fa-file-alt"></i> Logs do Sistema</h5>
            <form action="{{ route('admin.logs') }}" method="GET" class="filtros-inline">
                <select name="tipo" class="form-control">
                    <option value="">-- Todos os Tipos --</option>
                    <option value="webhook" {{ request('tipo') == 'webhook' ? 'selected' : '' }}>Webhook</option>
                    <option value="envio" {{ request('tipo') == 'envio' ? 'selected' : '' }}>Envio</option>
                    <option value="erro" {{ request('tipo') == 'erro' ? 'selected' : '' }}>Erro</option>
                    <option value="conexao" {{ request('tipo') == 'conexao' ? 'selected' : '' }}>Conexao</option>
                </select>
                <select name="nivel" class="form-control">
                    <option value="">-- Todos os Niveis --</option>
                    <option value="info" {{ request('nivel') == 'info' ? 'selected' : '' }}>Info</option>
                    <option value="warning" {{ request('nivel') == 'warning' ? 'selected' : '' }}>Warning</option>
                    <option value="error" {{ request('nivel') == 'error' ? 'selected' : '' }}>Error</option>
                </select>
                <input type="text" name="search" class="form-control" placeholder="Buscar..." value="{{ request('search') }}">
                <button type="submit" class="btn btn-info">
                    <i class="fas fa-filter"></i> Filtrar
                </button>
                <a href="{{ route('admin.logs') }}" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Limpar
                </a>
            </form>
        </div>
    </div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th width="120">Tipo</th>
                    <th width="80">Nivel</th>
                    <th width="120">Evento</th>
                    <th width="100">Instancia</th>
                    <th>Mensagem</th>
                    <th width="160">Data</th>
                    <th width="80"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr>
                    <td>
                        <span class="badge-tipo {{ $log->tipo }}">{{ $log->tipo }}</span>
                    </td>
                    <td>
                        <span class="badge-nivel {{ $log->nivel }}">{{ $log->nivel }}</span>
                    </td>
                    <td><small>{{ $log->evento ?? '-' }}</small></td>
                    <td><small>{{ $log->instancia ?? '-' }}</small></td>
                    <td>{{ Str::limit($log->mensagem, 80) }}</td>
                    <td><small>{{ $log->criada_em?->format('d/m/Y H:i:s') ?? '-' }}</small></td>
                    <td>
                        @if($log->dados)
                        <a href="{{ route('admin.logs.show', $log) }}" class="btn-detalhes">
                            <i class="fas fa-eye"></i>
                        </a>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                        Nenhum log encontrado
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <div>
            @if($logs->hasPages())
                {{ $logs->links('pagination::bootstrap-4') }}
            @endif
        </div>
        <form action="{{ route('admin.logs.limpar') }}" method="POST" onsubmit="return confirm('Remover logs com mais de 30 dias?')">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-danger">
                <i class="fas fa-trash"></i> Limpar logs antigos (30+ dias)
            </button>
        </form>
    </div>
</div>
@stop
