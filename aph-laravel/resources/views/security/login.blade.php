@extends('security.layout')
@section('title','Inicio de sesión')
@section('content')
<p>Ingresa con tu usuario y contraseña.</p>
<form method="post" action="/login" class="field">@csrf
<label for="usuario">Usuario</label><input id="usuario" name="usuario" required maxlength="40" autocomplete="username" autocapitalize="none">
<label for="password">Contraseña</label><input id="password" name="password" type="password" required maxlength="128" autocomplete="current-password">
<button class="primary">Ingresar</button></form><p><small>Si no puedes ingresar, contacta al superadministrador nacional.</small></p>
@endsection
