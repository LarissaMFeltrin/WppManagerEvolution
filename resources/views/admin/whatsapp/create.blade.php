@extends('adminlte::page')

@section('title', 'Nova Instancia')

@section('content_header')
    <h1><i class="fab fa-whatsapp"></i> Nova Instancia WhatsApp</h1>
@stop

@section('content')
<div class="card">
    <form action="{{ route('admin.whatsapp.store') }}" method="POST">
        @csrf
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="session_name">Nome da Sessao <span class="text-danger">*</span></label>
                        <input type="text" name="session_name" id="session_name"
                               class="form-control @error('session_name') is-invalid @enderror"
                               value="{{ old('session_name') }}" required
                               placeholder="ex: minha-instancia">
                        @error('session_name')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                        <small class="text-muted">Use apenas letras, numeros e hifen</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="empresa_id">Empresa <span class="text-danger">*</span></label>
                        <select name="empresa_id" id="empresa_id" class="form-control select2" required>
                            <option value="">Selecione...</option>
                            @foreach($empresas as $empresa)
                                <option value="{{ $empresa->id }}" {{ old('empresa_id') == $empresa->id ? 'selected' : '' }}>
                                    {{ $empresa->nome }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="phone_number">Numero do WhatsApp</label>
                        <input type="text" name="phone_number" id="phone_number"
                               class="form-control @error('phone_number') is-invalid @enderror"
                               value="{{ old('phone_number') }}"
                               placeholder="ex: 5544999998888">
                        @error('phone_number')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                        <small class="text-muted">Codigo do pais + DDD + numero (opcional, sera preenchido ao conectar)</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <div class="custom-control custom-switch mt-4">
                            <input type="checkbox" name="is_active" value="1" class="custom-control-input"
                                   id="is_active" checked>
                            <label class="custom-control-label" for="is_active">Instancia Ativa</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <a href="{{ route('admin.whatsapp.index') }}" class="btn btn-secondary">Cancelar</a>
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
