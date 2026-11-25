<?php
$period = dashboardPeriod(request()->query());
['total' => $periodTotal, 'rows' => $dashboardRows] = dashboardRecords($db, $province, $period);
$dashboardPath = auth()->user()->role === 'superadmin' ? '/national' : '/';
$dashboardRecord = static function (array $row) use ($provinces): string {
    $data = json_decode($row['datos'], true, 512, JSON_THROW_ON_ERROR);
    $type = $data['recurso_tipo'] ?? $row['tipo'] ?? 'Sin tipo registrado';
    unset($data['recurso_tipo']);

    return json_encode(['provincia' => $provinces[$row['provincia']]['nombre'] ?? $row['provincia'], 'id' => (int) $row['id'], 'estado' => $row['estado'], 'tipo' => $type] + array_filter($data, static fn ($value): bool => $value !== null && $value !== ''),
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
};
?>
<?php if (auth()->user()->role !== 'superadmin') { ?><h1>Panel principal</h1><?php } ?>
<nav class="dashboard-shortcuts" aria-label="Accesos directos">
<a class="button emphasis" href="/?vista=perfil"><?= icon('profile') ?><span>Mi Perfil</span></a>
<?php if (auth()->user()->role === 'superadmin') { ?><a class="button emphasis" href="/users"><?= icon('team') ?><span>Usuarios Nacionales</span></a><?php } ?>
<a class="button emphasis" href="<?= auth()->user()->role === 'superadmin' ? '/?vista=recursos&amp;select_province=1' : '/?vista=recursos' ?>"><?= icon('ambulance') ?><span>Recursos</span></a>
<a class="button emphasis" href="<?= auth()->user()->role === 'superadmin' ? '/national?view=atenciones' : '/?vista=atenciones' ?>"><?= icon('list') ?><span>Atenciones</span></a>
</nav>
<form method="get" action="<?= $dashboardPath ?>" class="card actions module-controls">
<label for="period-from">Desde</label><input id="period-from" type="date" name="desde" value="<?= h($period['desde']) ?>" required>
<label for="period-to">Hasta</label><input id="period-to" type="date" name="hasta" value="<?= h($period['hasta']) ?>" required>
<button class="button primary emphasis">Aplicar periodo</button>
<a class="button emphasis" href="<?= $dashboardPath ?>">Últimos 12 meses</a>
<a class="button emphasis" href="<?= $dashboardPath ?>?periodo=todo">Todo el historial</a>
</form>
<?php if ($period['error'] !== '') { ?><p class="errors" role="alert"><?= h($period['error']) ?></p><?php } ?>
<?php if ($periodTotal > DASHBOARD_LIMIT) { ?><p class="errors" role="alert">Hay <?= h(number_format($periodTotal, 0, ',', '.')) ?> atenciones en el periodo y el panel muestra las <?= h(number_format(DASHBOARD_LIMIT, 0, ',', '.')) ?> más recientes. Acorta el periodo para ver totales exactos.</p><?php } ?>
<p class="heading-description" id="period-summary"><?= $period['desde'] === '' ? 'Todo el historial' : 'Atenciones del '.h($period['desde']).' al '.h($period['hasta']) ?>: <?= h(number_format($periodTotal, 0, ',', '.')) ?>.</p>
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
<div id="dashboard-charts" class="dashboard-breakdowns" tabindex="-1" aria-label="Gráficos"></div>
<noscript><p class="errors">Activa JavaScript para consultar los gráficos y filtros interactivos.</p></noscript>
<script id="dashboard-data" type="application/json">[<?php $separator = '';
foreach ($dashboardRows as $row) {
    echo $separator, $dashboardRecord($row);
    $separator = ',';
} ?>]</script>
