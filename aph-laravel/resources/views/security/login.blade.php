@extends('security.layout')
@section('title','Inicio de sesión')
@section('content')
<p>Ingresa con tu usuario y contraseña.</p>
<form method="post" action="/login" class="field">@csrf
<label for="usuario">Usuario</label><input id="usuario" name="usuario" value="{{ old('usuario') }}" required maxlength="40" autocomplete="username" autocapitalize="none" @if($errors->any()) aria-invalid="true" aria-describedby="form-errors" @endif>
<label for="password">Contraseña</label><input id="password" name="password" type="password" required maxlength="128" autocomplete="current-password" @if($errors->any()) aria-invalid="true" aria-describedby="form-errors" @if(old('usuario')) autofocus @endif @endif>
<button class="primary">Ingresar</button></form><p><small>Si no puedes ingresar, contacta al superadministrador nacional.</small></p>
@endsection
