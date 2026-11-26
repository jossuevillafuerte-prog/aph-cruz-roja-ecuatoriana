@extends('security.auth')
@section('title','Solicitar cuenta')
@section('content')
<p class="auth-lead">Completa tus datos. El superadministrador nacional revisará la solicitud y, si la aprueba, te enviará a tu correo institucional el enlace para activar la cuenta y crear tu contraseña.</p>
<form method="post" action="/register" class="auth-form">@csrf
<div class="auth-pair">
@foreach(['first_name' => ['Nombres', 'maxlength="100" autocomplete="given-name"'], 'last_name' => ['Apellidos', 'maxlength="100" autocomplete="family-name"']] as $field => [$fieldLabel, $attributes])
<div class="field"><label for="reg-{{ $field }}">{{ $fieldLabel }}</label><input id="reg-{{ $field }}" name="{{ $field }}" value="{{ old($field) }}" required {!! $attributes !!} @error($field) aria-invalid="true" aria-describedby="reg-{{ $field }}-error" @enderror>@error($field)<p class="field-error" id="reg-{{ $field }}-error">{{ $message }}</p>@enderror</div>
@endforeach
</div>
<div class="field"><label for="reg-province">Provincia</label><select id="reg-province" name="province" required @error('province') aria-invalid="true" aria-describedby="reg-province-error" @enderror><option value="">Selecciona tu provincia</option>@foreach($provinces as $key => $p)<option value="{{ $key }}" @selected(old('province') === $key)>{{ $p['nombre'] }}</option>@endforeach</select>@error('province')<p class="field-error" id="reg-province-error">{{ $message }}</p>@enderror</div>
<div class="field"><label for="reg-email">Correo institucional</label><input id="reg-email" name="email" type="email" value="{{ old('email') }}" required maxlength="254" autocomplete="email" aria-describedby="reg-email-help @error('email') reg-email-error @enderror" @error('email') aria-invalid="true" @enderror><small id="reg-email-help">De tu provincia, por ejemplo nombre@azuay.cruzroja.org.ec, o @cruzroja.org.ec.</small>@error('email')<p class="field-error" id="reg-email-error">{{ $message }}</p>@enderror</div>
<div class="field"><label for="reg-username">Usuario</label><input id="reg-username" name="username" value="{{ old('username') }}" required minlength="3" maxlength="40" pattern="[a-z0-9][a-z0-9._\-]{2,39}" autocapitalize="none" autocomplete="username" aria-describedby="reg-username-help @error('username') reg-username-error @enderror" @error('username') aria-invalid="true" @enderror><small id="reg-username-help">De 3 a 40 caracteres: minúsculas, números, punto, guion o guion bajo.</small>@error('username')<p class="field-error" id="reg-username-error">{{ $message }}</p>@enderror</div>
<button class="primary">Enviar solicitud</button></form>
<div class="auth-actions"><a class="button" href="{{ route('login') }}">Volver al inicio de sesión</a></div>
@endsection
