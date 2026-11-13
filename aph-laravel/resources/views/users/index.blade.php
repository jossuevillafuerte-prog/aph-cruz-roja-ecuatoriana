@php
require_once base_path('legacy/ui.php');
@endphp
@extends('security.layout')
@section('title','Usuarios nacionales')
@section('content')

<div class="filter-bar"><a class="button emphasis status all" href="/users">{!! icon('list') !!} Todos</a><a class="button emphasis status closed" href="/users?state=active">{!! icon('check') !!} Activos: {{ $totals['active'] }}</a><a class="button emphasis status pending" href="/users?state=inactive">{!! icon('clock') !!} Inactivos: {{ $totals['inactive'] }}</a></div>
<form method="get" class="grid"><div class="field"><label>Buscar usuario, nombre o correo</label><input name="q" value="{{ request('q') }}"></div><div class="field"><label>Provincia</label><select name="province"><option value="">Todas</option>@foreach($provinces as $key=>$p)<option value="{{ $key }}" @selected(request('province')===$key)>{{ $p['nombre'] }}</option>@endforeach</select></div><div class="field"><label>Estado</label><select name="state"><option value="">Todos</option><option value="active" @selected(request('state')==='active')>Activos</option><option value="inactive" @selected(request('state')==='inactive')>Inactivos</option><option value="pending" @selected(request('state')==='pending')>Pendientes de activación</option></select></div><button class="emphasis">{!! icon('list') !!} Filtrar</button></form>
<p class="notice">Envío de invitaciones: {{ config('institution.invitations_enabled') ? 'habilitado' : 'pendiente de configurar correo y URL pública' }}. Los correos JP no se asignan a ninguna cuenta.</p>
<details class="card"><summary>{!! icon('add') !!} Crear técnico provincial APH</summary><form method="post" action="/users" class="grid">@csrf
<div class="field"><label>Nombre</label><input name="name" required maxlength="200" value="{{ old('name') }}"></div><div class="field"><label>Usuario</label><input name="username" required maxlength="40" value="{{ old('username') }}" autocapitalize="none"></div><div class="field"><label>Correo institucional</label><input type="email" name="email" required value="{{ old('email') }}"></div><div class="field"><label>Provincia</label><select name="province" required>@foreach($provinces as $key=>$p)<option value="{{ $key }}">{{ $p['nombre'] }}</option>@endforeach</select></div><button class="primary">Crear cuenta pendiente</button></form></details>
<div class="table users-table"><table><thead><tr><th>Nombre y usuario</th><th>Rol / provincia</th><th>Correo</th><th>Estado</th><th>Último ingreso</th><th>Acciones</th></tr></thead><tbody>
@forelse($users as $u)<tr><td>{{ $u->name }}<br><strong>{{ $u->username }}</strong></td><td>{{ $u->role==='superadmin'?'Superadministrador nacional':'Técnico provincial APH' }}<br>{{ $provinces[$u->province]['nombre']??'Nacional' }}</td><td class="user-email">@if($u->email)@php($emailParts=explode('@',$u->email,2))<span>{{ $emailParts[0] }}</span><span>{{ '@'.($emailParts[1]??'') }}</span>@else Sin correo registrado @endif<br><small>{{ $u->email_verified_at?'Verificado':($u->legacy_access?'Sin verificar · acceso temporal autorizado':'Pendiente de verificación') }}</small></td><td><span class="badge status {{ $u->active?'closed':'pending' }}">{{ $u->active?'Activo':'Inactivo' }}</span>@if(!$u->legacy_access&&!$u->email_verified_at)<br><small>Pendiente de activación</small>@endif</td><td>{{ $u->last_login_at??'Sin ingreso' }}</td><td>
@if((string)$u->id!==(string)auth()->id())<form method="post" action="/users/{{ $u->id }}">@csrf<input type="hidden" name="action" value="state"><input type="hidden" name="active" value="{{ $u->active?0:1 }}"><button>{{ $u->active?'Desactivar':'Activar' }}</button></form>@endif
<button type="button" class="button emphasis edit-account" data-id="{{ $u->id }}" data-first-name="{{ $u->first_name ?? $u->name }}" data-last-name="{{ $u->last_name ?? '' }}" data-email="{{ $u->email ?? '' }}" data-role="{{ $u->role }}" data-province="{{ $u->province ?? '' }}">{!! icon('edit') !!} Editar datos</button>
@if(!$u->email_verified_at)<form method="post" action="/users/{{ $u->id }}/invite">@csrf<button>Reenviar activación</button></form>@endif
</td></tr>@empty<tr><td colspan="6">No hay usuarios para estos filtros.</td></tr>@endforelse
</tbody></table></div><div class="actions">@if($users->previousPageUrl())<a class="button emphasis" href="{{ $users->previousPageUrl() }}">Anterior</a>@endif<span>Página {{ $users->currentPage() }} de {{ $users->lastPage() }}</span>@if($users->nextPageUrl())<a class="button emphasis" href="{{ $users->nextPageUrl() }}">Siguiente</a>@endif</div>

<dialog id="user-editor" class="editor-dialog" aria-labelledby="user-editor-title">
<div class="menu-heading"><h2 id="user-editor-title">Editar usuario</h2><button type="button" class="icon-button" data-close-editor aria-label="Cerrar edición">{!! icon('close') !!}</button></div>
<p>Deja la contraseña vacía para conservarla. Cambiar provincia o rol no mueve las fichas históricas. Al guardar se cerrarán las sesiones anteriores de la cuenta.</p>
<form id="user-edit-form" method="post" data-reopen="{{ old('edit_user_id') }}">@csrf
<input type="hidden" name="action" value="edit"><input type="hidden" name="edit_user_id" value="{{ old('edit_user_id') }}">
<div class="grid">
<label>Nombre<input name="first_name" value="{{ old('first_name') }}" maxlength="100" required autocomplete="given-name"></label>
<label>Apellido<input name="last_name" value="{{ old('last_name') }}" maxlength="100" autocomplete="family-name"></label>
<label>Correo electrónico<input type="email" name="email" value="{{ old('email') }}" maxlength="254" autocomplete="email"></label>
<label>Contraseña<input type="password" name="password" maxlength="72" autocomplete="new-password" aria-describedby="edit-password-help"></label>
<label>Rol<select name="role"><option value="technician" @selected(old('role')==='technician')>Técnico provincial APH</option><option value="superadmin" @selected(old('role')==='superadmin')>Superadministrador nacional</option></select></label>
<label>Provincia<select name="province"><option value="">Nacional / selecciona provincia</option>@foreach($provinces as $key=>$p)<option value="{{ $key }}" @selected(old('province')===$key)>{{ $p['nombre'] }}</option>@endforeach</select></label>
</div><small id="edit-password-help">{{ \App\Security\PasswordPolicy::DESCRIPTION }}</small>
@if($errors->any() && old('action')==='edit')<div class="errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<div class="actions"><button type="button" class="button emphasis" data-close-editor>{!! icon('close') !!} Cancelar</button><button class="primary emphasis">{!! icon('check') !!} Guardar cambios</button></div>
</form></dialog><script src="/user-editor.js" defer></script>
@endsection
