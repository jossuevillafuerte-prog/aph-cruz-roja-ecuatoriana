<?php
/**
 * Paso de valoración: silueta, zona afectada, códigos sugeridos, CIE-10 y diagnóstico descrito en un solo panel.
 * La lista #f-AE es el control real (teclado y lector de pantalla); la silueta es un atajo que la actualiza.
 *
 * @var int $stepIndex
 * @var string $title
 * @var array<string, mixed> $data
 * @var array<int|string, string> $errors
 */
$bodyShapes = [
    ['circle', 'Cabeza', 'cx="100" cy="35" r="24"'],
    ['rect', 'Cuello', 'x="90" y="59" width="20" height="14" rx="4"'],
    ['rect', 'Tórax', 'x="66" y="73" width="68" height="62" rx="8"'],
    ['rect', 'Abdomen, espalda baja y pelvis', 'x="66" y="137" width="68" height="78" rx="8"'],
    ['rect', 'Hombro y brazo', 'x="43" y="76" width="21" height="62" rx="8"'],
    ['rect', 'Hombro y brazo', 'x="136" y="76" width="21" height="62" rx="8"'],
    ['rect', 'Codo y antebrazo', 'x="40" y="140" width="19" height="56" rx="8"'],
    ['rect', 'Codo y antebrazo', 'x="141" y="140" width="19" height="56" rx="8"'],
    ['ellipse', 'Muñeca y mano', 'cx="49" cy="210" rx="11" ry="13"'],
    ['ellipse', 'Muñeca y mano', 'cx="151" cy="210" rx="11" ry="13"'],
    ['rect', 'Cadera y muslo', 'x="70" y="217" width="28" height="70" rx="9"'],
    ['rect', 'Cadera y muslo', 'x="102" y="217" width="28" height="70" rx="9"'],
    ['rect', 'Rodilla y pierna', 'x="72" y="289" width="24" height="68" rx="8"'],
    ['rect', 'Rodilla y pierna', 'x="104" y="289" width="24" height="68" rx="8"'],
    ['ellipse', 'Tobillo y pie', 'cx="83" cy="370" rx="16" ry="9"'],
    ['ellipse', 'Tobillo y pie', 'cx="117" cy="370" rx="16" ry="9"'],
];
$zone = $data['AE'] ?? '';
?>
<fieldset class="card form-step" data-step="<?= $stepIndex ?>" id="paso-<?= $stepIndex + 1 ?>"><legend><?= $stepIndex + 1 ?>. <?= h($title) ?></legend>
<p class="step-intro">Toca la zona afectada en la silueta: se sugieren los códigos CIE-10 de esa región. Si eliges primero el código, la zona se marca sola. <span class="required-note">El diagnóstico descrito es obligatorio para cerrar.</span></p>
<div class="assessment" data-zones="<?= h(json_encode(bodyZoneSuggestions(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)) ?>">
    <svg class="assessment-body" viewBox="0 0 200 390" aria-hidden="true" focusable="false">
        <?php foreach ($bodyShapes as [$shape, $shapeZone, $attributes]) { ?>
            <<?= $shape ?> class="body-zone" data-zone="<?= h($shapeZone) ?>" <?= $attributes ?>><title><?= h($shapeZone) ?></title></<?= $shape ?>>
        <?php } ?>
    </svg>
    <div class="assessment-panel">
        <div class="field">
            <label for="f-AE"><?= h(fieldLabel('AE')) ?></label>
            <div class="zone-row">
                <select id="f-AE" name="AE"<?= fieldErrorAttributes($errors, 'AE') ?>>
                    <option value="">Sin seleccionar</option>
                    <?php foreach (array_keys(BODY_ZONES) as $option) { ?><option <?= $option === $zone ? 'selected' : '' ?>><?= h($option) ?></option><?php } ?>
                </select>
                <button type="button" class="body-zone-chip" data-zone="Varias regiones">Varias regiones</button>
                <button type="button" class="body-zone-chip" data-zone="Sin zona (clínico)">Sin zona (clínico)</button>
            </div>
            <?= fieldErrorMessage($errors, 'AE') ?>
        </div>
        <div class="zone-hint" role="status"></div>
        <div class="field suggestions-field" hidden>
            <span class="field-label suggestions-title"></span>
            <div class="body-map-suggestions"></div>
        </div>
        <div class="field">
            <label for="f-U"><?= h(fieldLabel('U')) ?></label>
            <div class="cie-row">
                <input id="f-U" name="U" value="<?= h($data['U'] ?? '') ?>" placeholder="S42.0" autocomplete="off"<?= fieldErrorAttributes($errors, 'U', 'cie-description') ?>>
                <button type="button" class="cie-search-zone"><?= icon('list') ?> <span>Buscar en la CIE-10</span></button>
            </div>
            <small id="cie-description"><?= h($data['V'] ?? '') ?></small>
            <?= fieldErrorMessage($errors, 'U') ?>
            <details id="cie-picker"><summary>Listado completo de CIE-10</summary>
                <label for="cie-search">Buscar por código o descripción</label>
                <input id="cie-search" type="search" autocomplete="off" maxlength="100" placeholder="fractura">
                <small id="cie-status" role="status"></small><div id="cie-results"></div>
                <div class="actions"><button id="cie-prev" type="button" disabled>Anterior</button><button id="cie-next" type="button" disabled>Siguiente</button></div>
            </details>
        </div>
        <div class="field">
            <label for="f-T"><?= h(fieldLabel('T')) ?><?= requiredMark('T') ?></label>
            <textarea id="f-T" name="T" maxlength="4000" placeholder="Paciente consciente, dolor en hombro derecho tras caída"<?= fieldErrorAttributes($errors, 'T') ?>><?= h($data['T'] ?? '') ?></textarea>
            <?= fieldErrorMessage($errors, 'T') ?>
        </div>
    </div>
</div>
</fieldset>
