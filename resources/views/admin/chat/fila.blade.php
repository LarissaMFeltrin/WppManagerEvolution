@extends('adminlte::page')

@section('title', 'Fila de Espera')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1>Fila de Espera</h1>
        <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Pagina Inicial</a></li>
            <li class="breadcrumb-item active">Fila de Espera</li>
        </ol>
    </div>
@stop

@section('css')
<style>
.fila-header {
    background: #fff;
    padding: 15px 20px;
    border-radius: 5px;
    margin-bottom: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.fila-header .titulo {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1.1rem;
    font-weight: 600;
}

.fila-header .titulo i {
    color: #ffc107;
}

.fila-header .titulo .badge {
    font-size: 0.85rem;
}

.fila-header .info-atendente {
    color: #6c757d;
    font-size: 0.9rem;
}

.fila-header .info-atendente i {
    margin-right: 5px;
}

.fila-actions {
    background: #e9ecef;
    padding: 10px 20px;
    border-radius: 5px;
    margin-bottom: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.fila-actions .selecionar-todas {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
}

.fila-actions .selecionar-todas input {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.fila-item {
    background: #fff;
    padding: 15px 20px;
    border-radius: 5px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    border-left: 4px solid #ffc107;
    transition: all 0.2s;
}

.fila-item:hover {
    box-shadow: 0 3px 8px rgba(0,0,0,0.12);
}

.fila-item .checkbox-col {
    display: flex;
    align-items: center;
}

.fila-item .checkbox-col input {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.fila-item .avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: #dfe5e7;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    color: #fff;
    font-size: 1rem;
    flex-shrink: 0;
}

.fila-item .avatar.has-image {
    background-size: cover;
    background-position: center;
}

.fila-item .avatar.color-1 { background: #25d366; }
.fila-item .avatar.color-2 { background: #128c7e; }
.fila-item .avatar.color-3 { background: #075e54; }
.fila-item .avatar.color-4 { background: #34b7f1; }
.fila-item .avatar.color-5 { background: #00a884; }

.fila-item .info {
    flex: 1;
    min-width: 0;
}

.fila-item .info .nome {
    font-weight: 600;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.fila-item .info .nome .badge-instancia {
    font-size: 0.7rem;
    font-weight: 500;
    padding: 3px 8px;
}

.fila-item .info .numero {
    font-size: 0.8rem;
    color: #6c757d;
    margin-top: 2px;
}

.fila-item .info .preview {
    font-size: 0.85rem;
    color: #6c757d;
    margin-top: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.fila-item .info .preview i {
    color: #adb5bd;
}

.fila-item .info .preview span {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 400px;
}

.fila-item .tempo {
    text-align: right;
    flex-shrink: 0;
}

.fila-item .tempo .tempo-fila {
    color: #dc3545;
    font-size: 0.85rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 5px;
    justify-content: flex-end;
}

.fila-item .tempo .tempo-fila i {
    font-size: 0.8rem;
}

.fila-item .acoes {
    flex-shrink: 0;
}

.fila-item .acoes .btn-pegar {
    padding: 8px 20px;
    font-weight: 500;
}

.fila-vazia {
    background: #fff;
    padding: 60px 20px;
    border-radius: 5px;
    text-align: center;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.fila-vazia i {
    font-size: 4rem;
    color: #28a745;
    margin-bottom: 20px;
}

.fila-vazia h4 {
    color: #495057;
    margin-bottom: 10px;
}

.fila-vazia p {
    color: #6c757d;
}
</style>
@stop

@section('content')
{{-- Header da Fila --}}
<div class="fila-header">
    <div class="titulo">
        <i class="fas fa-folder-open"></i>
        Fila de Espera
        <span class="badge badge-success">{{ $conversas->count() }}</span>
    </div>
    <div class="info-atendente">
        <i class="fas fa-headset"></i>
        {{ $user->name }} &mdash; {{ $conversasAtivas }}/{{ $maxSlots }} conversas
    </div>
</div>

@if($conversas->count() > 0)
{{-- Barra de Acoes --}}
<div class="fila-actions">
    <label class="selecionar-todas mb-0">
        <input type="checkbox" id="selectAll">
        <span>Selecionar todas</span>
    </label>
    <button class="btn btn-success btn-sm" id="btnFinalizarSelecionadas" disabled>
        <i class="fas fa-check-double"></i> Finalizar Selecionadas (<span id="countSelecionadas">0</span>)
    </button>
</div>

{{-- Lista da Fila --}}
<div class="fila-lista">
    @foreach($conversas as $index => $conversa)
    @php
        $ultimaMensagem = $conversa->chat?->messages?->first();
        $tempoNaFila = $conversa->cliente_aguardando_desde ?? $conversa->created_at;
        $diffTotal = now()->diff($tempoNaFila);
        $diffHoras = $diffTotal->h + ($diffTotal->days * 24);
        $diffMinutos = $diffTotal->i;
    @endphp
    <div class="fila-item" data-id="{{ $conversa->id }}">
        <div class="checkbox-col">
            <input type="checkbox" class="item-checkbox" value="{{ $conversa->id }}">
        </div>
        <div class="avatar color-{{ ($index % 5) + 1 }}">
            {{ strtoupper(substr($conversa->cliente_nome ?? 'C', 0, 2)) }}
        </div>
        <div class="info">
            <div class="nome">
                {{ $conversa->cliente_nome ?? 'Cliente' }}
                @if($conversa->account)
                <span class="badge badge-info badge-instancia">
                    <i class="fab fa-whatsapp"></i> {{ $conversa->account->session_name }}
                </span>
                @endif
            </div>
            <div class="numero">{{ $conversa->cliente_numero }}</div>
            @if($ultimaMensagem)
            <div class="preview">
                <i class="fas fa-comment"></i>
                <span>{{ Str::limit($ultimaMensagem->message_text ?? '[midia]', 60) }}</span>
            </div>
            @endif
        </div>
        <div class="tempo">
            <div class="tempo-fila">
                <i class="fas fa-clock"></i>
                {{ $diffHoras }}h {{ $diffMinutos }}min na fila
            </div>
        </div>
        <div class="acoes">
            <form action="{{ route('admin.conversas.atender', $conversa) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-success btn-pegar">
                    <i class="fas fa-hand-paper"></i> Pegar
                </button>
            </form>
        </div>
    </div>
    @endforeach
</div>
@else
{{-- Fila Vazia --}}
<div class="fila-vazia">
    <i class="fas fa-check-circle"></i>
    <h4>Nenhuma conversa na fila!</h4>
    <p>Todas as conversas estao sendo atendidas.</p>
</div>
@endif
@stop

@section('js')
<script>
$(function() {
    // Selecionar todas
    $('#selectAll').on('change', function() {
        var checked = $(this).is(':checked');
        $('.item-checkbox').prop('checked', checked);
        updateCount();
    });

    // Atualizar contador ao selecionar item
    $('.item-checkbox').on('change', function() {
        updateCount();

        var total = $('.item-checkbox').length;
        var selecionados = $('.item-checkbox:checked').length;
        $('#selectAll').prop('checked', total === selecionados);
    });

    function updateCount() {
        var count = $('.item-checkbox:checked').length;
        $('#countSelecionadas').text(count);
        $('#btnFinalizarSelecionadas').prop('disabled', count === 0);
    }

    // Finalizar selecionadas
    $('#btnFinalizarSelecionadas').on('click', function() {
        var ids = [];
        $('.item-checkbox:checked').each(function() {
            ids.push($(this).val());
        });

        if (ids.length === 0) return;

        if (!confirm('Finalizar ' + ids.length + ' conversa(s) selecionada(s)?')) return;

        var done = 0;
        ids.forEach(function(id) {
            $.ajax({
                url: '/admin/painel/' + id + '/finalizar',
                method: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                complete: function() {
                    done++;
                    if (done >= ids.length) {
                        location.reload();
                    }
                }
            });
        });
    });

    // Auto-refresh a cada 30 segundos
    setTimeout(function() { location.reload(); }, 30000);
});
</script>
@stop
