<?php
$printLabels = ['K' => 'Número de hoja', 'B' => 'Fecha de atención', 'I' => 'Hora de llegada', 'F' => 'Evento o causa', 'G' => 'Cantón', 'C' => 'Código APH', 'D' => 'Tipo de atención', 'E' => 'Categoría de atención', 'L' => 'Nombres y apellidos del paciente', 'M' => 'Tipo de identificación', 'N' => 'Número de identificación', 'O' => 'Sexo', 'P' => 'Edad', 'Q' => 'Nacionalidad', 'R' => 'Condición de salud', 'S' => 'Discapacidad', 'T' => 'Diagnóstico descrito', 'U' => 'Código CIE-10', 'V' => 'Descripción CIE-10', 'J' => 'Recurso despachado', 'H' => 'Dirección del evento', 'W' => 'Localidad de destino', 'X' => 'Médico receptor', 'Z' => 'Kilometraje de salida', 'AA' => 'Kilometraje de llegada', 'AB' => 'Combustible de salida', 'AC' => 'Combustible de llegada', 'Y' => 'Responsable', 'AD' => 'Observaciones', 'AE' => 'Zona afectada'];
$sheetNumber = $data['K'] ?: 'Sin número de hoja';
$printGroups = [
    'Datos del servicio' => ['K', 'B', 'I', 'F', 'G', 'C', 'D', 'E'],
    'Paciente y atención' => ['L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'AE', 'T', 'U', 'V'],
    'Despacho y traslado' => ['J', 'H', 'W', 'X', 'Z', 'AA', 'AB', 'AC'],
    'Responsabilidad y observaciones' => ['Y', 'AD'],
];
?>
<div class="actions print-controls">
    <a class="button back-link" href="?vista=atenciones"><?= icon('back') ?> Volver a atenciones</a>
    <button class="primary" id="imprimir" disabled><?= icon('print') ?> Preparando mapas…</button>
    <button id="retry-print-maps" type="button">Recargar mapas</button>
    <p id="print-status" role="status">Preparando imágenes cartográficas para impresión.</p>
    <small>Para guardar: selecciona «Guardar como PDF», papel A4 y desactiva los encabezados y pies del navegador.</small>
</div>
<article class="print-record cre-report">
    <div class="report-header">
        <img src="marca/cre-institucional.png" alt="Cruz Roja Ecuatoriana">
        <div><p class="report-kicker">CRUZ ROJA ECUATORIANA · <?= h($territory['nombre']) ?></p><h1>Ficha de atención prehospitalaria</h1><p>Hoja <strong><?= h($sheetNumber) ?></strong></p></div>
        <img class="report-service-icon" src="marca/Emergency-Medical-Services.png" alt="">
    </div>
    <div class="report-ribbon"><span><?= h($record['estado']) ?></span><span>Fecha de atención: <?= h($data['B'] ?: 'Sin registrar') ?></span></div>
    <?php if (isset($data['demo_lote'])) { ?><p class="report-demo">EJEMPLO DE PRUEBA · Datos ficticios, sin validez como atención real</p><?php } ?>
    <?php foreach ($printGroups as $title => $keys) { ?>
    <section class="report-section"><h2><?= h($title) ?></h2><dl class="report-fields">
        <?php foreach ($keys as $key) { ?><div class="<?= in_array($key, ['T', 'V', 'AD', 'H', 'W'], true) ? 'report-wide' : '' ?>"><dt><?= h($printLabels[$key] ?? str_replace('_', ' ', FIELDS[$key])) ?></dt><dd><?= h(($data[$key] ?? '') !== '' ? $data[$key] : 'Sin registrar') ?></dd></div><?php } ?>
        <?php if ($title === 'Despacho y traslado') { ?><div><dt>Kilómetros recorridos</dt><dd><?= is_numeric($data['Z']) && is_numeric($data['AA']) && (float) $data['AA'] >= (float) $data['Z'] ? h(number_format((float) $data['AA'] - (float) $data['Z'], 1, ',', '.')).' km' : 'Sin datos válidos' ?></dd></div><?php } ?>
    </dl></section>
    <?php } ?>
    <div class="report-footer">Registro APH · <?= h($territory['nombre']) ?> <span>Hoja <?= h($sheetNumber) ?> · Versión <?= h($record['version']) ?></span></div>
    <section class="report-map-page">
        <div class="report-header"><img src="marca/cre-institucional.png" alt="Cruz Roja Ecuatoriana"><div><p class="report-kicker">REFERENCIAS GEOGRÁFICAS</p><h1>Ubicación del evento y destino</h1><p>Hoja <?= h($sheetNumber) ?> · <?= h($data['B']) ?></p></div><img class="report-service-icon" src="marca/Location.png" alt=""></div>
        <p class="report-map-note">Los pines representan los puntos guardados en la ficha. Son referencias aproximadas, no una ruta de traslado.</p>
        <?php foreach (['evento' => ['Ubicación del evento', 'H'], 'destino' => ['Localidad de destino', 'W']] as $key => [$title,$field]) {
            $lat = $data[$key.'_lat'] ?? null;
            $lon = $data[$key.'_lon'] ?? null;
            $valid = is_numeric($lat) && is_numeric($lon) && abs((float) $lat) <= 90 && abs((float) $lon) <= 180;
            ?>
        <section class="report-map-block"><h2><?= h($title) ?></h2><p><?= h($data[$field] ?: 'Dirección sin registrar') ?></p>
        <?php if ($valid) { ?>
        <div class="report-static-map" data-lat="<?= h($lat) ?>" data-lon="<?= h($lon) ?>" data-tiles="<?= h(config('maps.tiles')) ?>"><p class="map-placeholder">Preparando mapa…</p><img hidden alt="<?= h($title) ?>: punto de referencia marcado"></div>
        <p class="map-caption">Latitud <?= h($lat) ?> · Longitud <?= h($lon) ?> · WGS84 · © OpenStreetMap contributors</p>
        <?php } else { ?><p class="map-missing">No hay coordenadas registradas para esta ubicación.</p><?php } ?>
        </section>
        <?php } ?>
        <div class="report-footer">Cruz Roja Ecuatoriana · Registro APH <span>Hoja <?= h($sheetNumber) ?> · Referencia cartográfica</span></div>
    </section>
</article>
