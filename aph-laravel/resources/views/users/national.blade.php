@php
require_once base_path('legacy/ui.php');
@endphp
@extends('security.layout')
@section('title',request('view')==='atenciones' ? 'Atenciones nacionales' : 'Panel principal')
@section('content')
@unless(request('view')==='atenciones')
@php
require_once base_path('legacy/modelo.php');require_once base_path('legacy/ui.php');
$db=database();$territory=['nombre'=>$province ? $provinces[$province]['nombre'] : 'todo el país'];
ob_start();require base_path('legacy/dashboard-vista.php');$dashboard=ob_get_clean();
@endphp
{!! $dashboard !!}
@else
<a class="button back-link emphasis" href="/national">{!! icon('back') !!} Volver al panel principal</a>
@if(request('eliminada'))<p class="success" role="status">Atención eliminada. La acción quedó registrada en el historial.</p>@endif
<form method="get" action="/national" class="actions">
@if(request('view')==='atenciones')<input type="hidden" name="view" value="atenciones">@endif
<label for="national-province">Filtrar por provincia</label><select id="national-province" name="province"><option value="">Todas las provincias</option>@foreach($provinces as $key=>$p)<option value="{{ $key }}" @selected($province===$key)>{{ $p['nombre'] }}</option>@endforeach</select><button class="button primary emphasis">Filtrar</button><a class="button emphasis" href="/national?view=atenciones">Ver todas</a>
</form><p>{{ $rows->total() }} fichas registradas · {{ $province ? $provinces[$province]['nombre'] : 'Todas las provincias' }}</p><div class="table"><table><thead><tr><th>Hoja</th><th>Provincia</th><th>Fecha</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>@foreach($rows as $row)@php($d=json_decode($row->datos,true))<tr><td>{{ $d['K']??'Sin número' }}</td><td>{{ $provinces[$row->provincia]['nombre']??$row->provincia }}</td><td>{{ $d['B']??'' }}</td><td>{{ $row->estado }}</td><td><a class="button emphasis" href="/?id={{ $row->id }}">{!! icon('edit') !!} Editar</a> <a class="button emphasis" href="/?vista=imprimir&id={{ $row->id }}">{!! icon('print') !!} Imprimir</a> <button type="button" class="button danger emphasis" data-delete-action="/?vista=eliminar&id={{ $row->id }}" data-version="{{ $row->version }}" data-province="{{ $row->provincia }}" data-sheet="{{ $d['K']??'Sin número' }}">{!! icon('delete') !!} Eliminar</button></td></tr>@endforeach @if($rows->isEmpty())<tr><td colspan="5">No hay atenciones registradas para este filtro.</td></tr>@endif</tbody></table></div>
<div class="actions">@if($rows->previousPageUrl())<a class="button emphasis" href="{{ $rows->previousPageUrl() }}">Anterior</a>@endif<span>Página {{ $rows->currentPage() }} de {{ $rows->lastPage() }}</span>@if($rows->nextPageUrl())<a class="button emphasis" href="{{ $rows->nextPageUrl() }}">Siguiente</a>@endif</div>
@endunless
<link rel="stylesheet" href="/leaflet/leaflet.css"><script src="/leaflet/leaflet.js" defer></script><script src="/dashboard-mapas.js" defer></script>
@endsection
