@extends('adminlte::page')

@section('title', 'Instancias WhatsApp')

@section('content_header')
    <div class="d-flex justify-content-between">
        <h1><i class="fab fa-whatsapp"></i> Instancias WhatsApp</h1>
        <a href="{{ route('admin.whatsapp.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nova Instancia
        </a>
    </div>
@stop

@section('content')
<div class="card">
    <div class="card-body table-responsive p-0">
        <table class="table table-hover table-striped">
            <thead>
                <tr>
                    <th>Nome da Sessao</th>
                    <th>Empresa</th>
                    <th>Telefone</th>
                    <th>Status</th>
                    <th>Ultima Conexao</th>
                    <th width="200">Acoes</th>
                </tr>
            </thead>
            <tbody>
                @forelse($accounts as $account)
                <tr>
                    <td>{{ $account->session_name }}</td>
                    <td>{{ $account->empresa?->nome ?? '-' }}</td>
                    <td>{{ $account->phone_number ?? '-' }}</td>
                    <td>
                        @if($account->is_connected)
                            <span class="badge badge-success"><i class="fas fa-check"></i> Conectado</span>
                        @else
                            <span class="badge badge-danger"><i class="fas fa-times"></i> Desconectado</span>
                        @endif
                        @if(!$account->is_active)
                            <span class="badge badge-secondary">Inativo</span>
                        @endif
                    </td>
                    <td>{{ $account->last_connection?->diffForHumans() ?? 'Nunca' }}</td>
                    <td>
                        @if(!$account->is_connected)
                            <button class="btn btn-sm btn-success btn-qrcode" data-id="{{ $account->id }}" data-phone="{{ $account->phone_number }}">
                                <i class="fas fa-qrcode"></i> Conectar
                            </button>
                        @else
                            <form action="{{ route('admin.whatsapp.restart', $account) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-info" title="Reconectar">
                                    <i class="fas fa-sync"></i>
                                </button>
                            </form>
                            <form action="{{ route('admin.whatsapp.disconnect', $account) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-warning" title="Desconectar">
                                    <i class="fas fa-sign-out-alt"></i>
                                </button>
                            </form>
                        @endif
                        <a href="{{ route('admin.whatsapp.edit', $account) }}" class="btn btn-sm btn-info">
                            <i class="fas fa-edit"></i>
                        </a>
                        <form action="{{ route('admin.whatsapp.destroy', $account) }}" method="POST" class="d-inline"
                              onsubmit="return confirm('Tem certeza?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">
                        Nenhuma instancia cadastrada
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($accounts->hasPages())
    <div class="card-footer">
        {{ $accounts->links('pagination::bootstrap-4') }}
    </div>
    @endif
</div>

{{-- Modal Conexao --}}
<div class="modal fade" id="qrcodeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Conectar Instancia</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                {{-- Tabs para escolher metodo --}}
                <ul class="nav nav-tabs mb-3" id="connectTabs">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="#tabQrCode">
                            <i class="fas fa-qrcode"></i> QR Code
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#tabPairingCode">
                            <i class="fas fa-mobile-alt"></i> Codigo de Pareamento
                        </a>
                    </li>
                </ul>

                <div class="tab-content">
                    {{-- Tab QR Code --}}
                    <div class="tab-pane fade show active text-center" id="tabQrCode">
                        <div id="qrcode-container">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">Carregando...</span>
                            </div>
                            <p class="mt-2">Gerando QR Code...</p>
                        </div>
                        <p class="text-muted small mt-3">
                            Abra o WhatsApp no celular > Menu > Aparelhos conectados > Conectar aparelho
                        </p>
                    </div>

                    {{-- Tab Pairing Code --}}
                    <div class="tab-pane fade" id="tabPairingCode">
                        <div class="form-group">
                            <label>Numero do WhatsApp (com DDI)</label>
                            <input type="text" id="pairingPhone" class="form-control" placeholder="5511999998888">
                            <small class="text-muted">Digite o numero completo com codigo do pais</small>
                        </div>
                        <button type="button" class="btn btn-success btn-block" id="btnRequestPairingCode">
                            <i class="fas fa-key"></i> Gerar Codigo de Pareamento
                        </button>
                        <div id="pairing-result" class="mt-3" style="display:none;">
                            <div class="alert alert-success text-center">
                                <h4 class="mb-2">Codigo de Pareamento:</h4>
                                <h2 id="pairingCodeDisplay" class="font-weight-bold" style="letter-spacing: 5px;"></h2>
                                <p class="mb-0 mt-2 small">
                                    Abra o WhatsApp > Menu > Aparelhos conectados > Conectar aparelho > Conectar com numero
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@stop

@section('js')
<script>
$(function() {
    var currentAccountId = null;
    var currentPhone = null;

    $('.btn-qrcode').click(function() {
        currentAccountId = $(this).data('id');
        currentPhone = $(this).data('phone') || '';

        // Reset modal
        $('#qrcode-container').html('<div class="spinner-border text-primary"></div><p class="mt-2">Gerando QR Code...</p>');
        $('#pairing-result').hide();
        $('#pairingPhone').val(currentPhone);
        $('#connectTabs a:first').tab('show');

        $('#qrcodeModal').modal('show');
        loadQrCode();
    });

    function loadQrCode() {
        $.get('/admin/whatsapp/' + currentAccountId + '/qrcode')
            .done(function(data) {
                var base64 = null;

                // Extrair base64 de diferentes formatos de resposta
                if (data.data && data.data.qrcode && data.data.qrcode.base64) {
                    base64 = data.data.qrcode.base64;
                } else if (data.data && data.data.base64) {
                    base64 = data.data.base64;
                } else if (data.qrcode) {
                    base64 = data.qrcode;
                } else if (data.base64) {
                    base64 = data.base64;
                }

                if (base64) {
                    // Se já tem o prefixo data:image, usar direto
                    var src = base64.startsWith('data:') ? base64 : 'data:image/png;base64,' + base64;
                    $('#qrcode-container').html('<img src="' + src + '" class="img-fluid" style="max-width: 300px;">');
                } else {
                    $('#qrcode-container').html('<div class="alert alert-warning">QR Code nao disponivel. <button class="btn btn-sm btn-link" onclick="loadQrCode()">Tentar novamente</button></div>');
                }
            })
            .fail(function(xhr) {
                $('#qrcode-container').html('<div class="alert alert-danger">Erro ao gerar QR Code: ' + (xhr.responseJSON?.error || 'Erro desconhecido') + '</div>');
            });
    }

    $('#btnRequestPairingCode').click(function() {
        var phone = $('#pairingPhone').val().replace(/\D/g, '');

        if (phone.length < 10) {
            alert('Digite um numero de telefone valido');
            return;
        }

        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Gerando...');

        $.ajax({
            url: '/admin/whatsapp/' + currentAccountId + '/pairing-code',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                phone_number: phone
            }
        })
        .done(function(data) {
            if (data.success && data.data && data.data.pairingCode) {
                $('#pairingCodeDisplay').text(data.data.pairingCode);
                $('#pairing-result').show();
            } else if (data.data && data.data.code) {
                $('#pairingCodeDisplay').text(data.data.code);
                $('#pairing-result').show();
            } else {
                alert('Codigo de pareamento nao disponivel. Tente usar o QR Code.');
            }
        })
        .fail(function(xhr) {
            alert('Erro: ' + (xhr.responseJSON?.error || 'Erro desconhecido'));
        })
        .always(function() {
            btn.prop('disabled', false).html('<i class="fas fa-key"></i> Gerar Codigo de Pareamento');
        });
    });

    // Atualizar QR Code quando trocar de tab
    $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
        if ($(e.target).attr('href') === '#tabQrCode') {
            loadQrCode();
        }
    });
});
</script>
@stop
