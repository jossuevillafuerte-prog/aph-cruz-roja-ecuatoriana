<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>Acceso seguro · APH</title><link rel="stylesheet" href="/estilos.css">@auth<script src="/session-security.js" defer></script><script src="/catalogo.js" defer></script>@endauth</head><body><header class="app-header"><div class="header-inner">@auth @php
require_once base_path('legacy/ui.php');
@endphp<div class="header-brand"><button type="button" id="menu-open" class="icon-button" aria-label="Abrir menú" aria-controls="app-menu" aria-expanded="false">{!! icon('menu') !!}</button>@endauth<a class="identity" href="{{ auth()->user()?->role === 'superadmin' ? '/national' : '/' }}"><img src="/marca/cre-institucional.png" alt="Cruz Roja Ecuatoriana"><span>Registro APH</span></a>@auth</div><div class="header-tools"><a class="profile-link" href="/?vista=perfil">{!! icon('profile') !!}<span><strong>{{ auth()->user()->name }}</strong><small>Mi perfil</small></span></a></div>@endauth</div></header>
@auth
<dialog id="app-menu" class="app-menu" aria-labelledby="menu-title"><div class="menu-heading"><h2 id="menu-title">Registro APH</h2><button type="button" id="menu-close" class="icon-button" aria-label="Cerrar menú">{!! icon('close') !!}</button></div>
<nav aria-label="Menú principal">
<a class="button emphasis" href="{{ auth()->user()->role==='superadmin' ? '/national' : '/' }}">{!! icon('dashboard') !!} Panel principal</a>
<a class="button emphasis" href="{{ auth()->user()->role==='superadmin' ? '/national?view=atenciones' : '/?vista=atenciones' }}">{!! icon('list') !!} Atenciones</a>
<a class="button emphasis" href="{{ auth()->user()->role==='superadmin' ? '/?vista=recursos&select_province=1' : '/?vista=recursos' }}">{!! icon('ambulance') !!} Recursos</a>
<a class="button emphasis" href="/?vista=perfil">{!! icon('profile') !!} Mi perfil</a>
@if(auth()->user()->role==='superadmin')<a class="button emphasis" href="/users">{!! icon('team') !!} Usuarios nacionales</a>@endif
<a class="button emphasis" href="/security">{!! icon('check') !!} Seguridad</a>
</nav><form method="post" action="/logout">@csrf<button class="button">{!! icon('exit') !!} Cerrar sesión</button></form></dialog>
@endauth<main class="{{ request()->is('users*','national') ? '' : 'login' }}"><div class="card"><h1>@yield('title')</h1>@if(session('notice'))<p role="status">{{ session('notice') }}</p>@endif @if($errors->any())<div class="errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif @yield('content')</div>@auth<form method="post" action="/logout">@csrf<button class="button">Cerrar sesión</button></form>@endauth</main>@auth @php
require base_path('legacy/delete-dialog.php');
@endphp @endauth</body></html>
