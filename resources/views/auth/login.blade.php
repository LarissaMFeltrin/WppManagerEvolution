@extends('adminlte::auth.login')

@section('auth_header', 'Entrar no Sistema')
@section('auth_body')
    <form action="{{ route('login') }}" method="post">
        @csrf
        <div class="input-group mb-3">
            <input type="text" name="login" class="form-control @error('login') is-invalid @enderror"
                   value="{{ old('login') }}" placeholder="Usuario ou E-mail" autofocus>
            <div class="input-group-append">
                <div class="input-group-text">
                    <span class="fas fa-user"></span>
                </div>
            </div>
            @error('login')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>

        <div class="input-group mb-3">
            <input type="password" name="password" class="form-control @error('password') is-invalid @enderror"
                   placeholder="Senha">
            <div class="input-group-append">
                <div class="input-group-text">
                    <span class="fas fa-lock"></span>
                </div>
            </div>
            @error('password')
                <span class="invalid-feedback">{{ $message }}</span>
            @enderror
        </div>

        <div class="row">
            <div class="col-8">
                <div class="icheck-primary">
                    <input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                    <label for="remember">Lembrar-me</label>
                </div>
            </div>
            <div class="col-4">
                <button type="submit" class="btn btn-primary btn-block">Entrar</button>
            </div>
        </div>
    </form>
@endsection
