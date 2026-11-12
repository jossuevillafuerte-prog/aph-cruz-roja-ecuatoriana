<?php
$dimensions = [
    'F' => 'Evento o causa',
    'M' => 'Tipo de identificación',
    'O' => 'Sexo',
    'Q' => 'Nacionalidad',
    'R' => 'Condición de salud',
    'S' => 'Discapacidad',
    'U' => 'Códigos CIE-10',
    'unidad' => 'Despachos por recurso',
    'tipo' => 'Despachos por tipo de recurso',
];
$counts = array_fill_keys(array_keys($dimensions), []);
$ageBands = [4 => '0–4', 14 => '5–14', 24 => '15–24', 34 => '25–34', 44 => '35–44', 54 => '45–54', 64 => '55–64', 120 => '65+'];
$pyramid = array_fill_keys(array_keys($ageBands), ['Hombre' => 0, 'Mujer' => 0, 'Sin especificar' => 0]);
$unknownAge = 0;
$dispatches = 0;
$kilometers = [];
$points = array_fill_keys(array_keys(LOCATIONS), []);
$query = $db->prepare('SELECT a.datos, r.tipo FROM atenciones a
    LEFT JOIN recursos r ON r.id=a.recurso_id AND r.provincia=a.provincia
    WHERE a.provincia=? AND a.eliminado IS NULL');
$query->execute([$province]);
foreach ($query as $row) {
    $data = json_decode($row['datos'], true, 512, JSON_THROW_ON_ERROR);
    foreach (['F', 'M', 'O', 'Q', 'R', 'S', 'U'] as $key) {
        $value = ($data[$key] ?? '') !== '' ? $data[$key] : 'Sin registrar';
        $counts[$key][$value] = ($counts[$key][$value] ?? 0) + 1;
    }
    $unit = $data['J'] ?? '';
    if ($unit !== '') {
        $dispatches++;
        $type = $data['recurso_tipo'] ?? $row['tipo'] ?? 'Sin tipo registrado';
        $counts['unidad'][$unit] = ($counts['unidad'][$unit] ?? 0) + 1;
        $counts['tipo'][$type] = ($counts['tipo'][$type] ?? 0) + 1;
    }
    $age = $data['P'] ?? '';
    if (ctype_digit((string) $age) && (int) $age <= 120) {
        $sex = in_array($data['O'] ?? '', ['Mujer', 'Hombre'], true) ? $data['O'] : 'Sin especificar';
        foreach ($ageBands as $maximum => $label) {
            if ((int) $age <= $maximum) {
                $pyramid[$maximum][$sex]++;
                break;
            }
        }
    } else {
        $unknownAge++;
    }
    $date = $data['B'] ?? '';
    $start = $data['Z'] ?? '';
    $end = $data['AA'] ?? '';
    if ($date !== '' && is_numeric($start) && is_numeric($end) && is_finite((float) $start) && is_finite((float) $end)
        && (float) $start >= 0 && (float) $end >= (float) $start) {
        $kilometers[$date] = ($kilometers[$date] ?? 0) + ((float) $end - (float) $start);
    }
    foreach (LOCATIONS as $key => $label) {
        if (isset($data[$key.'_lat'], $data[$key.'_lon'])) {
            $points[$key][] = [$data[$key.'_lat'], $data[$key.'_lon']];
        }
    }
}
foreach ($counts as &$items) {
    ksort($items, SORT_NATURAL | SORT_FLAG_CASE);
    arsort($items, SORT_NUMERIC);
}
unset($items);
krsort($kilometers);
$pyramidPeak = max(1, max(array_column($pyramid, 'Hombre')), max(array_column($pyramid, 'Mujer')));

function dashboardBars(array $rows, string $unit = ''): void
{
    if (! $rows) {
        echo '<p class="heading-description">Aún no hay datos para este gráfico.</p>';

        return;
    }
    $peak = max($rows);
    ?>
    <ul class="stat-bars">
        <?php foreach ($rows as $label => $value) { ?>
            <li><span><?= h($label) ?></span>
                <strong><?= h($unit === '' ? $value : rtrim(rtrim(number_format($value, 3, ',', '.'), '0'), ',').$unit) ?></strong>
                <span class="stat-track" aria-hidden="true"><span style="width:<?= $peak > 0 ? round(100 * ($value / $peak), 2) : 0 ?>%"></span></span>
            </li>
        <?php } ?>
    </ul>
    <?php
}
?>
<div class="heading analytics-heading"><div><p class="eyebrow">DETALLE PROVINCIAL</p><h2>Estadísticas de atenciones</h2>
    <p class="heading-description">Incluye fichas pendientes y cerradas. Los datos faltantes se muestran como «Sin registrar».</p></div></div>
<div class="dashboard-breakdowns">
    <?php foreach (LOCATIONS as $key => $label) { ?>
        <section class="card map-card">
            <h2><?= $key === 'evento' ? 'Mapa de direcciones del evento' : 'Mapa de localidades de destino' ?></h2>
            <p class="heading-description"><?= count($points[$key]) ?> puntos de referencia registrados.</p>
            <?php if ($points[$key]) { ?>
                <div class="dashboard-map" data-points="<?= h(json_encode($points[$key], JSON_THROW_ON_ERROR)) ?>"
                    data-tiles="<?= h(getenv('APH_MAP_TILES') ?: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png') ?>"
                    data-attribution="<?= h(getenv('APH_MAP_ATTRIBUTION') ?: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors') ?>"
                    aria-label="<?= h($label) ?>"></div>
            <?php } ?>
        </section>
    <?php } ?>
</div>
<div class="dashboard-breakdowns">
    <section class="card"><h2>Recursos despachados</h2>
        <p class="metric-value"><?= $dispatches ?></p>
        <p class="heading-description">Atenciones con un recurso despachado registrado.</p>
    </section>
    <section class="card"><h2>Kilómetros recorridos por fecha</h2>
        <p class="heading-description">Suma de llegada menos salida, agrupada por fecha de atención.</p>
        <?php dashboardBars($kilometers, ' km'); ?>
    </section>
    <?php foreach ($dimensions as $key => $title) { ?>
        <section class="card"><h2><?= h($title) ?></h2><?php dashboardBars($counts[$key]); ?></section>
    <?php } ?>
</div>
<section class="card age-card">
    <h2>Pirámide por rangos etarios</h2>
    <p class="heading-description">Atenciones por edad y sexo registrado. Edad sin registrar o inválida: <?= $unknownAge ?>.</p>
    <table class="age-pyramid">
        <thead><tr><th scope="col">Hombre</th><th scope="col">Edad</th><th scope="col">Mujer</th><th scope="col">Sexo sin especificar</th></tr></thead>
        <tbody>
        <?php foreach (array_reverse($ageBands, true) as $maximum => $label) {
            $group = $pyramid[$maximum];
            ?>
            <tr><td><div class="age-side age-left"><span aria-hidden="true" style="width:<?= round(100 * $group['Hombre'] / $pyramidPeak) ?>%"></span><strong><?= $group['Hombre'] ?></strong></div></td>
                <th scope="row"><?= h($label) ?></th>
                <td><div class="age-side age-right"><span aria-hidden="true" style="width:<?= round(100 * $group['Mujer'] / $pyramidPeak) ?>%"></span><strong><?= $group['Mujer'] ?></strong></div></td>
                <td><?= $group['Sin especificar'] ?></td></tr>
        <?php } ?>
        </tbody>
    </table>
</section>
