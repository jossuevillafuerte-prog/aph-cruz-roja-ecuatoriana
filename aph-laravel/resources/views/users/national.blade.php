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
<link rel="stylesheet" href="/leaflet/leaflet.css"><script src="/leaflet/leaflet.js" defer></script><script src="/dashboard-mapas.js" defer></script>
@else
@php($provinceQuery=$province ? '&'.http_build_query(['province'=>$province]) : '')
<div class="actions"><a class="button primary emphasis" href="/?vista=nueva{{ $provinceQuery ?: '&select_province=1' }}">{!! icon('add') !!} Nueva atención</a>
@if($province)<a class="button" href="/?vista=exportar{{ $provinceQuery }}">{!! icon('download') !!} Exportar cerradas de {{ $provinces[$province]['nombre'] }} a Excel</a>
<a class="button" href="/?vista=geojson{{ $provinceQuery }}">{!! icon('pin') !!} Exportar ubicaciones de {{ $provinces[$province]['nombre'] }} (GeoJSON)</a>
@else<small>Filtra por una provincia para exportar a Excel o GeoJSON.</small>@endif</div>
<nav class="filter-bar attention-kpis" aria-label="Resumen de atenciones">
@foreach([['','all','list','Total de atenciones registradas',$total],['Cerrada','closed','check','Atenciones cerradas',$counts['Cerrada']??0],['Pendiente','pending','clock','Atenciones pendientes',$counts['Pendiente']??0]] as [$key,$style,$symbol,$label,$count])
<a class="button status emphasis {{ $style }}" data-state="{{ $key }}" data-count="{{ $count }}" href="{{ '/national?'.http_build_query(['view'=>'atenciones','province'=>$province,'estado'=>$state===$key?'':$key]) }}" aria-pressed="{{ $state===$key?'true':'false' }}">{!! icon($symbol) !!}<span>{{ $label }}<strong>{{ $count }}</strong></span></a>
@endforeach
</nav>

@if(request('eliminada'))<p class="success" role="status">Atención eliminada. La acción quedó registrada en el historial.</p>@endif
<form method="get" action="/national" class="card actions module-controls">
@if(request('view')==='atenciones')<input type="hidden" name="view" value="atenciones"><input type="hidden" name="estado" value="{{ $state }}">@endif
<label for="national-province">Filtrar por provincia</label><select id="national-province" name="province"><option value="">Todas las provincias</option>@foreach($provinces as $key=>$p)<option value="{{ $key }}" @selected($province===$key)>{{ $p['nombre'] }}</option>@endforeach</select><button class="button primary emphasis">Filtrar</button><a class="button emphasis" href="/national?view=atenciones">Ver todas</a>
</form><p>{{ $rows->total() }} fichas registradas · {{ $province ? $provinces[$province]['nombre'] : 'Todas las provincias' }}</p><div class="table"><table><thead><tr><th>Hoja</th><th>Provincia</th><th>Fecha</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>@foreach($rows as $row)@php($d=json_decode($row->datos,true))<tr class="{{ $row->estado==='Pendiente'?'attention-pending':'' }}"><td>{{ $d['K']??'Sin número' }}</td><td>{{ $provinces[$row->provincia]['nombre']??$row->provincia }}</td><td>{{ $d['B']??'' }}</td><td>{{ $row->estado }}</td><td><a class="button emphasis" href="{{ '/?'.http_build_query(['id'=>$row->id,'return_province'=>$province,'return_estado'=>$state,'return_page'=>$rows->currentPage()]) }}">{!! icon('edit') !!} Editar</a> <a class="button emphasis" href="/?vista=imprimir&id={{ $row->id }}">{!! icon('print') !!} Imprimir</a> <button type="button" class="button danger emphasis" data-delete-action="/?vista=eliminar&id={{ $row->id }}" data-version="{{ $row->version }}" data-province="{{ $row->provincia }}" data-sheet="{{ $d['K']??'Sin número' }}">{!! icon('delete') !!} Eliminar</button></td></tr>@endforeach @if($rows->isEmpty())<tr><td colspan="5">No hay atenciones registradas para este filtro. <a class="button" href="/national?view=atenciones">Ver todas las atenciones</a></td></tr>@endif</tbody></table></div>
<div class="actions">@if($rows->previousPageUrl())<a class="button emphasis" href="{{ $rows->previousPageUrl() }}">Anterior</a>@endif<span>Página {{ $rows->currentPage() }} de {{ $rows->lastPage() }}</span>@if($rows->nextPageUrl())<a class="button emphasis" href="{{ $rows->nextPageUrl() }}">Siguiente</a>@endif</div>
@endunless
@endsection
