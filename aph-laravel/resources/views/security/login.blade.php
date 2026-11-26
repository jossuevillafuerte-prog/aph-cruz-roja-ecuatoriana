@extends('security.auth')
@section('title','Inicio de sesión')
@section('content')
<p class="auth-lead">Ingresa con tu usuario y contraseña.</p>
<form method="post" action="/login" class="auth-form">@csrf
<div class="field"><label for="usuario">Usuario</label><input id="usuario" name="usuario" value="{{ old('usuario') }}" required maxlength="40" autocomplete="username" autocapitalize="none" @if($errors->any()) aria-invalid="true" aria-describedby="form-errors" @endif></div>
<div class="field"><label for="password">Contraseña</label><input id="password" name="password" type="password" required maxlength="128" autocomplete="current-password" @if($errors->any()) aria-invalid="true" aria-describedby="form-errors" @if(old('usuario')) autofocus @endif @endif></div>
<button class="primary">Ingresar</button></form>
<div class="auth-divider"><span>¿No tienes cuenta o buscas los datos?</span></div>
<div class="auth-actions">
<a class="button" href="{{ route('register') }}">Solicitar cuenta</a>
@if(str_starts_with((string) config('institution.powerbi_url'), 'https://'))<a class="button" href="{{ config('institution.powerbi_url') }}" target="_blank" rel="noopener noreferrer">Ver tablero Power BI<span class="visually-hidden"> (abre en otra pestaña)</span></a>@endif
</div>
<p class="auth-help"><small>¿Olvidaste tu contraseña? Contacta al superadministrador nacional.</small></p>
@endsection
