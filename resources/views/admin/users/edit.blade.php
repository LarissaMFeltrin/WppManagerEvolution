@extends('adminlte::page')

@section('title', 'Editar Usuario')

@section('content_header')
    <h1><i class="fas fa-user-edit"></i> Editar Usuario</h1>
@stop

@section('content')
<div class="card">
    <form action="{{ route('admin.users.update', $user) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="name">Nome <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $user->name) }}" required>
                        @error('name')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="email">E-mail <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror"
                               value="{{ old('email', $user->email) }}" required>
                        @error('email')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="password">Nova Senha <small class="text-muted">(deixe em branco para manter)</small></label>
                        <input type="password" name="password" id="password"
                               class="form-control @error('password') is-invalid @enderror">
                        @error('password')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="password_confirmation">Confirmar Nova Senha</label>
                        <input type="password" name="password_confirmation" id="password_confirmation" class="form-control">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="empresa_id">Empresa</label>
                        <select name="empresa_id" id="empresa_id" class="form-control select2">
                            <option value="">Todas as empresas (Super Admin)</option>
                            @foreach($empresas as $empresa)
                                <option value="{{ $empresa->id }}"
                                    {{ old('empresa_id', $user->empresa_id) == $empresa->id ? 'selected' : '' }}>
                                    {{ $empresa->nome }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Deixe vazio para acesso a todas as empresas (Super Admin)</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="role">Perfil <span class="text-danger">*</span></label>
                        <select name="role" id="role" class="form-control" required>
                            <option value="agent" {{ old('role', $user->role) === 'agent' ? 'selected' : '' }}>Agente</option>
                            <option value="supervisor" {{ old('role', $user->role) === 'supervisor' ? 'selected' : '' }}>Supervisor</option>
                            <option value="admin" {{ old('role', $user->role) === 'admin' ? 'selected' : '' }}>Administrador</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="status_atendimento">Status</label>
                        <select name="status_atendimento" id="status_atendimento" class="form-control">
                            <option value="offline" {{ old('status_atendimento', $user->status_atendimento) === 'offline' ? 'selected' : '' }}>Offline</option>
                            <option value="online" {{ old('status_atendimento', $user->status_atendimento) === 'online' ? 'selected' : '' }}>Online</option>
                            <option value="ocupado" {{ old('status_atendimento', $user->status_atendimento) === 'ocupado' ? 'selected' : '' }}>Ocupado</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="max_conversas">Max. Conversas Simultaneas</label>
                        <input type="number" name="max_conversas" id="max_conversas" class="form-control"
                               value="{{ old('max_conversas', $user->max_conversas) }}" min="1" max="50">
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="form-group">
                        <label for="whatsapp_accounts">Instancias WhatsApp vinculadas</label>
                        <select name="whatsapp_accounts[]" id="whatsapp_accounts" class="form-control select2" multiple>
                            @foreach($whatsappAccounts as $account)
                                <option value="{{ $account->id }}"
                                    {{ in_array($account->id, $userAccounts) ? 'selected' : '' }}>
                                    {{ $account->session_name }} ({{ $account->phone_number ?: 'sem numero' }})
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Selecione quais instancias este usuario pode acessar</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Salvar</button>
        </div>
    </form>
</div>
@stop

@section('js')
<script>
$(function() {
    $('.select2').select2({ theme: 'bootstrap4' });
});
</script>
@stop
