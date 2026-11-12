<?php
$query = $db->prepare("SELECT a.id, a.estado, a.datos, a.provincia, r.tipo FROM atenciones a LEFT JOIN recursos r ON r.id=a.recurso_id AND r.provincia=a.provincia WHERE (? = '' OR a.provincia=?) AND a.eliminado IS NULL");
$query->execute([$province, $province]);
$analytics = [];
foreach ($query as $row) {
    $data = json_decode($row['datos'], true, 512, JSON_THROW_ON_ERROR);
    $safe = array_intersect_key($data, array_flip(['B', 'D', 'F', 'G', 'K', 'M', 'O', 'P', 'Q', 'R', 'S', 'U', 'J', 'Z', 'AA', 'evento_lat', 'evento_lon', 'destino_lat', 'destino_lon']));
    $safe['provincia'] = $provinces[$row['provincia']]['nombre'];
    $safe['id'] = (int) $row['id'];
    $safe['estado'] = $row['estado'];
    $safe['tipo'] = $data['recurso_tipo'] ?? $row['tipo'] ?? 'Sin tipo registrado';
    $analytics[] = $safe;
}
?>
<div class="heading dashboard-heading">
    <div><p class="eyebrow">ATENCIÓN PREHOSPITALARIA</p><?php if (auth()->user()->role !== 'superadmin') { ?><h1>Panel de <?= h($territory['nombre']) ?></h1><?php } ?>
    <p class="heading-description">Selecciona una categoría o un punto del mapa para cruzar todas las estadísticas. Incluye pendientes y cerradas.</p></div>
    <div class="actions"><a class="button emphasis" href="<?= auth()->user()->role === 'superadmin' ? '/national?view=atenciones' : '/?vista=atenciones' ?>"><?= icon('list') ?> Ver atenciones</a><a class="button primary emphasis" href="<?= auth()->user()->role === 'superadmin' ? '/?vista=nueva&amp;select_province=1' : '/?vista=nueva' ?>"><?= icon('add') ?> Nueva atención</a></div>
</div>
<section class="card dashboard-controls">
    <label for="sheet-filter">Número de hoja de atención</label>
    <input id="sheet-filter" type="search" placeholder="Buscar número de hoja" autocomplete="off" aria-describedby="sheet-help">
    <div class="sheet-select-field"><label for="sheet-select">Escoger una hoja registrada</label>
    <select id="sheet-select" aria-describedby="sheet-help"><option value="">Todas las hojas</option></select>
    <small id="sheet-help">Busca para reducir la lista y selecciona el número exacto. Si un número se repite, se muestran todas sus fichas.</small></div>
    <button type="button" class="button" id="clear-dashboard">Limpiar filtros</button>
    <p id="dashboard-result" role="status" aria-live="polite"></p>
    <div id="dashboard-filters" class="actions" aria-label="Filtros activos"></div>
</section>
<div id="dashboard-metrics" class="dashboard-breakdowns"></div>
<div class="dashboard-breakdowns">
<?php foreach (['evento' => 'Mapa de direcciones del evento', 'destino' => 'Mapa de localidades de destino'] as $key => $title) { ?>
<section class="card map-card"><h2><?= h($title) ?></h2><p class="heading-description" id="map-count-<?= $key ?>"></p>
<div class="dashboard-map" data-location="<?= $key ?>" data-tiles="<?= h(getenv('APH_MAP_TILES') ?: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png') ?>" data-attribution="<?= h(getenv('APH_MAP_ATTRIBUTION') ?: '© OpenStreetMap contributors') ?>" aria-label="<?= h($title) ?>"></div></section>
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
