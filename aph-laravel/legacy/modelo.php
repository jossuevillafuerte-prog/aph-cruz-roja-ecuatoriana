<?php

declare(strict_types=1);
use App\Security\Security;
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
    'AE' => 'Zona_afectada',
];
const DERIVED = ['A', 'D', 'E', 'V'];
const FIELD_LABELS = [
    'B' => 'Fecha de atención', 'C' => 'Código APH', 'F' => 'Evento o causa', 'G' => 'Cantón', 'H' => 'Dirección del evento',
    'I' => 'Hora de llegada', 'J' => 'Recurso despachado', 'K' => 'Número de hoja de atención', 'L' => 'Nombres y apellidos',
    'M' => 'Tipo de identificación', 'N' => 'Número de identificación', 'O' => 'Sexo', 'P' => 'Edad', 'Q' => 'Nacionalidad',
    'R' => 'Condición', 'S' => 'Discapacidad', 'T' => 'Diagnóstico descrito', 'U' => 'Código CIE-10',
    'W' => 'Localidad de destino', 'X' => 'Médico de recepción', 'Y' => 'Responsable', 'Z' => 'Kilometraje de salida',
    'AA' => 'Kilometraje de llegada', 'AB' => 'Combustible de salida', 'AC' => 'Combustible de llegada', 'AD' => 'Observaciones',
    'AE' => 'Zona afectada',
];
const CLOSING_FIELDS = ['B', 'C', 'G', 'Y'];
// Obligatorios al cerrar una atención nueva o pendiente: pasos 1 y 2 completos, diagnóstico descrito, destino y médico.
// El número de documento se exige aparte, salvo paciente no identificado. Las correcciones usan CLOSING_FIELDS.
const REQUIRED_TO_CLOSE = ['K', 'B', 'I', 'C', 'F', 'G', 'H', 'J', 'L', 'M', 'O', 'P', 'Q', 'R', 'S', 'T', 'W', 'X', 'Y'];
const DASHBOARD_FIELDS = ['B', 'D', 'F', 'G', 'K', 'M', 'O', 'P', 'Q', 'R', 'S', 'J', 'Z', 'AA', 'evento_lat', 'evento_lon', 'destino_lat', 'destino_lon', 'recurso_tipo'];
const DASHBOARD_LIMIT = 10000;
const ATTENTIONS_PER_PAGE = 30;
/**
 * Zonas del mapa corporal: rango CIE-10 de traumatismos (capítulo XIX) y códigos frecuentes que se sugieren.
 *
 * @var array<string, array{0: ?string, 1: ?string, 2: list<string>}>
 */
const BODY_ZONES = [
    'Cabeza' => ['S00', 'S09', ['S060', 'S010', 'S020']],
    'Cuello' => ['S10', 'S19', ['S134', 'S119']],
    'Tórax' => ['S20', 'S29', ['S223', 'S202', 'S270']],
    'Abdomen, espalda baja y pelvis' => ['S30', 'S39', ['S301', 'S360', 'S325']],
    'Hombro y brazo' => ['S40', 'S49', ['S420', 'S430']],
    'Codo y antebrazo' => ['S50', 'S59', ['S525', 'S500']],
    'Muñeca y mano' => ['S60', 'S69', ['S619', 'S626']],
    'Cadera y muslo' => ['S70', 'S79', ['S720', 'S701']],
    'Rodilla y pierna' => ['S80', 'S89', ['S822', 'S800']],
    'Tobillo y pie' => ['S90', 'S99', ['S934', 'S923']],
    'Varias regiones' => ['T00', 'T07', ['T009']],
    'Sin zona (clínico)' => [null, null, ['R509', 'R104', 'J960', 'E162']],
];
const LOCATIONS = ['evento' => 'Ubicación del evento', 'destino' => 'Ubicación del destino'];

function fieldLabel(string $key): string
{
    return FIELD_LABELS[$key] ?? str_replace('_', ' ', FIELDS[$key] ?? $key);
}

/**
 * Conserva el primer error de cada campo para mostrarlo junto a él.
 *
 * @param  array<int|string, string>  $errors
 */
function addFieldError(array &$errors, string $key, string $message): void
{
    $errors[$key] ??= $message;
}

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
            addFieldError($errors, $key === 'evento' ? 'H' : 'W', "$label: vuelve a seleccionar el punto en el mapa.");
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

/**
 * Sugerencias por zona con la descripción oficial del listado CIE-10.
 *
 * @return array<string, array{from: ?string, to: ?string, range: ?string, codes: list<array{0: string, 1: string}>}>
 */
function bodyZoneSuggestions(): array
{
    $byCode = array_column(diagnoses(), null, 'codigo');
    $zones = [];
    foreach (BODY_ZONES as $zone => [$from, $to, $codes]) {
        $zones[$zone] = [
            'from' => $from,
            'to' => $to,
            'range' => $from ? $from.'–'.$to : null,
            'codes' => array_values(array_map(static fn (string $code): array => [$byCode[$code]['visible'], $byCode[$code]['descripcion']], array_filter($codes, static fn (string $code): bool => isset($byCode[$code])))),
        ];
    }

    return $zones;
}

/**
 * Indica si un código CIE-10 (sin punto) cae en el rango de la zona.
 */
function inBodyZone(string $code, string $zone): bool
{
    [$from, $to] = BODY_ZONES[$zone] ?? [null, null];

    return $from !== null && strcmp(substr($code, 0, 3), $from) >= 0 && strcmp(substr($code, 0, 3), $to) <= 0;
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

function validate(array $input, array $catalog, bool $closed, array $previous = [], bool $closing = false): array
{
    $data = array_fill_keys(array_keys(FIELDS), '');
    $errors = [];
    foreach (array_diff(array_keys(FIELDS), DERIVED) as $key) {
        $data[$key] = trim(is_string($input[$key] ?? null) ? $input[$key] : '');
        if (strlen($data[$key]) > 4000) {
            addFieldError($errors, $key, fieldLabel($key).': supera los 4000 caracteres admitidos.');
        }
    }
    foreach ($closed ? ($closing ? REQUIRED_TO_CLOSE : CLOSING_FIELDS) : ['B'] as $key) {
        if ($data[$key] === '') {
            addFieldError($errors, $key, 'Completa «'.fieldLabel($key).'».');
        }
    }
    if ($data['B'] !== '') {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $data['B']);
        if (! $date || $date->format('Y-m-d') !== $data['B']) {
            addFieldError($errors, 'B', 'Ingresa una fecha de atención válida.');
        }
    }
    if ($data['I'] !== '' && ! preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d\z/', $data['I'])) {
        addFieldError($errors, 'I', 'Ingresa una hora válida, por ejemplo 14:30.');
    }
    if ($data['P'] !== '' && (! ctype_digit($data['P']) || (int) $data['P'] > 120)) {
        addFieldError($errors, 'P', 'La edad debe ser un número entero entre 0 y 120.');
    }
    foreach (['Z', 'AA'] as $key) {
        if ($data[$key] !== '' && (! is_numeric($data[$key]) || ! is_finite((float) $data[$key]) || (float) $data[$key] < 0)) {
            addFieldError($errors, $key, fieldLabel($key).': ingresa un número igual o mayor que 0.');
        }
    }
    $odometerChanged = $data['Z'] !== ($previous['Z'] ?? '') || $data['AA'] !== ($previous['AA'] ?? '');
    if ($odometerChanged && is_numeric($data['Z']) && is_numeric($data['AA']) && (float) $data['AA'] < (float) $data['Z']) {
        addFieldError($errors, 'AA', 'El kilometraje de llegada debe ser igual o mayor que el de salida.');
    }
    foreach ($catalog['lists'] as $key => $options) {
        $legacyValue = in_array($key, ['F', 'G'], true) && $data[$key] === ($previous[$key] ?? null);
        if (! $legacyValue && $data[$key] !== '' && ! in_array($data[$key], $options, true)) {
            addFieldError($errors, $key, fieldLabel($key).': selecciona una opción de la lista.');
        }
    }
    $noDocument = ['', 'No identificado', 'Rehusa identificarse'];
    $documentChanged = $data['M'] !== ($previous['M'] ?? '') || $data['N'] !== ($previous['N'] ?? '');
    if ($documentChanged || ! $previous) {
        if (in_array($data['M'], $noDocument, true) && $data['N'] !== '') {
            addFieldError($errors, 'N', 'Selecciona un tipo de documento antes de ingresar su número.');
        } elseif ($data['N'] !== '' && $data['M'] === 'Cédula de identidad' && ! preg_match('/\A[0-9]{10}\z/', $data['N'])) {
            addFieldError($errors, 'N', 'La cédula ecuatoriana debe contener exactamente 10 dígitos.');
        } elseif ($data['N'] !== '' && ! preg_match('/\A(?=.*[A-Za-z0-9])[A-Za-z0-9 .\/-]{1,80}\z/', $data['N'])) {
            addFieldError($errors, 'N', 'Ingresa el número tal como aparece en el documento (letras, números, espacios, puntos, barras o guiones).');
        } elseif ($closed && ! in_array($data['M'], $noDocument, true) && $data['N'] === '') {
            addFieldError($errors, 'N', 'Completa el número del documento seleccionado para cerrar la atención.');
        }
    } elseif ($closed && ! in_array($data['M'], $noDocument, true) && $data['N'] === '') {
        addFieldError($errors, 'N', 'Completa el número del documento seleccionado para cerrar la atención.');
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
            addFieldError($errors, 'U', 'Selecciona un código CIE-10 de la lista.');
        }
    }
    if ($data['AE'] !== '' && ! isset(BODY_ZONES[$data['AE']])) {
        addFieldError($errors, 'AE', 'Selecciona una zona afectada de la lista.');
    }

    return [$data, $errors];
}

function locationKeys(): array
{
    $keys = [];
    foreach (array_keys(LOCATIONS) as $key) {
        array_push($keys, $key.'_lat', $key.'_lon');
    }

    return $keys;
}

function preservedData(array $savedData): array
{
    return array_diff_key($savedData, FIELDS, array_flip(locationKeys()), ['recurso_tipo' => true]);
}

function historyValue(mixed $value): string
{
    return is_scalar($value) || $value === null ? (string) $value : json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
}

function changesBetween(array $previous, array $data): array
{
    $changes = [];
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
    foreach (array_diff_key($data + $previous, FIELDS, array_flip(locationKeys())) as $key => $value) {
        $old = historyValue($previous[$key] ?? '');
        $new = historyValue($data[$key] ?? '');
        if ($old !== $new) {
            $changes[$key] = [$old, $new];
        }
    }

    return $changes;
}

function save(PDO $db, string $province, ?array $record, array $data, string $state, string $reason, ?int $resourceId, array $operator, ?int $owner, ?string $requestId = null): int
{
    $encoded = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    try {
        return DB::transaction(function () use ($db, $province, $record, $data, $encoded, $state, $reason, $resourceId, $operator, $owner, $requestId): int {
            if ($record) {
                $query = $db->prepare('UPDATE atenciones SET datos=?, estado=?, recurso_id=?, responsable_id=?, version=version+1 WHERE id=? AND version=? AND provincia=? AND eliminado IS NULL');
                $query->execute([$encoded, $state, $resourceId, $owner, $record['id'], $record['version'], $province]);
                if ($query->rowCount() !== 1) {
                    throw new DomainException('Otra edición se guardó primero. Abre de nuevo la atención.');
                }
                $id = (int) $record['id'];
            } else {
                $db->prepare('INSERT INTO atenciones (version,estado,datos,provincia,recurso_id,responsable_id,solicitud) VALUES (1,?,?,?,?,?,?)')
                    ->execute([$state, $encoded, $province, $resourceId, $owner, $requestId]);
                $id = (int) $db->lastInsertId();
            }
            $previous = $record ? json_decode($record['datos'], true, 512, JSON_THROW_ON_ERROR) : [];
            $changes = ['estado' => [$record['estado'] ?? '', $state]];
            if (($record['recurso_id'] ?? null) !== $resourceId) {
                $changes['Vínculo de recurso'] = [(string) ($record['recurso_id'] ?? ''), (string) $resourceId];
            }
            $changes += changesBetween($previous, $data);
            $db->prepare('INSERT INTO cambios (atencion,fecha,autor,motivo,detalle) VALUES (?,?,?,?,?)')
                ->execute([$id, date('c'), accountLabel($operator), $reason, json_encode($changes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
            Security::event('application_write', null, 'atencion:'.$id);

            return $id;
        });
    } catch (PDOException $error) {
        if ($record || $requestId === null || ($error->errorInfo[0] ?? '') !== '23000') {
            throw $error;
        }

        return repeatedRequest($db, $province, $requestId, $data, $state) ?? throw $error;
    }
}

function repeatedRequest(PDO $db, string $province, string $requestId, array $data, string $state): ?int
{
    $query = $db->prepare('SELECT id, estado, datos FROM atenciones WHERE solicitud=? AND provincia=?');
    $query->execute([$requestId, $province]);
    $existing = $query->fetch(PDO::FETCH_ASSOC);
    if (! $existing) {
        return null;
    }
    if ($existing['estado'] !== $state || json_decode($existing['datos'], true, 512, JSON_THROW_ON_ERROR) != $data) {
        throw new DomainException('Esta atención ya se guardó con otros datos y lo que ves aquí no se guardó. Ábrela desde Atenciones para corregirla (registro '.$existing['id'].').');
    }

    return (int) $existing['id'];
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
        Security::event('application_write', null, 'eliminar:'.$record['id']);
        DB::commit();

        return true;
    } catch (Throwable $error) {
        DB::rollBack();
        throw $error;
    }
}

function validDate(mixed $value): bool
{
    $date = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;

    return $date !== false && $date->format('Y-m-d') === $value;
}

/**
 * @return array{desde: string, hasta: string, error: string}
 */
function dashboardPeriod(array $query): array
{
    if (($query['periodo'] ?? '') === 'todo') {
        return ['desde' => '', 'hasta' => '', 'error' => ''];
    }
    $default = ['desde' => date('Y-m-d', strtotime('-12 months')), 'hasta' => date('Y-m-d'), 'error' => ''];
    if (empty($query['desde']) && empty($query['hasta'])) {
        return $default;
    }
    if (! validDate($query['desde'] ?? null) || ! validDate($query['hasta'] ?? null) || $query['desde'] > $query['hasta']) {
        return ['error' => 'Las fechas del periodo no son válidas. Se muestran los últimos 12 meses.'] + $default;
    }

    return ['desde' => $query['desde'], 'hasta' => $query['hasta'], 'error' => ''];
}

/**
 * Consulta solo los campos del panel: los datos personales y clínicos no salen de la base.
 *
 * @return array{total: int, rows: PDOStatement}
 */
function dashboardRecords(PDO $db, string $province, array $period): array
{
    $conditions = ['a.eliminado IS NULL'];
    $parameters = [];
    if ($province !== '') {
        $conditions[] = 'a.provincia=?';
        $parameters[] = $province;
    }
    if ($period['desde'] !== '') {
        $conditions[] = 'a.fecha BETWEEN ? AND ?';
        array_push($parameters, $period['desde'], $period['hasta']);
    }
    $where = implode(' AND ', $conditions);
    $count = $db->prepare("SELECT COUNT(*) FROM atenciones a WHERE $where");
    $count->execute($parameters);
    $fields = implode(',', array_map(static fn (string $key): string => "'$key', JSON_EXTRACT(a.datos, '$.$key')", DASHBOARD_FIELDS));
    $rows = $db->prepare("SELECT a.id, a.estado, a.provincia, r.tipo, JSON_OBJECT($fields) AS datos
        FROM (SELECT id FROM atenciones a WHERE $where ORDER BY a.fecha DESC, a.id DESC LIMIT ".DASHBOARD_LIMIT.') AS selected
        JOIN atenciones a ON a.id=selected.id
        LEFT JOIN recursos r ON r.id=a.recurso_id AND r.provincia=a.provincia');
    $rows->execute($parameters);

    return ['total' => (int) $count->fetchColumn(), 'rows' => $rows];
}
