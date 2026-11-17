<?php
$query = $db->prepare("SELECT a.id, a.estado, a.datos, a.provincia, r.tipo FROM atenciones a LEFT JOIN recursos r ON r.id=a.recurso_id AND r.provincia=a.provincia WHERE (? = '' OR a.provincia=?) AND a.eliminado IS NULL");
$query->execute([$province, $province]);
$analytics = [];
foreach ($query as $row) {
    $data = json_decode($row['datos'], true, 512, JSON_THROW_ON_ERROR);
    $safe = array_intersect_key($data, array_flip(['B', 'D', 'F', 'G', 'K', 'M', 'O', 'P', 'Q', 'R', 'S', 'J', 'Z', 'AA', 'evento_lat', 'evento_lon', 'destino_lat', 'destino_lon']));
    $safe['provincia'] = $provinces[$row['provincia']]['nombre'];
    $safe['id'] = (int) $row['id'];
    $safe['estado'] = $row['estado'];
    $safe['tipo'] = $data['recurso_tipo'] ?? $row['tipo'] ?? 'Sin tipo registrado';
    $analytics[] = $safe;
}
?>
<?php if (auth()->user()->role !== 'superadmin') { ?><h1>Panel principal</h1><?php } ?>
<nav class="dashboard-shortcuts" aria-label="Accesos directos">
<a class="button emphasis" href="/?vista=perfil"><?= icon('profile') ?><span>Mi Perfil</span></a>
<?php if (auth()->user()->role === 'superadmin') { ?><a class="button emphasis" href="/users"><?= icon('team') ?><span>Usuarios Nacionales</span></a><?php } ?>
<a class="button emphasis" href="<?= auth()->user()->role === 'superadmin' ? '/?vista=recursos&amp;select_province=1' : '/?vista=recursos' ?>"><?= icon('ambulance') ?><span>Recursos</span></a>
<a class="button emphasis" href="<?= auth()->user()->role === 'superadmin' ? '/national?view=atenciones' : '/?vista=atenciones' ?>"><?= icon('list') ?><span>Atenciones</span></a>
</nav>
<div id="dashboard-filters" class="actions" aria-label="Filtros activos" hidden></div>
<div id="dashboard-metrics" class="dashboard-breakdowns"></div>
<div class="dashboard-breakdowns">
<?php foreach (['evento' => 'Mapa de direcciones del evento', 'destino' => 'Mapa de localidades de destino'] as $key => $title) { ?>
<section class="card map-card"><h2><?= h($title) ?></h2><p class="heading-description" id="map-count-<?= $key ?>"></p>
<div class="dashboard-map" data-location="<?= $key ?>" data-tiles="<?= h(config('maps.tiles')) ?>" data-attribution="<?= h(config('maps.attribution')) ?>" aria-label="<?= h($title) ?>"></div></section>
<?php } ?>
</div>
<?php if (auth()->user()->role === 'superadmin') { ?>
<section class="card province-chart"><h2>Atenciones por provincia · Ecuador</h2><p>Más atenciones, mayor intensidad de rojo. Selecciona una provincia para filtrar los gráficos. Incluye pendientes y cerradas; excluye eliminadas.</p>
<div class="province-map-grid"><div id="ecuador-map" aria-label="Mapa de provincias del Ecuador continental"></div><div><h3>Galápagos</h3><div id="galapagos-map" aria-label="Mapa de Galápagos"></div></div></div>
<div id="province-legend" class="actions" aria-label="Escala de atenciones"></div><p id="province-map-status" role="status">Cargando límites provinciales…</p><div id="province-counts" class="province-counts"></div>
<small>Cartografía de referencia: <a href="https://www.geoboundaries.org/api.html" target="_blank" rel="noopener">geoBoundaries</a>, Ecuador ADM1, límites representados en 2011, CC0. Conteos actualizados a partir de las fichas del sistema.</small></section>
<?php } ?>
<div id="dashboard-charts" class="dashboard-breakdowns"></div>
<noscript><p class="errors">Activa JavaScript para consultar los gráficos y filtros interactivos.</p></noscript>
<script id="dashboard-data" type="application/json"><?= json_encode($analytics, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
