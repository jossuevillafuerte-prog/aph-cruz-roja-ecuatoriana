<?php

declare(strict_types=1);
use Illuminate\Support\Facades\DB;

const FIELDS = [
    'A' => 'Columna1', 'B' => 'Fecha_de_atencion', 'C' => 'Codigo_APH_Salud',
    'D' => 'Tipo_Codigo_APH_Salud', 'E' => 'Categoria_Codigo_APH_Salud', 'F' => 'Evento_Causa',
    'G' => 'Canton', 'H' => 'Direccion_evento', 'I' => 'Hora_llegada', 'J' => 'Recurso_Despacho',
    'K' => 'Hoja_atencion', 'L' => 'Nombres_y_apellidos_PACIENTE', 'M' => 'Tipo_identificacion',
    'N' => 'Nro_identificacion', 'O' => 'Sexo', 'P' => 'Edad', 'Q' => 'Nacionalidad',
    'R' => 'Condicion', 'S' => 'Discapacidad', 'T' => 'Diagnostico_descrito_territorio',
    'U' => 'CIE_10', 'V' => 'Descripcion_Diagnostico_Codigo_CIE_10', 'W' => 'Localidad_destino',
    'X' => 'Nombres_medico_recepcion_paciente', 'Y' => 'Responsable', 'Z' => 'km_salida',
    'AA' => 'km_llegada', 'AB' => 'combustible_salida', 'AC' => 'combustible_llegada', 'AD' => 'Observaciones',
];
const DERIVED = ['A', 'D', 'E', 'V'];
const CLOSING_FIELDS = ['B', 'C', 'G', 'Y'];
const LOCATIONS = ['evento' => 'Ubicación del evento', 'destino' => 'Ubicación del destino'];

function validateLocations(array $input, array $previous): array
{
    $locations = [];
    $errors = [];
    foreach (LOCATIONS as $key => $label) {
        $lat = $key.'_lat';
        $lon = $key.'_lon';
        if (! array_key_exists($lat, $input) && ! array_key_exists($lon, $input)) {
            $locations[$lat] = $previous[$lat] ?? null;
            $locations[$lon] = $previous[$lon] ?? null;

            continue;
        }
        $latitude = $input[$lat] ?? null;
        $longitude = $input[$lon] ?? null;
        if ($latitude === '' && $longitude === '') {
            $locations[$lat] = $locations[$lon] = null;

            continue;
        }
        if (! is_string($latitude) || ! is_string($longitude) || ! is_numeric($latitude) || ! is_numeric($longitude)
            || abs((float) $latitude) > 90 || abs((float) $longitude) > 180) {
            $errors[] = "$label: vuelve a seleccionar el punto en el mapa.";
            $locations[$lat] = $previous[$lat] ?? null;
            $locations[$lon] = $previous[$lon] ?? null;

            continue;
        }
        $locations[$lat] = round((float) $latitude, 6);
        $locations[$lon] = round((float) $longitude, 6);
    }

    return [$locations, $errors];
}

function database(): PDO
{
    $pdo = DB::connection()->getPdo();
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    return $pdo;
}

function provinces(): array
{
    return json_decode(file_get_contents(__DIR__.'/provincias.json'), true, 512, JSON_THROW_ON_ERROR);
}

function diagnoses(): array
{
    return json_decode(file_get_contents(__DIR__.'/cie10-oms-2019.json'), true, 512, JSON_THROW_ON_ERROR);
}

function searchText(string $text): string
{
    return strtolower(strtr($text, [
        'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n',
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
    ]));
}

function adverseEvents(): array
{
    $events = [];
    foreach (file(__DIR__.'/eventos-sngr.tsv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $row) {
        [$code, $label, $parent] = array_pad(explode("\t", $row), 3, '');
        $events[$code] = ['name' => $label, 'parent' => $parent];
    }

    return $events;
}

function catalog(array $province): array
{
    $catalog = json_decode(file_get_contents(__DIR__.'/catalogos.json'), true, 512, JSON_THROW_ON_ERROR);
    $catalog['events'] = adverseEvents();
    $catalog['lists']['F'] = array_column($catalog['events'], 'name');
    $catalog['lists']['G'] = $province['cantones'];

    return $catalog;
}

function validate(array $input, array $catalog, bool $closed, array $previous = []): array
{
    $data = array_fill_keys(array_keys(FIELDS), '');
    $errors = [];
    foreach (array_diff(array_keys(FIELDS), DERIVED) as $key) {
        $data[$key] = trim(is_string($input[$key] ?? null) ? $input[$key] : '');
        if (strlen($data[$key]) > 4000) {
            $errors[] = FIELDS[$key].': supera la longitud admitida.';
        }
    }
    foreach ($closed ? CLOSING_FIELDS : ['B'] as $key) {
        if ($data[$key] === '') {
            $errors[] = 'Completa '.FIELDS[$key].'.';
        }
    }
    if ($data['B'] !== '') {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $data['B']);
        if (! $date || $date->format('Y-m-d') !== $data['B']) {
            $errors[] = 'Ingresa una fecha de atención válida.';
        }
    }
    if ($data['I'] !== '' && ! preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d\z/', $data['I'])) {
        $errors[] = 'Ingresa una hora válida.';
    }
    if ($data['P'] !== '' && (! ctype_digit($data['P']) || (int) $data['P'] > 120)) {
        $errors[] = 'La edad debe ser un entero entre 0 y 120.';
    }
    foreach (['Z', 'AA'] as $key) {
        if ($data[$key] !== '' && (! is_numeric($data[$key]) || ! is_finite((float) $data[$key]) || (float) $data[$key] < 0)) {
            $errors[] = FIELDS[$key].': ingresa un número no negativo.';
        }
    }
    $odometerChanged = $data['Z'] !== ($previous['Z'] ?? '') || $data['AA'] !== ($previous['AA'] ?? '');
    if ($odometerChanged && is_numeric($data['Z']) && is_numeric($data['AA']) && (float) $data['AA'] < (float) $data['Z']) {
        $errors[] = 'El kilometraje de llegada debe ser igual o mayor que el de salida.';
    }
    foreach ($catalog['lists'] as $key => $options) {
        $legacyValue = in_array($key, ['F', 'G'], true) && $data[$key] === ($previous[$key] ?? null);
        if (! $legacyValue && $data[$key] !== '' && ! in_array($data[$key], $options, true)) {
            $errors[] = FIELDS[$key].': selecciona un valor del catálogo.';
        }
    }
    $noDocument = ['', 'No identificado', 'Rehusa identificarse'];
    $documentChanged = $data['M'] !== ($previous['M'] ?? '') || $data['N'] !== ($previous['N'] ?? '');
    if ($documentChanged || ! $previous) {
        if (in_array($data['M'], $noDocument, true) && $data['N'] !== '') {
            $errors[] = 'Selecciona un tipo de documento válido para ingresar su número.';
        } elseif ($data['N'] !== '' && $data['M'] === 'Cédula de identidad' && ! preg_match('/\A[0-9]{10}\z/', $data['N'])) {
            $errors[] = 'La cédula ecuatoriana debe contener exactamente 10 dígitos.';
        } elseif ($data['N'] !== '' && ! preg_match('/\A(?=.*[A-Za-z0-9])[A-Za-z0-9 .\/-]{1,80}\z/', $data['N'])) {
            $errors[] = 'Ingresa el número tal como aparece en el documento (letras, números, espacios, puntos, barras o guiones).';
        } elseif ($closed && ! in_array($data['M'], $noDocument, true) && $data['N'] === '') {
            $errors[] = 'Completa el número del documento seleccionado para cerrar la atención.';
        }
    } elseif ($closed && ! in_array($data['M'], $noDocument, true) && $data['N'] === '') {
        $errors[] = 'Completa el número del documento seleccionado para cerrar la atención.';
    }
    if (isset($catalog['aph'][$data['C']])) {
        $data['A'] = substr($data['C'], 0, 3);
        [$data['D'], $data['E']] = $catalog['aph'][$data['C']];
    }
    if ($data['U'] !== '') {
        $code = preg_match('/\A[A-Z][0-9]{2}(?:\.?[A-Z0-9]+)?\z/i', $data['U'])
            ? str_replace('.', '', strtoupper($data['U'])) : '';
        foreach ($code === '' ? [] : diagnoses() as $item) {
            if ($item['codigo'] === $code) {
                $data['U'] = $item['codigo'];
                $data['V'] = $item['descripcion'];
                break;
            }
        }
        if ($data['V'] === '' && $data['U'] === ($previous['U'] ?? null)) {
            $data['V'] = $previous['V'] ?? '';
        } elseif ($data['V'] === '') {
            $errors[] = 'Selecciona un código CIE-10 válido.';
        }
    }

    return [$data, $errors];
}

function save(PDO $db, string $province, ?array $record, array $data, string $state, string $reason, ?int $resourceId, array $operator, ?int $owner): int
{
    $db->beginTransaction();
    try {
        $encoded = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        if ($record) {
            $query = $db->prepare('UPDATE atenciones SET datos=?, estado=?, recurso_id=?, responsable_id=?, version=version+1 WHERE id=? AND version=? AND provincia=? AND eliminado IS NULL');
            $query->execute([$encoded, $state, $resourceId, $owner, $record['id'], $record['version'], $province]);
            if ($query->rowCount() !== 1) {
                throw new DomainException('Otra edición se guardó primero. Abre de nuevo la atención.');
            }
            $id = (int) $record['id'];
        } else {
            $db->prepare('INSERT INTO atenciones (version,estado,datos,provincia,recurso_id,responsable_id) VALUES (1,?,?,?,?,?)')
                ->execute([$state, $encoded, $province, $resourceId, $owner]);
            $id = (int) $db->lastInsertId();
        }
        $previous = $record ? json_decode($record['datos'], true, 512, JSON_THROW_ON_ERROR) : [];
        $changes = ['estado' => [$record['estado'] ?? '', $state]];
        if (($record['recurso_id'] ?? null) !== $resourceId) {
            $changes['Vínculo de recurso'] = [(string) ($record['recurso_id'] ?? ''), (string) $resourceId];
        }
        foreach (FIELDS as $key => $label) {
            $value = $data[$key];
            $old = $previous[$key] ?? '';
            if ($old !== $value) {
                $changes[$label] = [$old, $value];
            }
        }
        foreach (LOCATIONS as $key => $label) {
            $lat = $key.'_lat';
            $lon = $key.'_lon';
            $old = isset($previous[$lat], $previous[$lon]) ? $previous[$lat].', '.$previous[$lon] : '';
            $new = isset($data[$lat], $data[$lon]) ? $data[$lat].', '.$data[$lon] : '';
            if ($old !== $new) {
                $changes[$label] = [$old, $new];
            }
        }
        $db->prepare('INSERT INTO cambios (atencion,fecha,autor,motivo,detalle) VALUES (?,?,?,?,?)')
            ->execute([$id, date('c'), accountLabel($operator), $reason, json_encode($changes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
        $db->commit();

        return $id;
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }
}

function deleteAttention(PDO $db, string $province, array $record, array $account): bool
{
    DB::beginTransaction();
    try {
        $date = date('c');
        $query = $db->prepare('UPDATE atenciones SET eliminado=?, version=version+1 WHERE id=? AND provincia=? AND version=? AND eliminado IS NULL');
        $query->execute([$date, $record['id'], $province, $record['version']]);
        if ($query->rowCount() !== 1) {
            DB::rollBack();

            return false;
        }
        $db->prepare('INSERT INTO cambios (atencion,fecha,autor,motivo,detalle) VALUES (?,?,?,?,?)')
            ->execute([$record['id'], $date, accountLabel($account), 'Eliminación de atención', json_encode(['Visibilidad' => ['Activa', 'Eliminada']], JSON_THROW_ON_ERROR)]);
        DB::commit();

        return true;
    } catch (Throwable $error) {
        DB::rollBack();
        throw $error;
    }
}
