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
.supervisao-header {
    background: #fff;
    padding: 12px 20px;
    border-radius: 5px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.supervisao-header .stats {
    display: flex;
    align-items: center;
    gap: 25px;
}

.supervisao-header .stats .stat-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
}

.supervisao-header .stats .stat-item i {
    color: #6c757d;
}

.supervisao-header .stats .stat-item.titulo {
    font-weight: 600;
}

.supervisao-header .stats .stat-item.titulo i {
    color: #28a745;
}

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
}

.conversa-card-header .info .nome {
    font-weight: 600;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.conversa-card-header .info .nome .tempo {
    font-size: 0.75rem;
    background: rgba(0,0,0,0.2);
    padding: 2px 8px;
    border-radius: 10px;
}

.conversa-card-header .info .numero {
    font-size: 0.8rem;
    opacity: 0.9;
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
}

.filter-select {
    min-width: 180px;
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

/* Badge de aguardando resposta */
.waiting-badge {
    position: absolute;
    top: 10px;
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
}

.waiting-badge i {
    font-size: 9px;
}

.conversa-card.cliente-aguardando {
    border: 2px solid #dc3545;
}

.conversa-card.cliente-aguardando .conversa-card-header {
    background: linear-gradient(135deg, #c0392b 0%, #e74c3c 100%);
}

/* Card sem pendencia - verde */
.conversa-card:not(.cliente-aguardando) {
    border: 2px solid #28a745;
}

.conversa-card:not(.cliente-aguardando) .status-indicator {
    background: #28a745;
}
</style>
@stop

@section('content')
{{-- Header com Stats --}}
<div class="supervisao-header">
    <div class="stats">
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
    <div>
        <select class="form-control filter-select" id="filtroAtendente">
            <option value="">Todos atendentes</option>
            @foreach($atendentes as $atendente)
                <option value="{{ $atendente->id }}">{{ $atendente->name }}</option>
            @endforeach
        </select>
    </div>
</div>

{{-- Grid de Conversas --}}
<div class="conversas-grid" id="conversasGrid">
    @forelse($conversasEmAtendimento as $conversa)
    @php
        // Tempo em atendimento
        $tempoAtendimento = $conversa->atendida_em ?? $conversa->created_at;
        $diffTotal = now()->diff($tempoAtendimento);
        $diffHoras = $diffTotal->h + ($diffTotal->days * 24);
        $diffMinutos = $diffTotal->i;

        // Formatar tempo de forma legível
        if ($diffHoras > 0) {
            $tempoFormatado = $diffHoras . 'h ' . $diffMinutos . 'min';
        } else {
            $tempoFormatado = $diffMinutos . 'min';
        }

        // Buscar últimas 10 mensagens (mais recentes primeiro para verificar aguardando)
        $mensagensRecentes = $conversa->chat?->messages()->orderBy('timestamp', 'desc')->limit(10)->get() ?? collect();
        // Inverter para exibir em ordem cronológica (antigas primeiro)
        $mensagens = $mensagensRecentes->reverse();

        // Verificar se cliente está aguardando resposta
        // Só pisca se a ÚLTIMA mensagem for do cliente (não respondida)
        $aguardandoResposta = false;
        $tempoEspera = null;
        $ultimaMensagem = $mensagensRecentes->first(); // Mais recente

        if ($ultimaMensagem && !$ultimaMensagem->is_from_me) {
            $aguardandoResposta = true;
            $tempoEspera = $ultimaMensagem->timestamp;
        }
    @endphp
    <div class="conversa-card {{ $aguardandoResposta ? 'cliente-aguardando' : '' }}"
         data-atendente="{{ $conversa->atendente_id }}"
         data-tempo-espera="{{ $tempoEspera }}"
         data-aguardando="{{ $aguardandoResposta ? '1' : '0' }}">
        {{-- Header --}}
        <div class="conversa-card-header">
            <div class="avatar">
                {{ strtoupper(substr($conversa->cliente_nome ?? 'C', 0, 2)) }}
            </div>
            <div class="info">
                <div class="nome">
                    {{ $conversa->cliente_nome ?? 'Cliente' }}
                    <span class="tempo" title="Em atendimento">{{ $tempoFormatado }}</span>
                </div>
                <div class="numero">{{ $conversa->cliente_numero }}</div>
            </div>
            <div class="status-indicator"></div>
        </div>

        {{-- Body com mensagens --}}
        <div class="conversa-card-body">
            @foreach($mensagens as $msg)
            <div class="msg-preview {{ $msg->is_from_me ? 'sent' : 'received' }}">
                @if(!$msg->is_from_me)
                <div class="sender">{{ $conversa->cliente_nome ?? 'Cliente' }}</div>
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
@stop

@section('js')
<script>
$(function() {
    // Filtro por atendente
    $('#filtroAtendente').on('change', function() {
        var atendenteId = $(this).val();

        if (atendenteId) {
            $('.conversa-card').hide();
            $('.conversa-card[data-atendente="' + atendenteId + '"]').show();
        } else {
            $('.conversa-card').show();
        }
    });

    // Auto-refresh a cada 15 segundos
    setTimeout(function() {
        location.reload();
    }, 15000);

    // Scroll automático para baixo em cada card
    $('.conversa-card-body').each(function() {
        this.scrollTop = this.scrollHeight;
    });

    // Badge de aguardando resposta
    function updateWaitingBadges() {
        $('.conversa-card').each(function() {
            var card = $(this);
            var aguardando = card.data('aguardando') == '1';
            var tempoEspera = card.data('tempo-espera');
            var badgeEl = card.find('.waiting-badge');

            if (aguardando && tempoEspera) {
                var now = Math.floor(Date.now() / 1000);
                var diff = now - tempoEspera;

                if (diff > 0) {
                    var timeStr = '';
                    if (diff < 60) {
                        timeStr = diff + 's';
                    } else if (diff < 3600) {
                        var mins = Math.floor(diff / 60);
                        timeStr = mins + 'min';
                    } else {
                        var hrs = Math.floor(diff / 3600);
                        var mins = Math.floor((diff % 3600) / 60);
                        timeStr = hrs + 'h ' + mins + 'min';
                    }

                    if (badgeEl.length === 0) {
                        card.find('.conversa-card-header').append(
                            '<span class="waiting-badge"><i class="fas fa-clock"></i> Aguardando ' + timeStr + '</span>'
                        );
                    } else {
                        badgeEl.html('<i class="fas fa-clock"></i> Aguardando ' + timeStr);
                    }
                }
            } else {
                badgeEl.remove();
            }
        });
    }

    setInterval(updateWaitingBadges, 1000);
    updateWaitingBadges();
});
</script>
@stop
