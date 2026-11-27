<?php

function h(string|int|float $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function icon(string $name): string
{
    $brandIcon = [
        'add' => 'Health.png', 'list' => 'Documents.png', 'clock' => 'Rapid-timely.png',
        'download' => 'Download.png', 'ambulance' => 'Emergency-Medical-Services.png',
        'pin' => 'Location.png', 'team' => 'Participation-team.png', 'profile' => 'Volunteers.png',
        'exit' => 'Exit.png',
    ][$name] ?? null;
    if ($brandIcon) {
        return '<img class="brand-icon" src="marca/'.$brandIcon.'" width="22" height="22" alt="">';
    }
    $path = match ($name) {
        'add' => 'M12 5v14M5 12h14',
        'list' => 'M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01',
        'clock' => 'M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        'check' => 'm5 12 4 4L19 6',
        'download' => 'M12 3v12m-4-4 4 4 4-4M5 15v5h14v-5',
        'ambulance' => 'M3 17V6h12v11M15 9h3l3 4v4h-2M3 17h2m4 0h6M7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm10 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM9 8v5m-2.5-2.5h5',
        'edit' => 'm14 6 4 4M4 20l4-1 12-12-4-4L4 15z',
        'delete' => 'M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 11v5m4-5v5',
        'print' => 'M6 8V3h12v5M6 17H3V8h18v9h-3M6 13h12v8H6zM17 11h.01',
        'pin' => 'M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0ZM12 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z',
        'menu' => 'M4 6h16M4 12h16M4 18h16',
        'close' => 'm6 6 12 12M18 6 6 18',
        'back' => 'm11 5-7 7 7 7M4 12h16',
        'dashboard' => 'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z',
    };

    return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="'.$path.'"/></svg>';
}

function accountAvatar(array $account): string
{
    return '<span class="avatar" aria-hidden="true">'.($account['tiene_imagen']
        ? '<img src="?vista=avatar" width="40" height="40" alt="">' : icon('profile')).'</span>';
}

function fieldId(string $key): string
{
    return $key === 'motivo' ? 'motivo' : 'f-'.$key;
}

/**
 * @param  array<int|string, string>  $errors
 */
function fieldErrorAttributes(array $errors, string $key, string $help = ''): string
{
    $described = trim($help.(isset($errors[$key]) ? ' '.fieldId($key).'-error' : ''));

    return (isset($errors[$key]) ? ' aria-invalid="true"' : '').($described !== '' ? ' aria-describedby="'.h($described).'"' : '');
}

/**
 * @param  array<int|string, string>  $errors
 */
function fieldErrorMessage(array $errors, string $key): string
{
    return isset($errors[$key]) ? '<p class="field-error" id="'.h(fieldId($key)).'-error">'.h($errors[$key]).'</p>' : '';
}

/**
 * Resumen enfocable: cada error de campo enlaza a su control.
 *
 * @param  array<int|string, string>  $errors
 * @param  list<string>  $linkable
 */
function errorSummary(array $errors, array $linkable): string
{
    if (! $errors) {
        return '';
    }
    $items = '';
    foreach ($errors as $key => $message) {
        $items .= is_string($key) && in_array($key, $linkable, true)
            ? '<li><a href="#'.h(fieldId($key)).'">'.h($message).'</a></li>'
            : '<li>'.h($message).'</li>';
    }

    return '<div class="errors" role="alert" id="form-errors" tabindex="-1"><h2>Revisa '.(count($errors) === 1 ? 'este dato' : 'estos '.count($errors).' datos').'</h2><ul>'.$items.'</ul></div>';
}

/**
 * Asterisco de los campos obligatorios para cerrar la atención.
 */
function requiredMark(string $key): string
{
    return in_array($key, REQUIRED_TO_CLOSE, true) ? '<span class="required-mark" aria-hidden="true">*</span>' : '';
}
