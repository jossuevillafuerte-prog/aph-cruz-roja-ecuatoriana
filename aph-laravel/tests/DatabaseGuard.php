<?php

namespace Tests;

use RuntimeException;

final class DatabaseGuard
{
    public const TEST_DATABASE = 'aph_test';

    /**
     * Aborta antes de que RefreshDatabase pueda vaciar una base que no sea la de pruebas.
     *
     * @param  array{database?: string|null, url?: string|null, read?: array<mixed>, write?: array<mixed>}  $connection
     */
    public static function check(bool $configurationIsCached, array $connection): void
    {
        if ($configurationIsCached) {
            throw new RuntimeException('Pruebas detenidas: la configuración está en caché y podría apuntar a otra base. Ejecuta "php artisan config:clear".');
        }

        if (! empty($connection['url']) || ! empty($connection['read']) || ! empty($connection['write'])) {
            throw new RuntimeException('Pruebas detenidas: la conexión define url, read o write y podría apuntar a otra base.');
        }

        if (($connection['database'] ?? null) !== self::TEST_DATABASE) {
            throw new RuntimeException('Pruebas detenidas: solo pueden usar la base "'.self::TEST_DATABASE.'".');
        }
    }
}
