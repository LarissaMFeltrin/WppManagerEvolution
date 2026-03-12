@extends('adminlte::page')

@section('title', 'Meu Console')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1>Meu Console</h1>
        <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Pagina Inicial</a></li>
            <li class="breadcrumb-item active">Meu Console</li>
        </ol>
    </div>
@stop

@section('css')
<style>
.console-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.console-header .info-atendente {
    display: flex;
    align-items: center;
    gap: 10px;
}

.console-header .info-atendente .nome {
    font-size: 1.3rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.console-header .info-atendente .nome i {
    color: #6c757d;
}

.console-header .info-atendente .conversas-count {
    color: #6c757d;
    font-size: 0.9rem;
}

.console-header .btn-fila {
    display: flex;
    align-items: center;
    gap: 8px;
}

.alerta-fila {
    background: #fff3cd;
    border: 1px solid #ffc107;
    border-radius: 5px;
    padding: 12px 20px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alerta-fila i {
    color: #856404;
    font-size: 1.1rem;
}

.alerta-fila a {
    color: #856404;
    font-weight: 600;
    text-decoration: underline;
}

.console-actions {
    background: #e9ecef;
    padding: 10px 20px;
    border-radius: 5px;
    margin-bottom: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.console-actions .selecionar-todas {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
}

.console-actions .selecionar-todas input {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.conversa-item {
    background: #fff;
    padding: 15px 20px;
    border-radius: 5px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    border-left: 4px solid #28a745;
}

.conversa-item .checkbox-col {
    display: flex;
    align-items: center;
}

.conversa-item .checkbox-col input {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.conversa-item .info {
    flex: 1;
    min-width: 0;
}

.conversa-item .info .nome {
    font-weight: 600;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.conversa-item .info .nome i {
    color: #6c757d;
}

.conversa-item .info .numero {
    font-size: 0.8rem;
    color: #6c757d;
    margin-top: 2px;
}

.conversa-item .info .preview {
    font-size: 0.85rem;
    color: #6c757d;
    margin-top: 5px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.conversa-item .info .preview i {
    color: #adb5bd;
}

.conversa-item .info .preview span {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 400px;
}

.conversa-item .tempo {
    text-align: right;
    flex-shrink: 0;
    min-width: 100px;
}

.conversa-item .tempo .tempo-atendimento {
    color: #17a2b8;
    font-size: 0.85rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 5px;
    justify-content: flex-end;
}

.conversa-item .acoes {
    flex-shrink: 0;
    display: flex;
    gap: 8px;
}

.conversa-item .acoes .btn {
    padding: 6px 12px;
    font-size: 0.85rem;
}

.console-vazio {
    background: #fff;
    padding: 60px 20px;
    border-radius: 5px;
    text-align: center;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.console-vazio i {
    font-size: 4rem;
    color: #6c757d;
    margin-bottom: 20px;
}

.console-vazio h4 {
    color: #495057;
    margin-bottom: 10px;
}

.console-vazio p {
    color: #6c757d;
}

/* Modal de transferencia */
.modal-transferir .atendente-item {
    padding: 10px;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    margin-bottom: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.modal-transferir .atendente-item:hover {
    background: #f8f9fa;
    border-color: #28a745;
}

.modal-transferir .atendente-item.selected {
    background: #d4edda;
    border-color: #28a745;
}
</style>
@stop

@section('content')
{{-- Header do Console --}}
<div class="console-header">
    <div class="info-atendente">
        <div>
            <div class="nome">
                <i class="fas fa-headset"></i>
                {{ $user->name }}
                <span class="badge badge-success">Online</span>
            </div>
            <div class="conversas-count">
                Conversas: {{ $conversasAtivas }} / {{ $maxSlots }}
            </div>
        </div>
    </div>
    <div>
        <a href="{{ route('admin.fila') }}" class="btn btn-warning btn-fila">
            <i class="fas fa-comments"></i>
            Fila de Espera
            <span class="badge badge-light">{{ $filaCount }}</span>
        </a>
    </div>
</div>

{{-- Alerta de Fila --}}
@if($filaCount > 0)
<div class="alerta-fila">
    <i class="fas fa-bell"></i>
    <span>
        <strong>{{ $filaCount }}</strong> conversa(s) aguardando na fila.
        <a href="{{ route('admin.fila') }}">Ir para a Fila</a>
    </span>
</div>
@endif

@if($conversas->count() > 0)
{{-- Barra de Acoes --}}
<div class="console-actions">
    <label class="selecionar-todas mb-0">
        <input type="checkbox" id="selectAll">
        <span>Selecionar todas</span>
    </label>
    <button class="btn btn-success btn-sm" id="btnFinalizarSelecionadas" disabled>
        <i class="fas fa-check-double"></i> Finalizar Selecionadas (<span id="countSelecionadas">0</span>)
    </button>
</div>

{{-- Lista de Conversas --}}
<div class="conversas-lista">
    @foreach($conversas as $conversa)
    @php
        $ultimaMensagem = $conversa->chat?->messages?->first();
        $tempoAtendimento = $conversa->atendida_em ?? $conversa->created_at;
        $diffTotal = now()->diff($tempoAtendimento);
        $diffHoras = $diffTotal->h + ($diffTotal->days * 24);
        $diffMinutos = $diffTotal->i;
    @endphp
    <div class="conversa-item" data-id="{{ $conversa->id }}">
        <div class="checkbox-col">
            <input type="checkbox" class="item-checkbox" value="{{ $conversa->id }}">
        </div>
        <div class="info">
            <div class="nome">
                <i class="fas fa-user"></i>
                {{ $conversa->cliente_nome ?? 'Cliente' }}
                <span class="badge badge-success">Em atendimento</span>
            </div>
            <div class="numero">{{ $conversa->cliente_numero }}</div>
            @if($ultimaMensagem)
            <div class="preview">
                <i class="fas fa-comment"></i>
                <span>{{ Str::limit($ultimaMensagem->message_text ?? '[midia]', 50) }}</span>
            </div>
            @endif
        </div>
        <div class="tempo">
            <div class="tempo-atendimento">
                <i class="fas fa-clock"></i>
                {{ $diffHoras }}h {{ $diffMinutos }}min
            </div>
        </div>
        <div class="acoes">
            <a href="{{ route('admin.painel', ['conversa' => $conversa->id]) }}" class="btn btn-success btn-sm">
                <i class="fas fa-comments"></i> Abrir Chat
            </a>
            <button class="btn btn-warning btn-sm btn-devolver" data-id="{{ $conversa->id }}" data-nome="{{ $conversa->cliente_nome ?? 'Cliente' }}">
                <i class="fas fa-undo"></i> Devolver
            </button>
            <button class="btn btn-success btn-sm btn-finalizar" data-id="{{ $conversa->id }}">
                <i class="fas fa-check"></i> Finalizar
            </button>
        </div>
    </div>
    @endforeach
</div>
@else
{{-- Console Vazio --}}
<div class="console-vazio">
    <i class="fas fa-inbox"></i>
    <h4>Nenhuma conversa em atendimento</h4>
    <p>Pegue conversas da fila para comecar a atender.</p>
    @if($filaCount > 0)
    <a href="{{ route('admin.fila') }}" class="btn btn-warning mt-3">
        <i class="fas fa-users"></i> Ver Fila ({{ $filaCount }})
    </a>
    @endif
</div>
@endif

{{-- Modal Devolver/Transferir --}}
<div class="modal fade" id="modalDevolver" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Devolver Conversa</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body modal-transferir">
                <p>Devolver conversa de <strong id="nomeClienteDevolver"></strong>:</p>

                <div class="mb-3">
                    <div class="atendente-item" data-id="fila">
                        <i class="fas fa-users text-warning"></i>
                        <strong>Devolver para a Fila</strong>
                        <br><small class="text-muted">A conversa voltara para a fila de espera</small>
                    </div>
                </div>

                @if($atendentes->count() > 0)
                <p class="mb-2"><strong>Ou transferir para:</strong></p>
                @foreach($atendentes as $atendente)
                <div class="atendente-item" data-id="{{ $atendente->id }}">
                    <i class="fas fa-user text-info"></i>
                    <strong>{{ $atendente->name }}</strong>
                    <br><small class="text-muted">{{ $atendente->conversas_ativas ?? 0 }} conversas ativas</small>
                </div>
                @endforeach
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" id="btnConfirmarDevolver" disabled>
                    <i class="fas fa-undo"></i> Confirmar
                </button>
            </div>
        </div>
    </div>
</div>
@stop

@section('js')
<script>
$(function() {
    var conversaIdDevolver = null;
    var atendenteIdTransferir = null;

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

        finalizarMultiplas(ids);
    });

    // Finalizar individual
    $('.btn-finalizar').on('click', function() {
        var id = $(this).data('id');
        if (!confirm('Finalizar esta conversa?')) return;
        finalizarMultiplas([id]);
    });

    function finalizarMultiplas(ids) {
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
    }

    // Devolver
    $('.btn-devolver').on('click', function() {
        conversaIdDevolver = $(this).data('id');
        var nome = $(this).data('nome');
        $('#nomeClienteDevolver').text(nome);
        atendenteIdTransferir = null;
        $('.atendente-item').removeClass('selected');
        $('#btnConfirmarDevolver').prop('disabled', true);
        $('#modalDevolver').modal('show');
    });

    // Selecionar destino
    $('.atendente-item').on('click', function() {
        $('.atendente-item').removeClass('selected');
        $(this).addClass('selected');
        atendenteIdTransferir = $(this).data('id');
        $('#btnConfirmarDevolver').prop('disabled', false);
    });

    // Confirmar devolver/transferir
    $('#btnConfirmarDevolver').on('click', function() {
        if (!conversaIdDevolver || !atendenteIdTransferir) return;

        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processando...');

        if (atendenteIdTransferir === 'fila') {
            // Devolver para fila
            $.ajax({
                url: '/admin/conversas/' + conversaIdDevolver + '/transferir',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    atendente_id: null,
                    devolver_fila: true
                },
                success: function() {
                    location.reload();
                },
                error: function() {
                    alert('Erro ao devolver');
                    btn.prop('disabled', false).html('<i class="fas fa-undo"></i> Confirmar');
                }
            });
        } else {
            // Transferir para atendente
            $.ajax({
                url: '/admin/conversas/' + conversaIdDevolver + '/transferir',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    atendente_id: atendenteIdTransferir
                },
                success: function() {
                    location.reload();
                },
                error: function() {
                    alert('Erro ao transferir');
                    btn.prop('disabled', false).html('<i class="fas fa-undo"></i> Confirmar');
                }
            });
        }
    });

    // Auto-refresh a cada 30 segundos
    setTimeout(function() { location.reload(); }, 30000);
});
</script>
@stop
