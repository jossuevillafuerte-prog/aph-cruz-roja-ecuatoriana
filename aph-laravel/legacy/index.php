<?php
declare(strict_types=1);
use App\Security\Security;
use App\Services\AttentionDrafts;
use App\Support\Asset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require_once __DIR__.'/modelo.php';
require_once __DIR__.'/cuentas.php';
require_once __DIR__.'/exportar.php';
require_once __DIR__.'/ui.php';
$view = is_string($_GET['vista'] ?? null) ? $_GET['vista'] : '';
$db = database();
$provinces = provinces();
$user = Auth::user();
$province = $request->attributes->get('scope_province');
$username = $user->usuario;
$account = findAccount($db, $province, $username);
$authenticated = true;
$territory = $provinces[$province] ?? ['nombre' => 'Nacional'];
$profileView = $view === 'perfil';
if ($view === 'avatar') {
    $query = $db->prepare('SELECT imagen, imagen_tipo FROM perfiles WHERE provincia=? AND usuario=? AND imagen IS NOT NULL');
    $query->execute([$user->province ?? 'nacional', $account['usuario']]);
    $avatar = $query->fetch();
    if (! $avatar) {
        aphStatus(404);
        aphExit();
    }
    aphHeader('Content-Type: '.$avatar['imagen_tipo']);
    echo $avatar['imagen'];
    aphExit();
}
$isAdmin = $user->role === 'superadmin';

$operatorView = $view === 'operadores';
if ($operatorView && ! $isAdmin) {
    aphStatus(403);
    aphExit('Esta cuenta no tiene permiso para realizar esta acción.');
}
if ($view === 'direccion') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ! is_string($_POST['_token'] ?? null)
        || ! hash_equals($_SESSION['token'], $_POST['_token'])) {
        aphStatus(403);
        aphExit();
    }

    [$point, $errors] = validateLocations(['evento_lat' => $_POST['lat'] ?? null, 'evento_lon' => $_POST['lon'] ?? null], []);
    if ($errors || ! isset($point['evento_lat'], $point['evento_lon'])) {
        aphStatus(422);
        aphExit();
    }
    require_once __DIR__.'/geocodificar.php';
    aphHeader('Content-Type: application/json; charset=utf-8');
    echo json_encode(['direccion' => reverseAddress($db, $point['evento_lat'], $point['evento_lon'])], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    aphExit();
}
if ($view === 'cie') {
    $term = trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');
    $page = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]) ?: 1;
    $offset = ($page - 1) * 50;
    $matches = [];
    $total = 0;
    if (strlen($term) <= 100) {
        $needle = searchText($term);
        foreach (diagnoses() as $entry) {
            if ($needle === '' || str_contains(searchText($entry['codigo'].' '.$entry['visible'].' '.$entry['descripcion']), $needle)) {
                if ($total++ < $offset || count($matches) >= 50) {
                    continue;
                }
                $matches[] = $entry;
            }
        }
    }
    aphHeader('Content-Type: application/json; charset=utf-8');
    echo json_encode(['items' => $matches, 'total' => $total], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    aphExit();
}
if ($view === 'exportar') {
    exportWorkbook($db, $province, $territory['nombre']);
}
if ($view === 'geojson') {
    exportLocations($db, $province, $territory['nombre']);
}
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$record = null;
$resourceView = $view === 'recursos';
$resourceQuery = $db->prepare('SELECT * FROM recursos WHERE provincia=? ORDER BY numero');
$resources = [];
if ($resourceView || $view === 'nueva' || isset($_GET['id'])) {
    $resourceQuery->execute([$province]);
    $resources = array_column($resourceQuery->fetchAll(), null, 'id');
}
$resourceFields = ['numero' => 'Número del recurso', 'ubicacion' => 'Ubicación del recurso', 'capacidad' => 'Capacidad de respuesta', 'combustible' => 'Tipo de combustible', 'tipo' => 'Tipo de recurso'];
$resource = null;
if ($resourceView && isset($_GET['id'])) {
    $resource = $resources[$id] ?? null;
    if (! $resource) {
        aphStatus(404);
        aphExit('Recurso no encontrado.');
    }
}
if (! $resourceView && ! $operatorView && isset($_GET['id'])) {
    $query = $db->prepare('SELECT * FROM atenciones WHERE id=? AND provincia=? AND eliminado IS NULL');
    $query->execute([$id ?: 0, $province]);
    $record = $query->fetch();
    if (! $record) {
        aphStatus(404);
        aphExit('Atención no encontrada.');
    }
}
$deleteView = $view === 'eliminar';
$printView = $view === 'imprimir';
if (($deleteView || $printView) && ! $record) {
    aphStatus(404);
    aphExit('Atención no encontrada.');
}
$editing = $view === 'nueva' || $record;
$data = $record ? json_decode($record['datos'], true, 512, JSON_THROW_ON_ERROR) + array_fill_keys(array_keys(FIELDS), '') : [];
$savedData = $data;
if (! $record && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $data['B'] = date('Y-m-d');
}
$previousResponsible = $savedData['Y'] ?? '';
$responsible = $previousResponsible !== '' ? $previousResponsible : accountLabel($account);
$responsibleId = $record['responsable_id'] ?? ($previousResponsible === '' ? $account['id'] : null);
$errors = [];
$reason = '';
$formVersion = (string) ($record['version'] ?? 0);
$requestId = Str::isUuid($_POST['solicitud'] ?? null) ? strtolower($_POST['solicitud']) : (string) Str::uuid();
$closed = ($record['estado'] ?? '') === 'Cerrada';
$catalog = $editing ? catalog($territory) : [];
$resourceInput = $resource ?? ['tipo' => 'Ambulancia'];
$resourceId = $record['recurso_id'] ?? null;
$profileName = $account['nombre'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! is_string($_POST['_token'] ?? null) || ! hash_equals($_SESSION['token'], $_POST['_token'])) {
        aphStatus(403);
        aphExit('La sesión del formulario expiró y los cambios no se guardaron. Vuelve a abrirlo.');
    }
    if ($profileView) {
        $profileName = is_string($_POST['nombre'] ?? null) ? trim($_POST['nombre']) : '';
        [$errors, $newCredential] = saveProfile($db, $province, $account, $_POST, $_FILES['imagen'] ?? null);
        if (! $errors) {
            if ($newCredential !== null) {
                request()->session()->regenerate();
                $_SESSION['credencial'] = $newCredential;
                $_SESSION['token'] = request()->session()->token();
            }
            aphHeader('Location: ?vista=perfil&guardado=1', true, 303);
            aphExit();
        }
        aphStatus(422);
    } elseif ($operatorView) {
        $errors = manageOperators($db, $province, $_POST);
        if (! $errors) {
            aphHeader('Location: ?vista=operadores&guardado=1', true, 303);
            aphExit();
        }
        aphStatus(422);
    } elseif ($resourceView) {
        foreach ($resourceFields as $key => $label) {
            $value = $_POST[$key] ?? ($key === 'tipo' ? $resourceInput[$key] : '');
            $resourceInput[$key] = trim(is_string($value) ? $value : '');
            if ($resourceInput[$key] === '' || strlen($resourceInput[$key]) > 300) {
                $errors[] = 'Revisa el campo «'.$label.'».';
            }
        }
        if (! $errors) {
            try {
                DB::transaction(function () use ($db, $resource, $resourceInput, $resourceFields, $province, $id): void {
                    if ($resource) {
                        $db->prepare('UPDATE recursos SET ubicacion=?, capacidad=?, combustible=?, tipo=? WHERE id=? AND provincia=?')
                            ->execute([$resourceInput['ubicacion'], $resourceInput['capacidad'], $resourceInput['combustible'], $resourceInput['tipo'], $id, $province]);
                    } else {
                        $db->prepare('INSERT INTO recursos (provincia,numero,ubicacion,capacidad,combustible,tipo) VALUES (?,?,?,?,?,?)')
                            ->execute([$province, ...array_map(static fn ($key) => $resourceInput[$key], array_keys($resourceFields))]);
                    }
                    Security::event('application_write', null, 'recurso:'.($resource ? $id : $db->lastInsertId()));
                });
                aphHeader('Location: ?vista=recursos&guardado=1', true, 303);
                aphExit();
            } catch (PDOException $error) {
                if (($error->errorInfo[0] ?? '') !== '23000') {
                    throw $error;
                }
                $errors[] = 'Ya existe un recurso con ese número en tu provincia.';
            }
        }
        aphStatus(422);
    } elseif ($deleteView) {
        if ($formVersion === ($_POST['version'] ?? null) && deleteAttention($db, $province, $record, $account)) {
            aphHeader('Location: '.($isAdmin ? '/national?view=atenciones&eliminada=1' : '?vista=atenciones&eliminada=1'), true, 303);
            aphExit();
        }
        aphStatus(409);
        $errors[] = 'La atención cambió. Revisa los datos y confirma de nuevo.';
    } elseif (! $editing || $printView) {
        aphStatus(405);
        aphExit();
    } else {
        $action = $_POST['accion'] ?? '';
        $validAction = $closed ? $action === 'corregir' : in_array($action, ['guardar', 'cerrar'], true);
        $nextState = $action === 'guardar' ? 'Pendiente' : 'Cerrada';
        $posted = $_POST;
        $posted['Y'] = $responsible;
        $selected = $_POST['recurso_id'] ?? '';
        $validResource = is_string($selected) && ($selected === '' || isset($resources[$selected]));
        $resourceId = $validResource && $selected !== '' ? (int) $selected : null;
        $posted['J'] = $resourceId !== null ? $resources[$resourceId]['numero'] : '';
        if ($selected === '' && ! empty($_POST['conservar_recurso']) && empty($record['recurso_id'])) {
            $posted['J'] = $savedData['J'] ?? '';
        }
        [$data, $errors] = validate($posted, $catalog, $nextState === 'Cerrada', $data);
        if ($resourceId !== null) {
            $data['recurso_tipo'] = ($record['recurso_id'] ?? null) === $resourceId
                ? ($savedData['recurso_tipo'] ?? $resources[$resourceId]['tipo']) : $resources[$resourceId]['tipo'];
        } elseif ($data['J'] !== '' && $data['J'] === ($savedData['J'] ?? '') && isset($savedData['recurso_tipo'])) {
            $data['recurso_tipo'] = $savedData['recurso_tipo'];
        }
        [$locations, $locationErrors] = validateLocations($posted, $savedData);
        $data += $locations + preservedData($savedData);
        $errors = array_merge($errors, $locationErrors);
        if (! $validResource) {
            addFieldError($errors, 'J', 'Selecciona un recurso de tu provincia.');
        }
        $reason = trim(is_string($_POST['motivo'] ?? null) ? $_POST['motivo'] : '');
        $submittedVersion = is_string($_POST['version'] ?? null) ? $_POST['version'] : '';
        if (! $validAction || $formVersion !== $submittedVersion) {
            $errors[] = 'El estado o la versión cambió. Vuelve a abrir el formulario.';
        }
        $formVersion = $submittedVersion;
        if (($closed && $reason === '') || strlen($reason) > 1000) {
            addFieldError($errors, 'motivo', 'Ingresa un motivo de corrección de hasta 1000 caracteres.');
        }
        if (! $errors) {
            try {
                $defaultReason = match (true) {
                    ! $record => $nextState === 'Cerrada' ? 'Creación y cierre de atención' : 'Creación de atención',
                    $nextState === 'Cerrada' => 'Cierre de atención',
                    default => 'Actualización de atención pendiente',
                };
                $id = save($db, $province, $record ?: null, $data, $nextState, $reason !== '' ? $reason : $defaultReason, $resourceId, $account, $responsibleId, $requestId);
                AttentionDrafts::forget((int) $user->id, $record ? AttentionDrafts::recordKey((int) $record['id']) : AttentionDrafts::newKey($province));
                aphHeader('Location: ?id='.$id.'&guardado=1&'.$request->attributes->get('attention_return_context'), true, 303);
                aphExit();
            } catch (DomainException $error) {
                $errors[] = $error->getMessage();
            }
        }
        aphStatus(422);
    }
}
$groups = [
    'Evento y despacho' => ['B', 'C', 'F', 'G', 'H', 'I', 'J', 'K'],
    'Paciente y diagnóstico' => ['L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U'],
    'Destino y operación' => ['W', 'X', 'Z', 'AA', 'AB', 'AC', 'AD'],
];
$filter = $_GET['estado'] ?? '';
$filter = in_array($filter, ['Pendiente', 'Cerrada'], true) ? $filter : '';
$statusClasses = ['Pendiente' => 'pending', 'Cerrada' => 'closed'];
$dashboardView = $view === '' && ! isset($_GET['estado']) && ! isset($_GET['eliminada']) && ! $editing;
$navigation = [
    ['dashboard', $isAdmin ? '/national' : './', 'Panel principal', $dashboardView],
    ['list', $isAdmin ? '/national?view=atenciones' : '?vista=atenciones', 'Atenciones', ! $dashboardView && ! $profileView && ! $resourceView && ! $operatorView],
    ['ambulance', $isAdmin ? '/?vista=recursos&select_province=1' : '?vista=recursos', 'Recursos', $resourceView],
    ['profile', '?vista=perfil', 'Mi perfil', $profileView],
];
if ($profileView) {
    $navigation[] = ['check', '/security', 'Seguridad', false];
}
if ($isAdmin) {
    $navigation[] = ['team', '/users', 'Usuarios nacionales', false];
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registro APH · <?= h($territory['nombre']) ?></title>
    <link rel="stylesheet" href="<?= h(Asset::url('estilos.css')) ?>">
    <script src="<?= h(Asset::url('catalogo.js')) ?>" defer></script><script src="<?= h(Asset::url('/form-feedback.js')) ?>" defer></script><script src="<?= h(Asset::url('/attention-draft.js')) ?>" defer></script>
    <meta name="csrf-token" content="<?= h(csrf_token()) ?>"><meta name="session-user" content="<?= h((string) $user->id) ?>"><script src="<?= h(Asset::url('/session-security.js')) ?>" defer></script>
    <?php if ($printView) { ?><link rel="stylesheet" href="<?= h(Asset::url('impresion.css')) ?>"><script src="<?= h(Asset::url('impresion.js')) ?>" defer></script><?php } ?>
    <?php if ($dashboardView || ($editing && ! $deleteView && ! $printView)) { ?>
        <link rel="stylesheet" href="<?= h(Asset::url('leaflet/leaflet.css')) ?>">
        <script src="<?= h(Asset::url('leaflet/leaflet.js')) ?>" defer></script>
    <?php } ?>
    <?php if ($dashboardView) { ?>
        <script src="<?= h(Asset::url('dashboard-mapas.js')) ?>" defer></script>
    <?php } elseif ($editing && ! $deleteView && ! $printView) { ?>
        <script src="<?= h(Asset::url('ubicaciones.js')) ?>" defer></script>
    <?php } ?>
</head>
<body>
<header class="app-header"><div class="header-inner">
    <div class="header-brand">
        <button type="button" id="menu-open" class="icon-button" aria-label="Abrir menú" aria-controls="app-menu" aria-expanded="false"><?= icon('menu') ?></button>
        <a class="identity" href="<?= $isAdmin ? '/national' : '/' ?>"><img src="marca/cre-institucional.png" alt="Cruz Roja Ecuatoriana"><span>Registro APH</span></a>
    </div>
    <div class="header-tools">
        <span class="province-chip"><?= icon('pin') ?> <?= h($territory['nombre']) ?></span>
        <a class="profile-link" href="?vista=perfil" <?= $profileView ? 'aria-current="page"' : '' ?>>
            <?= accountAvatar($account) ?>
            <span><strong><?= h($account['nombre']) ?></strong><small>Mi perfil</small></span>
        </a>
        <form method="post" action="/logout"><input type="hidden" name="_token" value="<?= h($_SESSION['token']) ?>">
            <button class="icon-button logout" aria-label="Cerrar sesión"><?= icon('exit') ?></button></form>
    </div>
</div></header>
<dialog id="app-menu" class="app-menu" aria-labelledby="menu-title">
    <div class="menu-heading"><h2 id="menu-title">Registro APH</h2>
        <button type="button" id="menu-close" class="icon-button" aria-label="Cerrar menú"><?= icon('close') ?></button>
    </div>
    <p><?= h($territory['nombre']) ?></p>
    <nav aria-label="Menú principal">
        <?php foreach ($navigation as [$symbol, $href, $label, $active]) { ?>
            <a class="button emphasis" href="<?= h($href) ?>" <?= $active ? 'aria-current="page"' : '' ?>><?= icon($symbol) ?> <?= h($label) ?></a>
        <?php } ?>
    </nav>
    <form method="post" action="/logout">
        <input type="hidden" name="_token" value="<?= h($_SESSION['token']) ?>">
        <button class="danger"><?= icon('exit') ?> Cerrar sesión</button>
    </form>
</dialog>
<main class="module-page">
<?php if (! $dashboardView && ! $printView) {
    $editingAttention = $record && ! $deleteView && ! $resourceView && ! $profileView;
    ?><a class="button back-link emphasis" href="<?= h($editingAttention ? $request->attributes->get('attention_back_url') : ($isAdmin ? '/national' : '/')) ?>"><?= icon('back') ?> <?= $editingAttention ? 'Volver a atenciones' : 'Volver al panel principal' ?></a><?php } ?>


<?php if ($dashboardView) { ?>
    <?php require __DIR__.'/dashboard-vista.php'; ?>
<?php } elseif ($profileView) { ?>
    <?php require __DIR__.'/perfil-vista.php'; ?>
<?php } elseif ($operatorView) { ?>
    <?php require __DIR__.'/operadores-vista.php'; ?>
<?php } elseif ($resourceView) { ?>

    <div class="heading"><h1>Recursos de <?= h($territory['nombre']) ?></h1></div>
    <?php if (isset($_GET['guardado'])) { ?><p class="success" role="status">Recurso guardado.</p><?php } ?>
    <?php if ($errors) { ?><div class="errors" role="alert"><ul>
        <?php foreach ($errors as $error) { ?><li><?= h($error) ?></li><?php } ?>
    </ul></div><?php } ?>
    <form method="post" class="card">
        <h2><?= $resource ? 'Editar recurso' : 'Registrar recurso' ?></h2>
        <input type="hidden" name="_token" value="<?= h($_SESSION['token']) ?>">
        <div class="grid">
            <?php foreach ($resourceFields as $key => $label) { ?>
                <div class="field"><label for="r-<?= $key ?>"><?= h($label) ?></label>
                <input id="r-<?= $key ?>" name="<?= $key ?>" value="<?= h($resourceInput[$key] ?? '') ?>" maxlength="300" required <?= $key === 'numero' && $resource ? 'readonly' : '' ?>></div>
            <?php } ?>
        </div>
        <p class="actions"><button class="primary emphasis"><?= icon($resource ? 'check' : 'add') ?> <?= $resource ? 'Guardar cambios' : 'Agregar recurso' ?></button>
        <?php if ($resource) { ?><a class="button" href="?vista=recursos">Cancelar</a><?php } ?></p>
    </form>
    <div class="card table"><table>
        <thead><tr><?php foreach ($resourceFields as $label) { ?><th><?= h($label) ?></th><?php } ?><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($resources as $item) { ?><tr>
            <?php foreach ($resourceFields as $key => $label) { ?><td><?= h($item[$key]) ?></td><?php } ?>
            <td><a class="button" href="?vista=recursos&amp;id=<?= h($item['id']) ?>"><?= icon('edit') ?> Editar</a></td>
        </tr><?php } ?>
        <?php if (! $resources) { ?><tr><td colspan="6" class="empty-state">Todavía no hay recursos registrados. <a class="button" href="#r-numero">Agregar el primer recurso</a></td></tr><?php } ?>
        </tbody>
    </table></div>
<?php } elseif ($deleteView) { ?>
    <div class="heading"><h1>Eliminar atención #<?= h($id) ?></h1></div>
    <div class="card">
        <?php foreach ($errors as $error) { ?><p class="errors" role="alert"><?= h($error) ?></p><?php } ?>
        <p>La atención del <?= h($data['B']) ?> dejará de aparecer en el listado y en la exportación. La eliminación quedará registrada en el historial.</p>
        <form class="actions" method="post">
            <input type="hidden" name="_token" value="<?= h($_SESSION['token']) ?>">
            <input type="hidden" name="version" value="<?= h($formVersion) ?>">
            <a class="button" href="?vista=atenciones">Cancelar</a>
            <button class="danger"><?= icon('delete') ?> Confirmar eliminación</button>
        </form>
    </div>
<?php } elseif ($printView) { ?>
    <?php require __DIR__.'/impresion-vista.php'; ?>
<?php } elseif (! $editing) { ?>

    <?php
        $query = $db->prepare('SELECT estado, COUNT(*) FROM atenciones WHERE provincia=? AND eliminado IS NULL GROUP BY estado');
        $query->execute([$province]);
        $counts = array_map(intval(...), $query->fetchAll(PDO::FETCH_KEY_PAIR));
        $lastPage = max(1, (int) ceil(($filter === '' ? array_sum($counts) : $counts[$filter] ?? 0) / ATTENTIONS_PER_PAGE));
        $page = min($lastPage, filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1);
        $query = $db->prepare("SELECT id, version, estado, datos FROM atenciones WHERE provincia=? AND eliminado IS NULL AND (?='' OR estado=?) ORDER BY id DESC LIMIT ".ATTENTIONS_PER_PAGE.' OFFSET '.(($page - 1) * ATTENTIONS_PER_PAGE));
        $query->execute([$province, $filter, $filter]);
        $records = $query->fetchAll();
        $pageUrl = static fn (int $number): string => '/?'.http_build_query(['vista' => 'atenciones', 'estado' => $filter, 'pagina' => $number]);
        ?>
    <?php if (isset($_GET['eliminada'])) { ?><p class="success" role="status">Atención eliminada.</p><?php } ?>
    <div class="heading dashboard-heading"><div><p class="eyebrow">ATENCIÓN PREHOSPITALARIA</p><h1>Atenciones de <?= h($territory['nombre']) ?></h1><p class="heading-description">Registro y seguimiento de atenciones prehospitalarias.</p></div>
    <div class="actions"><a class="button emphasis" href="?vista=recursos"><?= icon('ambulance') ?> Recursos</a>
    <?php if ($isAdmin) { ?>
        <a class="button emphasis" href="/users"><?= icon('team') ?> Responsables APH</a>
    <?php } ?>
    <a class="button primary emphasis" href="?vista=nueva"><?= icon('add') ?> Nueva atención</a></div></div>
    <nav class="filter-bar attention-kpis" aria-label="Filtrar atenciones por estado">
    <?php foreach ([['', 'all', 'list', 'Todas', array_sum($counts)], ['Cerrada', 'closed', 'check', 'Cerradas', $counts['Cerrada'] ?? 0], ['Pendiente', 'pending', 'clock', 'Pendientes', $counts['Pendiente'] ?? 0]] as [$key,$style,$symbol,$label,$count]) { ?>
        <a class="button status emphasis <?= h($style) ?>" data-state="<?= h($key) ?>" data-count="<?= h($count) ?>" href="<?= h('/?'.http_build_query(['vista' => 'atenciones', 'estado' => $key])) ?>"<?= $filter === $key ? ' aria-current="page"' : '' ?>><?= icon($symbol) ?><span><?= h($label) ?><strong><?= h($count) ?></strong></span></a>
    <?php } ?>
    </nav>
    <div class="card module-controls"><label for="attention-province">Provincia</label><select id="attention-province" disabled><option><?= h($territory['nombre']) ?></option></select>
    <div class="actions export-actions">
        <a class="button" href="?vista=exportar"><?= icon('download') ?> Exportar cerradas a Excel</a>
        <a class="button" href="?vista=geojson"><?= icon('pin') ?> Exportar ubicaciones (GeoJSON)</a>
    </div>
    </div><div class="card table"><table>
        <thead><tr><th>Hoja de atención</th><th>Fecha</th><th>Cantón</th><th>Código APH</th><th>Estado</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($records as $item) {
            $values = json_decode($item['datos'], true, 512, JSON_THROW_ON_ERROR) + array_fill_keys(array_keys(FIELDS), '');
            ?>
            <tr class="<?= $item['estado'] === 'Pendiente' ? 'attention-pending' : '' ?>"><td><a href="?id=<?= h($item['id']) ?>&amp;return_estado=<?= h($filter) ?>&amp;return_page=<?= h($page) ?>"><?= h($values['K'] ?: 'Sin número de hoja') ?></a></td>
            <td><?= h($values['B']) ?></td><td><?= h($values['G'] ?: '—') ?></td>
            <td><?= h($values['C'] ?: '—') ?></td><td><span class="badge status <?= $statusClasses[$item['estado']] ?>"><?= icon($item['estado'] === 'Cerrada' ? 'check' : 'clock') ?> <?= h($item['estado']) ?></span></td>
            <td><div class="row-actions">
                <?php if (true) { ?>
                    <a class="button icon-button" href="?id=<?= h($item['id']) ?>&amp;return_estado=<?= h($filter) ?>&amp;return_page=<?= h($page) ?>" aria-label="Editar hoja <?= h($values['K'] ?: 'sin número') ?>"><?= icon('edit') ?></a>
                <?php } ?>
                <button type="button" class="button icon-button danger emphasis" data-delete-action="/?vista=eliminar&amp;id=<?= h($item['id']) ?>" data-version="<?= h($item['version']) ?>" data-province="<?= h($province) ?>" data-sheet="<?= h($values['K']) ?>" aria-label="Eliminar hoja <?= h($values['K'] ?: 'sin número') ?>"><?= icon('delete') ?></button>
                <a class="button icon-button" href="?vista=imprimir&amp;id=<?= h($item['id']) ?>" aria-label="Imprimir hoja <?= h($values['K'] ?: 'sin número') ?> o guardar como PDF"><?= icon('print') ?></a>
            </div></td></tr>
        <?php } if (! $records) { ?>
            <tr><td colspan="6" class="empty-state"><?php if ($filter !== '') { ?>No hay atenciones <?= $filter === 'Pendiente' ? 'pendientes' : 'cerradas' ?>. <a class="button" href="?vista=atenciones">Ver todas las atenciones</a><?php } else { ?>Todavía no hay atenciones registradas en <?= h($territory['nombre']) ?>. <a class="button primary" href="?vista=nueva"><?= icon('add') ?> Registrar la primera atención</a><?php } ?></td></tr>
        <?php } ?>
        </tbody>
    </table></div>
    <div class="actions"><?php if ($page > 1) { ?><a class="button emphasis" href="<?= h($pageUrl($page - 1)) ?>">Anterior</a><?php } ?><span>Página <?= h($page) ?> de <?= h($lastPage) ?></span><?php if ($page < $lastPage) { ?><a class="button emphasis" href="<?= h($pageUrl($page + 1)) ?>">Siguiente</a><?php } ?></div>
<?php } else { ?>

    <div class="heading"><h1><?= $record ? 'Hoja de atención '.h($savedData['K'] ?: 'sin número') : 'Nueva atención' ?></h1>
    <span class="badge status <?= $statusClasses[$record['estado'] ?? ''] ?? 'all' ?>"><?= h($record['estado'] ?? 'Sin guardar') ?></span></div>
    <?php if (isset($_GET['guardado']) && ! $errors) { ?><p class="success" role="status">Atención guardada.</p><?php } ?>
    <?= errorSummary($errors, [...array_merge(...array_values($groups)), 'motivo']) ?>
    <p>Para guardar un pendiente basta la fecha. Para cerrar, completa también el código APH y el cantón.</p>
    <?php
    $draftKey = $record ? AttentionDrafts::recordKey((int) $record['id']) : AttentionDrafts::newKey($province);
    $draft = $_SERVER['REQUEST_METHOD'] === 'POST' ? null : AttentionDrafts::find((int) $user->id, $draftKey);
    ?>
    <form method="post" data-draft-key="<?= h($draftKey) ?>" data-draft-version="<?= h($formVersion) ?>"<?= $draft ? ' data-draft="'.h(json_encode(['fields' => $draft['fields'], 'version' => $draft['version'], 'saved_at' => date('d/m/Y H:i', strtotime($draft['saved_at']))], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)).'"' : '' ?>>
        <input type="hidden" name="_token" value="<?= h($_SESSION['token']) ?>">
        <input type="hidden" name="version" value="<?= h($formVersion) ?>">
        <?php if (! $record) { ?><input type="hidden" name="solicitud" value="<?= h($requestId) ?>"><?php } ?>
        <?php foreach ($groups as $title => $keys) { ?>
            <fieldset class="card"><legend><?= h($title) ?></legend><div class="grid">
            <?php foreach ($keys as $key) {
                $label = fieldLabel($key);
                $value = $data[$key] ?? '';
                $type = match ($key) {
                    'B' => 'date', 'I' => 'time', 'P','Z','AA' => 'number', default => 'text'
                };
                ?>
                <div class="field"><label for="f-<?= $key ?>"><?= h($label) ?></label>
                <?php if ($key === 'J') { ?>
                    <select id="f-J" name="recurso_id"<?= fieldErrorAttributes($errors, 'J') ?>>
                        <option value="">Selecciona un recurso</option>
                        <?php foreach ($resources as $item) { ?>
                            <option value="<?= h($item['id']) ?>" <?= $resourceId === (int) $item['id'] ? 'selected' : '' ?>><?= h($item['numero'].' · '.$item['ubicacion']) ?></option>
                        <?php } ?>
                    </select>
                    <?php if (empty($record['recurso_id']) && ! empty($savedData['J'])) { ?>
                        <label><input type="checkbox" name="conservar_recurso" value="1" <?= $_SERVER['REQUEST_METHOD'] !== 'POST' || ! empty($_POST['conservar_recurso']) ? 'checked' : '' ?>>
                            Conservar valor anterior: <?= h($savedData['J']) ?></label>
                    <?php } ?>
                    <?php if (! $resources) { ?><small>Agrega una unidad en <a href="?vista=recursos">Recursos</a> antes de asignarla.</small><?php } ?>
                <?php } elseif ($key === 'F') { ?>
                    <select id="f-F" name="F"<?= fieldErrorAttributes($errors, 'F') ?>><option value="">Selecciona un tipo o subtipo</option>
                        <?php if (! empty($savedData['F']) && ! in_array($savedData['F'], $catalog['lists']['F'], true)) { ?>
                            <option value="<?= h($savedData['F']) ?>" <?= $value === $savedData['F'] ? 'selected' : '' ?>><?= h($savedData['F']) ?> (registro anterior)</option>
                        <?php } ?>
                        <?php foreach ($catalog['events'] as $code => $event) {
                            if ($event['parent'] !== '') {
                                continue;
                            } ?>
                            <optgroup label="<?= h($code.' · '.$event['name']) ?>">
                                <option value="<?= h($event['name']) ?>" <?= $value === $event['name'] ? 'selected' : '' ?>><?= h($code.' · '.$event['name']) ?></option>
                                <?php foreach ($catalog['events'] as $subcode => $sub) {
                                    if ($sub['parent'] !== $code) {
                                        continue;
                                    } ?>
                                    <option value="<?= h($sub['name']) ?>" <?= $value === $sub['name'] ? 'selected' : '' ?>><?= h($subcode.' · '.$sub['name']) ?></option>
                                <?php } ?>
                            </optgroup>
                        <?php } ?>
                    </select>
                <?php } elseif (isset($catalog['lists'][$key])) { ?>
                    <select id="f-<?= $key ?>" name="<?= $key ?>"<?= fieldErrorAttributes($errors, $key) ?>>
                        <option value="">Selecciona una opción</option>
                        <?php if ($key === 'G' && ! empty($savedData['G']) && ! in_array($savedData['G'], $catalog['lists']['G'], true)) { ?>
                            <option value="<?= h($savedData['G']) ?>" <?= $value === $savedData['G'] ? 'selected' : '' ?>><?= h($savedData['G']) ?> (registro anterior)</option>
                        <?php } ?>
                        <?php foreach ($catalog['lists'][$key] as $option) { ?>
                            <option <?= $option === $value ? 'selected' : '' ?>><?= h($option) ?></option>
                        <?php } ?>
                    </select>
                <?php } elseif (in_array($key, ['T', 'AD'], true)) { ?>
                    <textarea id="f-<?= $key ?>" name="<?= $key ?>" maxlength="4000"<?= fieldErrorAttributes($errors, $key) ?>><?= h($value) ?></textarea>
                <?php } elseif ($key === 'U') { ?>
                    <input id="f-U" name="U" value="<?= h($value) ?>" placeholder="Ej. U07.1" autocomplete="off"<?= fieldErrorAttributes($errors, 'U', 'cie-description') ?>>
                    <small id="cie-description"><?= h($data['V'] ?? '') ?></small>
                    <details id="cie-picker"><summary>Ver listado completo de CIE-10</summary>
                        <label for="cie-search">Buscar por código o descripción</label>
                        <input id="cie-search" type="search" autocomplete="off" maxlength="100" placeholder="Filtrar listado">
                        <small id="cie-status" role="status"></small><div id="cie-results"></div>
                        <div class="actions"><button id="cie-prev" type="button" disabled>Anterior</button><button id="cie-next" type="button" disabled>Siguiente</button></div>
                    </details>
                <?php } elseif ($key === 'N') { ?>
                    <input id="f-N" name="N" value="<?= h($value) ?>" autocomplete="off"<?= fieldErrorAttributes($errors, 'N', 'documento-ayuda') ?>>
                    <small id="documento-ayuda">Selecciona el tipo de documento.</small>
                <?php } else { ?>
                    <input id="f-<?= $key ?>" name="<?= $key ?>" type="<?= $type ?>" value="<?= h($value) ?>"
                        <?= $key === 'B' ? 'required' : '' ?> <?= $type === 'number' ? 'min="0"' : '' ?>
                        <?= $key === 'P' ? 'max="120"' : '' ?> <?= in_array($key, ['Z', 'AA'], true) ? 'step="any"' : '' ?><?= fieldErrorAttributes($errors, $key) ?>>
                <?php } ?>
                <?= fieldErrorMessage($errors, $key) ?>
                <?php if (in_array($key, ['H', 'W'], true)) {
                    $point = $key === 'H' ? 'evento' : 'destino';
                    $latitude = $data[$point.'_lat'] ?? null;
                    $longitude = $data[$point.'_lon'] ?? null;
                    ?>
                    <div class="location-picker" data-field="f-<?= $key ?>"
                        data-tiles="<?= h(config('maps.tiles')) ?>"
                        data-attribution="<?= h(config('maps.attribution')) ?>"
                        data-islands="<?= $province === 'galapagos' ? '1' : '0' ?>">
                        <input type="hidden" name="<?= $point ?>_lat" value="<?= h((string) $latitude) ?>">
                        <input type="hidden" name="<?= $point ?>_lon" value="<?= h((string) $longitude) ?>">
                        <small class="location-status" role="status"><?= $latitude !== null && $longitude !== null ? 'Punto de referencia marcado' : 'Sin punto marcado' ?></small>
                        <button type="button" class="map-open"><?= icon('pin') ?> Marcar en el mapa</button>
                        <dialog aria-labelledby="map-title-<?= $point ?>">
                            <h2 id="map-title-<?= $point ?>"><?= h(LOCATIONS[$point]) ?></h2>
                            <p class="map-help">Coloca el pin y confirma para completar la ubicación de referencia.</p>
                            <div class="location-map" aria-label="<?= h(LOCATIONS[$point]) ?>"></div>
                            <small class="map-status" role="status"></small>
                            <small>Direcciones aproximadas de <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>.</small>
                            <div class="actions">
                                <button type="button" class="map-cancel">Cancelar</button>
                                <button type="button" class="map-center">Usar centro del mapa</button>
                                <button type="button" class="map-confirm primary" disabled>Confirmar ubicación</button>
                            </div>
                        </dialog>
                        <button type="button" class="map-clear" <?= $latitude === null || $longitude === null ? 'hidden' : '' ?>>Quitar punto</button>
                    </div>
                <?php } ?>
                </div>
                <?php if ($key === 'AA') { ?>
                    <div class="field"><label for="km-recorridos">Kilómetros recorridos</label>
                        <output id="km-recorridos" class="calculated" for="f-Z f-AA" aria-live="polite">—</output>
                        <small>Llegada menos salida.</small>
                    </div>
                <?php } ?>
            <?php } ?>
            </div></fieldset>
        <?php } ?>
        <div class="actions">
        <?php if ($closed) { ?>
            <label for="motivo">Motivo de la corrección</label>
            <input id="motivo" name="motivo" maxlength="1000" required value="<?= h($reason) ?>"<?= fieldErrorAttributes($errors, 'motivo') ?>>
            <?= fieldErrorMessage($errors, 'motivo') ?>
            <button class="primary" name="accion" value="corregir" data-shortcut-save aria-keyshortcuts="Control+S"><?= icon('check') ?> Guardar corrección <kbd>Ctrl+S</kbd></button>
        <?php } else { ?>
            <button name="accion" value="guardar" data-shortcut-save aria-keyshortcuts="Control+S"><?= icon('clock') ?> Guardar pendiente <kbd>Ctrl+S</kbd></button>
            <button class="primary" name="accion" value="cerrar"><?= icon('check') ?> Cerrar atención</button>
        <?php } ?>
        </div>
    </form>
    <?php if ($record) {
        $history = $db->prepare('SELECT * FROM cambios WHERE atencion=? ORDER BY id DESC');
        $history->execute([$id]);
        ?>
    <section class="card"><h2>Historial de cambios</h2>
    <?php foreach ($history as $change) { ?>
        <details><summary><?= h($change['fecha']) ?> · <?= h($change['motivo']) ?></summary>
        <p><?= h($change['autor']) ?></p><ul>
        <?php foreach (json_decode($change['detalle'], true, 512, JSON_THROW_ON_ERROR) as $field => $difference) { ?>
            <li><?= h($field) ?> · <?php if (is_array($difference) && array_is_list($difference) && count($difference) === 2) {
                [$old, $new] = $difference; ?>
                <?= h($old === '' || $old === null ? '—' : (string) $old) ?> → <?= h($new === '' || $new === null ? '—' : (string) $new) ?>
            <?php } else { ?><?= h(is_scalar($difference) ? (string) $difference : json_encode($difference, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) ?><?php } ?></li>
        <?php } ?>
        </ul></details>
    <?php } ?>
    </section>
    <?php } ?>
<?php } ?>
</main>
<?php require __DIR__.'/delete-dialog.php'; ?>
</body>
</html>

