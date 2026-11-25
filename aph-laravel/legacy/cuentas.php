<?php

declare(strict_types=1);
use App\Security\PasswordPolicy;
use App\Security\Security;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
    if ($name === '' || strlen($name) > 200) {
        $errors[] = 'Ingresa un nombre de hasta 200 bytes.';
    }
    if ($name !== $account['nombre'] && strpbrk($name, '()') !== false) {
        $errors[] = 'El nombre no puede incluir paréntesis: se reservan para identificar al usuario.';
    }
    if ($changePassword) {
        if (! password_verify($current, $account['clave'])) {
            $errors[] = 'La contraseña actual no es correcta.';
        } elseif ($new !== $repeat) {
            $errors[] = 'La confirmación de contraseña no coincide.';
        } elseif ($error = PasswordPolicy::error($new)) {
            $errors[] = $error;
        }
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

    try {
        return DB::transaction(function () use ($db, $account, $input, $name, $new, $changePassword, $image, $mime): array {
            $newCredential = null;
            if ($changePassword) {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $query = $db->prepare('UPDATE accounts SET password=?, revision=revision+1, updated_at=NOW() WHERE id=? AND password=? AND revision=? AND active=1');
                $query->execute([$hash, auth()->id(), $account['clave'], $account['revision']]);
                if ($query->rowCount() !== 1) {
                    throw new DomainException('La cuenta cambió durante la edición. Vuelve a iniciar sesión.');
                }
                $newCredential = credential(['clave' => $hash, 'revision' => $account['revision'] + 1]);
            }
            $db->prepare('UPDATE accounts SET name=?, updated_at=NOW() WHERE id=?')->execute([$name, auth()->id()]);
            if ($name !== $account['nombre']) {
                $db->prepare('UPDATE accounts SET first_name=NULL, last_name=NULL WHERE id=?')->execute([auth()->id()]);
                Security::event('profile_name_changed', null, 'account:'.auth()->id(), mb_substr($account['nombre'], 0, 145).' → '.mb_substr($name, 0, 145));
            }
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
            Security::event('application_write', null, 'perfil:'.auth()->id());

            return [[], $newCredential];
        });
    } catch (DomainException $error) {
        return [[$error->getMessage()], null];
    }
}
