<?php

namespace App\Security;

use Illuminate\Support\Facades\Http;

class PasswordPolicy
{
    public const DESCRIPTION = 'De 6 a 72 caracteres: al menos una letra, un número y un símbolo (. @ _ - ! # $ %). Sin tildes, ñ ni espacios. No uses claves comunes o filtradas.';

    public static function accepts(string $password): bool
    {
        if (! preg_match('/\A(?=.*[A-Za-z])(?=.*[0-9])(?=.*[.@_!#$%\-])[A-Za-z0-9.@_!#$%\-]{6,72}\z/', $password)) {
            return false;
        }
        if (preg_match('/(password|contrasena|cruzroja|admin|qwerty|123456)/i', $password)) {
            return false;
        }
        try {
            $hash = strtoupper(sha1($password));
            $response = Http::withHeaders(['Add-Padding' => 'true'])->timeout(5)->get('https://api.pwnedpasswords.com/range/'.substr($hash, 0, 5));
            if (! $response->successful() || ! str_contains($response->body(), ':')) {
                return false;
            }
            foreach (explode("\n", $response->body()) as $line) {
                [$suffix,$count] = array_pad(explode(':', trim($line), 2), 2, 0);
                if (hash_equals(substr($hash, 5), $suffix) && (int) $count > 0) {
                    return false;
                }
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
