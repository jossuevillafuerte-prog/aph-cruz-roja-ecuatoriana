@php
// Fotos del collage: cualquier JPG, PNG o WebP en public/collage (máximo 5, en orden alfabético).
$photos = collect(glob(public_path('collage/*')) ?: [])->filter(fn ($file) => preg_match('/\.(jpe?g|png|webp)\z/i', $file))->sort()->take(5)->map(fn ($file) => \App\Support\Asset::url('collage/'.basename($file)))->values();
$pictograms = ['Emergency-Medical-Services', 'Volunteers', 'Health', 'Rapid-timely', 'Participation-team'];
@endphp
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>@yield('title') · APH</title><link rel="stylesheet" href="{{ \App\Support\Asset::url('/estilos.css') }}"><script src="{{ \App\Support\Asset::url('/form-feedback.js') }}" defer></script></head>
<body class="auth-page">
<div class="auth-layout">
<aside class="auth-visual" aria-label="Cruz Roja Ecuatoriana en territorio">
<div class="auth-collage {{ $photos->isEmpty() ? 'is-pictograms' : 'has-'.$photos->count() }}">
@forelse($photos as $photo)<img src="{{ $photo }}" alt="" loading="{{ $loop->first ? 'eager' : 'lazy' }}" decoding="async">
@empty @foreach($pictograms as $pictogram)<span><img src="/marca/{{ $pictogram }}.png" alt=""></span>@endforeach
@endforelse
</div>
<div class="auth-caption"><p class="auth-caption-title">Atención prehospitalaria</p><p>Registro nacional de atenciones de la Cruz Roja Ecuatoriana.</p></div>
</aside>
<main class="auth-panel"><div class="auth-card">
<a class="auth-brand" href="/login"><img src="/marca/cre-institucional.png" alt="Cruz Roja Ecuatoriana"><span>Registro APH</span></a>
<h1>@yield('title')</h1>
@if(session('notice'))<p class="success" role="status">{{ session('notice') }}</p>@endif
@if($errors->any())<div class="errors" role="alert" id="form-errors" tabindex="-1">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
@yield('content')
</div></main>
</div>
</body></html>
