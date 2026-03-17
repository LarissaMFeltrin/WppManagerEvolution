@extends('adminlte::page')

@section('title', 'Sincronizar Contatos')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1>Sincronizar Contatos</h1>
        <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Pagina Inicial</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.contatos.index') }}">Contatos</a></li>
            <li class="breadcrumb-item active">Sincronizar Contatos</li>
        </ol>
    </div>
@stop

@section('css')
<style>
.sync-card {
    background: #fff;
    border-radius: 5px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 15px;
}

.sync-card .card-header {
    background: #fff;
    border-bottom: 1px solid #dee2e6;
    padding: 15px;
}

.sync-card .card-header h5 {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.sync-card .card-header h5 i {
    color: #6c757d;
}

.sync-card .card-body {
    padding: 20px;
}

.sync-description {
    color: #6c757d;
    font-size: 0.9rem;
    margin-bottom: 20px;
}

.stats-row {
    display: flex;
    gap: 15px;
    margin-bottom: 25px;
}

.stat-box {
    flex: 1;
    display: flex;
    align-items: center;
    padding: 15px 20px;
    border-radius: 5px;
    color: #fff;
}

.stat-box.bg-teal {
    background: linear-gradient(135deg, #20c997 0%, #17a2b8 100%);
}

.stat-box.bg-warning {
    background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
}

.stat-box.bg-danger {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
}

.stat-box .icon {
    width: 45px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,0.2);
    border-radius: 5px;
    margin-right: 15px;
}

.stat-box .icon i {
    font-size: 1.3rem;
}

.stat-box .content .label {
    font-size: 0.85rem;
    opacity: 0.9;
}

.stat-box .content .value {
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1.2;
}

a.stat-box {
    transition: transform 0.2s, box-shadow 0.2s;
    cursor: pointer;
}

a.stat-box:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.action-buttons {
    display: flex;
    gap: 10px;
}

.btn-sync {
    background: linear-gradient(135deg, #20c997 0%, #17a2b8 100%);
    border: none;
    color: #fff;
    padding: 10px 20px;
    font-weight: 500;
}

.btn-sync:hover {
    background: linear-gradient(135deg, #1ba87c 0%, #138496 100%);
    color: #fff;
}

.instancias-table {
    margin-bottom: 0;
}

.instancias-table th {
    font-size: 0.75rem;
    text-transform: uppercase;
    color: #6c757d;
    font-weight: 600;
    padding: 10px 15px;
    border-top: none;
}

.instancias-table td {
    padding: 10px 15px;
    vertical-align: middle;
    font-size: 0.9rem;
}

.info-card {
    background: #fff;
    border-radius: 5px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    padding: 20px;
}

.info-card h6 {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 15px;
    font-weight: 600;
}

.info-card h6 i {
    color: #17a2b8;
}

.info-card ul {
    margin: 0;
    padding-left: 20px;
}

.info-card ul li {
    margin-bottom: 8px;
    color: #495057;
    font-size: 0.9rem;
}

.info-card ul li:last-child {
    margin-bottom: 0;
}
</style>
@stop

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <i class="fas fa-check-circle"></i> {{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger alert-dismissible">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <i class="fas fa-exclamation-circle"></i>
        @foreach($errors->all() as $error)
            {{ $error }}<br>
        @endforeach
    </div>
@endif

<div class="row">
    {{-- Coluna principal --}}
    <div class="col-lg-8">
        <div class="sync-card">
            <div class="card-header">
                <h5><i class="fas fa-sync-alt"></i> Sincronizar Contatos</h5>
            </div>
            <div class="card-body">
                <p class="sync-description">
                    A sincronizacao verifica os chats existentes e cria contatos que estejam faltando.
                    Tambem atualiza nomes de contatos que estejam em branco usando o nome do chat.
                </p>

                <div class="stats-row">
                    <a href="{{ route('admin.contatos.index') }}" class="stat-box bg-teal" style="text-decoration: none;">
                        <div class="icon">
                            <i class="fas fa-address-book"></i>
                        </div>
                        <div class="content">
                            <div class="label">Total de Contatos</div>
                            <div class="value">{{ $stats['total_contatos'] }}</div>
                        </div>
                    </a>
                    <a href="{{ route('admin.contatos.index', ['filter' => 'sem_nome']) }}" class="stat-box bg-warning" style="text-decoration: none;">
                        <div class="icon">
                            <i class="fas fa-user-slash"></i>
                        </div>
                        <div class="content">
                            <div class="label">Sem Nome</div>
                            <div class="value">{{ $stats['sem_nome'] }}</div>
                        </div>
                    </a>
                    <a href="{{ route('admin.contatos.chats-sem-contato') }}" class="stat-box bg-danger" style="text-decoration: none;">
                        <div class="icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="content">
                            <div class="label">Chats sem Contato</div>
                            <div class="value">{{ $stats['chats_sem_contato'] }}</div>
                        </div>
                    </a>
                </div>

                <div class="action-buttons">
                    <form action="{{ route('admin.contatos.sincronizar') }}" method="POST" id="formSincronizar" class="d-flex align-items-center gap-2">
                        @csrf
                        <select name="account_id" id="accountIdInput" class="form-control" style="max-width: 250px;" required>
                            @foreach($instancias as $inst)
                                <option value="{{ $inst->id }}">
                                    {{ $inst->session_name }} {{ $inst->phone_number ? '(' . $inst->phone_number . ')' : '' }}
                                </option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-sync">
                            <i class="fas fa-sync-alt"></i> Executar Sincronizacao
                        </button>
                    </form>
                    <a href="{{ route('admin.contatos.index') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-list"></i> Ver Contatos
                    </a>
                    <a href="{{ route('admin.contatos.duplicados') }}" class="btn btn-outline-warning">
                        <i class="fas fa-users"></i> Chats Duplicados
                    </a>
                </div>
            </div>
        </div>

        {{-- Card de informação --}}
        <div class="info-card">
            <h6><i class="fas fa-info-circle"></i> O que a sincronizacao faz</h6>
            <ul>
                <li>Cria contatos para chats individuais que nao possuem um registro de contato</li>
                <li>Atualiza o nome de contatos que estao em branco usando o nome salvo no chat</li>
                <li>Sincroniza nomes de grupos WhatsApp via servico Node.js</li>
            </ul>
        </div>
    </div>

    {{-- Coluna lateral --}}
    <div class="col-lg-4">
        <div class="sync-card">
            <div class="card-header">
                <h5><i class="fab fa-whatsapp"></i> Instancias</h5>
            </div>
            <div class="card-body p-0">
                <table class="table instancias-table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Numero</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($instancias as $instancia)
                        <tr class="instancia-row" data-id="{{ $instancia->id }}" style="cursor: pointer;">
                            <td>
                                <div class="form-check">
                                    <input type="radio" name="instancia_selecionada" class="form-check-input"
                                           value="{{ $instancia->id }}" {{ $loop->first ? 'checked' : '' }}>
                                    <label class="form-check-label">{{ $instancia->session_name }}</label>
                                </div>
                            </td>
                            <td>{{ $instancia->owner_jid ? explode('@', $instancia->owner_jid)[0] : '-' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="2" class="text-center text-muted py-3">Nenhuma instancia</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@stop

@section('js')
<script>
$(function() {
    // Selecionar instância ao clicar na linha
    $('.instancia-row').on('click', function() {
        var id = $(this).data('id');
        $(this).find('input[type="radio"]').prop('checked', true);
        $('#accountIdInput').val(id);
    });

    // Atualizar hidden input quando radio mudar
    $('input[name="instancia_selecionada"]').on('change', function() {
        $('#accountIdInput').val($(this).val());
    });

    // Mostrar loading ao submeter
    $('#formSincronizar').on('submit', function(e) {
        var accountId = $('#accountIdInput').val();
        if (!accountId) {
            e.preventDefault();
            alert('Selecione uma instância primeiro!');
            return false;
        }
        var btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true);
        btn.html('<i class="fas fa-spinner fa-spin"></i> Sincronizando...');
    });
});
</script>
@stop
