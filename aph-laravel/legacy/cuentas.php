<?php

declare(strict_types=1);
use App\Security\PasswordPolicy;
use Illuminate\Support\Facades\Auth;

const OPERATOR_ROLES = ['Técnico APH', 'Operador de cabina APH'];

function findAccount(PDO $db, string $province, string $username): ?array
{
    $u = Auth::user();
    if (! $u) {
        return null;
    }
    $query = $db->prepare('SELECT imagen IS NOT NULL AS present FROM perfiles WHERE provincia=? AND usuario=?');
    $query->execute([$u->province ?? 'nacional', $u->username]);

    return ['id' => $u->operator_id, 'usuario' => $u->username, 'nombre' => $u->name, 'clave' => $u->password, 'revision' => $u->revision, 'tiene_imagen' => (bool) $query->fetchColumn()];
}

function credential(array $account): string
{
    return hash('sha256', $account['clave'].':'.$account['revision']);
}

function accountLabel(array $account): string
{
    return $account['nombre'].' ('.$account['usuario'].')';
}

function validOperatorPassword(string $password): bool
{
    return PasswordPolicy::accepts($password);
}

function saveProfile(PDO $db, string $province, array $account, array $input, ?array $upload): array
{
    $name = trim(is_string($input['nombre'] ?? null) ? $input['nombre'] : '');
    $current = is_string($input['clave_actual'] ?? null) ? $input['clave_actual'] : '';
    $new = is_string($input['clave_nueva'] ?? null) ? $input['clave_nueva'] : '';
    $repeat = is_string($input['clave_repetida'] ?? null) ? $input['clave_repetida'] : '';
    $changePassword = $current !== '' || $new !== '' || $repeat !== '';
    $errors = [];
    $image = null;
    $mime = null;
    $newCredential = null;
    if ($name === '' || strlen($name) > 200) {
        $errors[] = 'Ingresa un nombre de hasta 200 bytes.';
    }
    if ($changePassword && (! password_verify($current, $account['clave']) || ! validOperatorPassword($new) || $new !== $repeat)) {
        $errors[] = 'Verifica la clave actual y confirma una nueva de 6 a 72 caracteres, con letra, número y símbolo (. @ _ - ! # $ %), sin tildes ni espacios, y no estar filtrada.';
    }
    if ($upload && ($upload['error'] ?? null) !== UPLOAD_ERR_NO_FILE) {
        $validFile = ($upload['error'] ?? null) === UPLOAD_ERR_OK && is_int($upload['size'] ?? null)
            && $upload['size'] <= 1048576 && is_string($upload['tmp_name'] ?? null) && is_uploaded_file($upload['tmp_name']);
        $details = $validFile ? @getimagesize($upload['tmp_name']) : false;
        if (! $details || ! in_array($details['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)
            || $details[0] < 1 || $details[1] < 1 || max($details[0], $details[1]) > 2048) {
            $errors[] = 'Usa una imagen JPG, PNG o WebP de hasta 1 MB y 2048 × 2048 píxeles.';
        } else {
            $image = file_get_contents($upload['tmp_name']);
            $mime = $details['mime'];
            if ($image === false) {
                $errors[] = 'No se pudo leer la imagen.';
            }
        }
    }
    if ($errors) {
        return [$errors, null];
    }

    $db->beginTransaction();
    try {
        if ($changePassword) {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $query = $db->prepare('UPDATE accounts SET password=?, revision=revision+1, updated_at=NOW() WHERE id=? AND password=? AND revision=? AND active=1');
            $query->execute([$hash, auth()->id(), $account['clave'], $account['revision']]);
            if ($query->rowCount() !== 1) {
                $db->rollBack();

                return [['La cuenta cambió durante la edición. Vuelve a iniciar sesión.'], null];
            }
            $newCredential = credential(['clave' => $hash, 'revision' => $account['revision'] + 1]);
        }
        $db->prepare('UPDATE accounts SET name=?, updated_at=NOW() WHERE id=?')->execute([$name, auth()->id()]);
        $province = auth()->user()->province ?? 'nacional';
        $query = $db->prepare('INSERT INTO perfiles (provincia,usuario,nombre) VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE nombre=VALUES(nombre)');
        $query->execute([$province, $account['usuario'], $name]);
        if ($image !== null || ($input['quitar_imagen'] ?? '') === '1') {
            $query = $db->prepare('UPDATE perfiles SET imagen=?, imagen_tipo=? WHERE provincia=? AND usuario=?');
            $query->bindValue(1, $image, $image === null ? PDO::PARAM_NULL : PDO::PARAM_LOB);
            $query->bindValue(2, $mime);
            $query->bindValue(3, $province);
            $query->bindValue(4, $account['usuario']);
            $query->execute();
        }
        $db->commit();

        return [[], $newCredential];
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }
}
