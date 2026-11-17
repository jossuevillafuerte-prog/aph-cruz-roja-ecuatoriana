<?php use App\Security\PasswordPolicy;

?>
<div class="heading"><div><p class="eyebrow">CUENTA PERSONAL</p><h1>Mi perfil</h1></div></div>
<?php if (isset($_GET['guardado'])) { ?><p class="success" role="status">Perfil actualizado.</p><?php } ?>
<?php if ($errors) { ?><div class="errors" role="alert"><ul>
    <?php foreach ($errors as $error) { ?><li><?= h($error) ?></li><?php } ?>
</ul></div><?php } ?>
<form method="post" enctype="multipart/form-data" class="profile-form">
    <input type="hidden" name="_token" value="<?= h($_SESSION['token']) ?>">
    <section class="card">
        <div class="profile-intro"><?= accountAvatar($account) ?>
            <div><h2>Datos de la cuenta</h2><p><?= h($account['usuario']) ?> · <?= h($territory['nombre']) ?></p></div>
        </div>
        <div class="grid">
            <div class="field"><label for="perfil-nombre">Nombre visible</label>
                <input id="perfil-nombre" name="nombre" value="<?= h($profileName) ?>" maxlength="200" autocomplete="name" required></div>
            <div class="field"><label for="perfil-imagen">Imagen de perfil</label>
                <input id="perfil-imagen" name="imagen" type="file" accept="image/jpeg,image/png,image/webp">
                <small>JPG, PNG o WebP. Máximo 1 MB y 2048 × 2048 píxeles.</small>
                <?php if ($account['tiene_imagen']) { ?><label><input type="checkbox" name="quitar_imagen" value="1"> Quitar imagen actual</label><?php } ?>
            </div>
        </div>
    </section>
    <section class="card">
        <h2>Cambiar contraseña</h2>
        <p><?= h(PasswordPolicy::DESCRIPTION) ?></p>
        <div class="grid">
            <div class="field"><label for="perfil-actual">Contraseña actual</label><input id="perfil-actual" type="password" name="clave_actual" autocomplete="current-password" maxlength="72"></div>
            <div class="field"><label for="perfil-nueva">Contraseña nueva</label><input id="perfil-nueva" type="password" name="clave_nueva" autocomplete="new-password" minlength="8" maxlength="72"></div>
            <div class="field"><label for="perfil-repetida">Confirmar contraseña nueva</label><input id="perfil-repetida" type="password" name="clave_repetida" autocomplete="new-password" minlength="8" maxlength="72"></div>
        </div>
    </section>
    <button class="primary emphasis"><?= icon('check') ?> Guardar perfil</button>
</form>
