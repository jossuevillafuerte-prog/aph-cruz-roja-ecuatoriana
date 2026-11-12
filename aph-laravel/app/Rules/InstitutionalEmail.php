<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class InstitutionalEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $fail('Ingresa un correo institucional válido.');

            return;
        }
        $domain = strtolower(substr(strrchr($value, '@'), 1));
        if (! in_array($domain, [config('institution.national_domain'), ...array_values(config('institution.domains'))], true)) {
            $fail('El dominio del correo no está autorizado por la institución.');
        }
    }
}
