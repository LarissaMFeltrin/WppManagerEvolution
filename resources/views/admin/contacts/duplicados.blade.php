@extends('adminlte::page')

@section('title', 'Chats Duplicados')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1>Chats Duplicados</h1>
        <a href="{{ route('admin.contatos.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar
        </a>
    </div>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-success alert-dismissible">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            {{ session('error') }}
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-users"></i>
                Contatos com multiplos JIDs (LID / Numero)
            </h3>
        </div>
        <div class="card-body">
            <p class="text-muted">
                Quando um contato usa WhatsApp Business ou tem multiplos dispositivos, ele pode aparecer com diferentes identificadores (LID e numero real).
                Aqui voce pode mesclar esses chats para unificar o historico de mensagens.
            </p>

            @if($duplicados->isEmpty())
                <div class="alert alert-info">
                    <i class="fas fa-check-circle"></i>
                    Nenhum chat ou contato duplicado encontrado.
                </div>
            @else
                @foreach($duplicados as $grupo)
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <strong>{{ $grupo['name'] }}</strong>
                            @if($grupo['type'] === 'chat')
                                <span class="badge badge-info ml-2">{{ count($grupo['chats']) }} chats</span>
                            @else
                                <span class="badge badge-purple ml-2" style="background:#6f42c1;color:#fff">{{ count($grupo['contacts']) }} contatos</span>
                            @endif
                            <span class="badge badge-secondary ml-1">{{ $accounts[$grupo['account_id']] ?? 'Conta desconhecida' }}</span>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>JID</th>
                                        <th>Tipo</th>
                                        @if($grupo['type'] === 'chat')
                                            <th>Mensagens</th>
                                            <th>Ultima atividade</th>
                                        @else
                                            <th>Telefone</th>
                                            <th>Tem Chat?</th>
                                        @endif
                                        <th>Acao</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if($grupo['type'] === 'chat')
                                        {{-- Duplicados de CHATS --}}
                                        @foreach($grupo['chats'] as $index => $chat)
                                            <tr class="{{ $index === 0 ? 'table-success' : '' }}">
                                                <td>
                                                    <code>{{ $chat->chat_id }}</code>
                                                    @if($index === 0)
                                                        <span class="badge badge-success">Principal</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if(strlen(explode('@', $chat->chat_id)[0]) > 15)
                                                        <span class="badge badge-warning">LID</span>
                                                    @else
                                                        <span class="badge badge-primary">Numero</span>
                                                    @endif
                                                </td>
                                                <td>{{ $chat->messages_count }}</td>
                                                <td>
                                                    @if($chat->last_message_timestamp)
                                                        {{ \Carbon\Carbon::createFromTimestamp($chat->last_message_timestamp)->diffForHumans() }}
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($index > 0)
                                                        <form action="{{ route('admin.contatos.mesclar') }}" method="POST" class="d-inline" onsubmit="return confirm('Mesclar este chat com o principal?\n\nTodas as mensagens serao movidas para o chat principal e este chat sera removido.')">
                                                            @csrf
                                                            <input type="hidden" name="primary_chat_id" value="{{ $grupo['chats'][0]->id }}">
                                                            <input type="hidden" name="secondary_chat_id" value="{{ $chat->id }}">
                                                            <button type="submit" class="btn btn-sm btn-warning">
                                                                <i class="fas fa-compress-arrows-alt"></i> Mesclar
                                                            </button>
                                                        </form>
                                                    @else
                                                        <span class="text-muted">Principal</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    @else
                                        {{-- Duplicados de CONTATOS --}}
                                        @foreach($grupo['contacts'] as $index => $contact)
                                            @php
                                                $hasChat = \App\Models\Chat::where('chat_id', $contact->jid)->exists();
                                                $isLid = strlen(explode('@', $contact->jid)[0]) > 15 || str_contains($contact->jid, '@lid');
                                            @endphp
                                            <tr class="{{ $index === 0 ? 'table-success' : '' }}">
                                                <td>
                                                    <code>{{ $contact->jid }}</code>
                                                    @if($index === 0)
                                                        <span class="badge badge-success">Principal</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($isLid)
                                                        <span class="badge badge-warning">LID</span>
                                                    @else
                                                        <span class="badge badge-primary">Numero</span>
                                                    @endif
                                                </td>
                                                <td>{{ $contact->phone_number }}</td>
                                                <td>
                                                    @if($hasChat)
                                                        <span class="badge badge-success">Sim</span>
                                                    @else
                                                        <span class="badge badge-secondary">Nao</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($index > 0)
                                                        <form action="{{ route('admin.contatos.mesclar-contatos') }}" method="POST" class="d-inline" onsubmit="return confirm('Mesclar este contato com o principal?\n\nO contato secundario sera removido e o JID sera salvo como alias.')">
                                                            @csrf
                                                            <input type="hidden" name="primary_contact_id" value="{{ $grupo['contacts'][0]->id }}">
                                                            <input type="hidden" name="secondary_contact_id" value="{{ $contact->id }}">
                                                            <button type="submit" class="btn btn-sm btn-warning">
                                                                <i class="fas fa-compress-arrows-alt"></i> Mesclar
                                                            </button>
                                                        </form>
                                                    @else
                                                        <span class="text-muted">Principal</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
@stop
