@extends('adminlte::page')

@section('title', 'Contatos')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1>Contatos</h1>
        <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Pagina Inicial</a></li>
            <li class="breadcrumb-item active">Contatos</li>
        </ol>
    </div>
@stop

@section('css')
<style>
.contatos-header {
    background: #fff;
    padding: 15px 20px;
    border-radius: 5px;
    margin-bottom: 15px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.contatos-header .titulo {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 15px;
}

.contatos-header .titulo i {
    color: #6c757d;
}

.filtros-bar {
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
}

.filtros-bar .search-box {
    flex: 1;
    min-width: 250px;
    position: relative;
}

.filtros-bar .search-box input {
    padding-left: 40px;
    height: 42px;
}

.filtros-bar .search-box i {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #adb5bd;
}

.filtros-bar .select-instancia {
    min-width: 220px;
}

.filtros-bar .select-instancia select {
    height: 42px;
}

.filtros-bar .btn {
    height: 42px;
    padding: 0 20px;
}

.contatos-table {
    background: #fff;
    border-radius: 5px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    overflow: hidden;
}

.contatos-table table {
    margin-bottom: 0;
}

.contatos-table th {
    background: #fff;
    border-top: none;
    font-weight: 600;
    color: #17a2b8;
    padding: 15px 20px;
    border-bottom: 2px solid #dee2e6;
}

.contatos-table td {
    padding: 12px 20px;
    vertical-align: middle;
}

.contatos-table tbody tr:hover {
    background: #f8f9fa;
}

.contatos-table .nome-link {
    color: #17a2b8;
    font-weight: 500;
    text-decoration: none;
}

.contatos-table .nome-link:hover {
    text-decoration: underline;
}

.contatos-table .badge-bloqueado {
    font-size: 0.8rem;
    padding: 5px 12px;
}

.contatos-table .btn-enviar {
    padding: 6px 15px;
    font-size: 0.85rem;
}

.contatos-vazio {
    background: #fff;
    padding: 60px 20px;
    border-radius: 5px;
    text-align: center;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.contatos-vazio i {
    font-size: 4rem;
    color: #6c757d;
    margin-bottom: 20px;
}

.pagination {
    margin: 0;
}
</style>
@stop

@section('content')
{{-- Header com Filtros --}}
<div class="contatos-header">
    <div class="titulo">
        <i class="fas fa-address-book"></i>
        Contatos
    </div>

    <form action="" method="GET" class="filtros-bar">
        @if(request('filter'))
            <input type="hidden" name="filter" value="{{ request('filter') }}">
        @endif
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" name="search" class="form-control"
                   value="{{ request('search') }}" placeholder="Buscar por nome ou numero...">
        </div>
        <div class="select-instancia">
            <select name="account_id" class="form-control">
                <option value="">-- Todas as Instancias --</option>
                @foreach($accounts as $account)
                    <option value="{{ $account->id }}" {{ request('account_id') == $account->id ? 'selected' : '' }}>
                        {{ $account->session_name }}
                    </option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-success">
            <i class="fas fa-filter"></i> Filtrar
        </button>
        <a href="{{ route('admin.contatos.index') }}" class="btn btn-light">
            <i class="fas fa-times"></i> Limpar
        </a>
        <a href="{{ route('admin.contatos.sincronizar.page') }}" class="btn btn-info ml-3">
            <i class="fas fa-sync"></i> Sincronizar
        </a>
        <a href="{{ route('admin.contatos.duplicados') }}" class="btn btn-warning ml-2">
            <i class="fas fa-users"></i> Duplicados
        </a>
    </form>
</div>

{{-- Indicador de filtro ativo --}}
@if(request('filter') === 'sem_nome')
<div class="alert alert-warning d-flex justify-content-between align-items-center mb-3">
    <div>
        <i class="fas fa-filter"></i>
        <strong>Filtro ativo:</strong> Mostrando apenas contatos sem nome
    </div>
    <a href="{{ route('admin.contatos.index') }}" class="btn btn-sm btn-light">
        <i class="fas fa-times"></i> Remover filtro
    </a>
</div>
@endif

{{-- Tabela de Contatos --}}
@if($contacts->count() > 0)
<div class="contatos-table">
    <table class="table">
        <thead>
            <tr>
                <th>Nome</th>
                <th>Numero</th>
                <th>Instancia</th>
                <th>Bloqueado</th>
                <th width="150"></th>
            </tr>
        </thead>
        <tbody>
            @foreach($contacts as $contact)
            <tr>
                <td>
                    <a href="{{ route('admin.contatos.edit', $contact) }}" class="nome-link">
                        {{ $contact->name ?? 'Sem nome' }}
                    </a>
                </td>
                <td>{{ $contact->phone }}</td>
                <td>{{ $contact->account?->session_name ?? '-' }}</td>
                <td>
                    @if($contact->is_blocked)
                        <span class="badge badge-danger badge-bloqueado">Sim</span>
                    @else
                        <span class="badge badge-danger badge-bloqueado">Nao</span>
                    @endif
                </td>
                <td>
                    <a href="{{ route('admin.contatos.abrir-conversa', $contact) }}"
                       class="btn btn-success btn-enviar">
                        <i class="fas fa-comments"></i> Abrir Conversa
                    </a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @if($contacts->hasPages())
    <div class="card-footer d-flex justify-content-center">
        {{ $contacts->appends(request()->query())->links('pagination::bootstrap-4') }}
    </div>
    @endif
</div>
@else
<div class="contatos-vazio">
    <i class="fas fa-address-book"></i>
    <h4>Nenhum contato encontrado</h4>
    <p class="text-muted">Sincronize os contatos de uma instancia WhatsApp.</p>
    <button class="btn btn-success mt-3" data-toggle="modal" data-target="#sincronizarModal">
        <i class="fas fa-sync"></i> Sincronizar Contatos
    </button>
</div>
@endif

{{-- Modal Sincronizar --}}
<div class="modal fade" id="sincronizarModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.contatos.sincronizar') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Sincronizar Contatos</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Selecione a Instancia:</label>
                        <select name="account_id" class="form-control" required>
                            <option value="">Selecione...</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->session_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        A sincronizacao ira buscar todos os contatos do WhatsApp.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-sync"></i> Sincronizar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@stop

@section('js')
<script>
toastr.options = {
    "closeButton": true,
    "progressBar": true,
    "positionClass": "toast-top-right",
    "timeOut": "3000"
};

@if(session('success'))
    toastr.success('{{ session('success') }}');
@endif

@if(session('error'))
    toastr.error('{{ session('error') }}');
@endif
</script>
@stop
