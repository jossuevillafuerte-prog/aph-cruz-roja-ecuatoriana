<?php

namespace App\Security;

use Illuminate\Support\Facades\Http;

class PasswordPolicy
{
    public const DESCRIPTION = 'De 8 a 72 caracteres: al menos una mayúscula, una minúscula, un número y un símbolo (. @ _ - ! # $ %). Sin tildes, ñ ni espacios. No uses claves comunes o filtradas.';

    public static function accepts(string $password): bool
    {
        return self::error($password) === null;
    }

    public static function error(string $password): ?string
    {
        if (! preg_match('/\A(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*[.@_!#$%\-])[A-Za-z0-9.@_!#$%\-]{8,72}\z/', $password)) {
            return self::DESCRIPTION;
        }
        if (preg_match('/(password|contrasena|cruzroja|admin|qwerty|123456)/i', $password)) {
            return 'Esta contraseña es común o aparece en filtraciones. Elige otra.';
        }
        try {
            $hash = strtoupper(sha1($password));
            $response = Http::withHeaders(['Add-Padding' => 'true'])->timeout(5)->get('https://api.pwnedpasswords.com/range/'.substr($hash, 0, 5));
            if (! $response->successful() || ! str_contains($response->body(), ':')) {
                return 'No se pudo verificar la seguridad de la contraseña. Inténtalo nuevamente.';
            }
            foreach (explode("\n", $response->body()) as $line) {
                [$suffix,$count] = array_pad(explode(':', trim($line), 2), 2, 0);
                if (hash_equals(substr($hash, 5), $suffix) && (int) $count > 0) {
                    return 'Esta contraseña aparece en filtraciones. Elige otra.';
                }
            }

            return null;
        } catch (\Throwable) {
            return 'No se pudo verificar la seguridad de la contraseña. Inténtalo nuevamente.';
        }
    }
}
