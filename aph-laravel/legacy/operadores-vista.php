<?php

use App\Security\PasswordPolicy;

$query = $db->prepare('SELECT o.id, o.usuario, COALESCE(p.nombre,o.nombre) AS nombre, o.perfil, o.activo
    FROM operadores o LEFT JOIN perfiles p ON p.provincia=o.provincia AND p.usuario=o.usuario
    WHERE o.provincia=? ORDER BY nombre, o.usuario');
$query->execute([$province]);
$operators = $query->fetchAll();
$token = h($_SESSION['token']);
?>
<a class="button back-link" href="./"><?= icon('back') ?> Volver al panel principal</a>
<div class="heading"><h1>Responsables APH de <?= h($territory['nombre']) ?></h1></div>
<p>Las cuentas personales identifican a quien registra una ficha y a quien realiza cada cambio.</p>
<?php if (isset($_GET['guardado'])) { ?><p class="success" role="status">Cuenta actualizada.</p><?php } ?>
<?php if ($errors) { ?><div class="errors" role="alert"><ul>
    <?php foreach ($errors as $error) { ?><li><?= h($error) ?></li><?php } ?>
</ul></div><?php } ?>
<form method="post" class="card">
    <h2>Crear cuenta personal</h2>
    <input type="hidden" name="_token" value="<?= $token ?>">
    <input type="hidden" name="accion" value="crear">
    <div class="grid">
        <div class="field"><label for="nombre">Nombres y apellidos</label>
            <input id="nombre" name="nombre" maxlength="200" required></div>
        <div class="field"><label for="nuevo-usuario">Usuario</label>
            <input id="nuevo-usuario" name="usuario" minlength="3" maxlength="40" autocapitalize="none" autocomplete="off" required></div>
        <div class="field"><label for="perfil">Perfil</label>
            <select id="perfil" name="perfil" required>
                <option value="">Selecciona un perfil</option>
                <?php foreach (OPERATOR_ROLES as $role) { ?><option><?= h($role) ?></option><?php } ?>
            </select></div>
        <div class="field"><label for="clave-operador">Contraseña inicial</label>
            <input id="clave-operador" name="clave_operador" type="password" minlength="6" maxlength="72" autocomplete="new-password" required>
            <small><?= h(PasswordPolicy::DESCRIPTION) ?></small></div>
    </div>
    <p class="actions"><button class="primary emphasis"><?= icon('add') ?> Crear cuenta</button></p>
</form>
<div class="card table"><table>
    <thead><tr><th>Responsable</th><th>Usuario</th><th>Perfil</th><th>Estado</th><th>Acciones</th></tr></thead>
    <tbody>
    <?php foreach ($operators as $operator) {
        $operatorId = h($operator['id']); ?>
        <tr>
            <td><?= h($operator['nombre']) ?></td>
            <td><?= h($operator['usuario']) ?></td>
            <td><?= h($operator['perfil']) ?></td>
            <td><span class="badge status <?= $operator['activo'] ? 'closed' : 'all' ?>"><?= $operator['activo'] ? 'Activa' : 'Inactiva' ?></span></td>
            <td>
                <form method="post">
                    <input type="hidden" name="_token" value="<?= $token ?>">
                    <input type="hidden" name="operador_id" value="<?= $operatorId ?>">
                    <input type="hidden" name="activo" value="<?= $operator['activo'] ? '0' : '1' ?>">
                    <button name="accion" value="estado"><?= $operator['activo'] ? 'Desactivar' : 'Activar' ?></button>
                </form>
                <details>
                    <summary>Restablecer contraseña</summary>
                    <form method="post" class="field">
                        <input type="hidden" name="_token" value="<?= $token ?>">
                        <input type="hidden" name="operador_id" value="<?= $operatorId ?>">
                        <label for="clave-<?= $operatorId ?>">Nueva contraseña</label>
                        <input id="clave-<?= $operatorId ?>" name="clave_operador" type="password" minlength="6" maxlength="72" autocomplete="new-password" required>
                        <button name="accion" value="clave">Guardar contraseña</button>
                    </form>
                </details>
            </td>
        </tr>
    <?php } ?>
    <?php if (! $operators) { ?><tr><td colspan="5">Todavía no hay cuentas personales registradas.</td></tr><?php } ?>
    </tbody>
</table></div>
