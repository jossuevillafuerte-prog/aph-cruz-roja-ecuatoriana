@php
require_once base_path('legacy/ui.php');
@endphp
@extends('security.layout')
@section('title', $next==='nueva' ? 'Nueva atención' : 'Recursos provinciales')
@section('content')
<p>Selecciona la provincia que deseas gestionar.</p>
<form method="post" action="/scope" class="field">@csrf
<input type="hidden" name="next" value="{{ $next }}">
<label for="province-choice">Provincia</label><select name="province" id="province-choice" required>
<option value="">Selecciona una provincia</option>
@foreach($provinces as $key=>$p)<option value="{{ $key }}" @selected(session('scope_province')===$key)>{{ $p['nombre'] }}</option>@endforeach
</select><div class="actions"><button class="primary emphasis">{!! icon($next==='nueva' ? 'add' : 'ambulance') !!} Continuar</button></div></form>
@endsection
