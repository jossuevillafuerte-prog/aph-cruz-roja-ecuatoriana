<?php

namespace App\Support;

/**
 * URLs de archivos de public/ con versión según su fecha de modificación.
 *
 * El hosting guarda JS y CSS en caché durante días; al cambiar el archivo
 * cambia la URL, y el navegador descarga la versión nueva tras cada despliegue.
 */
final class Asset
{
    public static function url(string $path): string
    {
        $path = '/'.ltrim($path, '/');
        $file = public_path($path);

        return is_file($file) ? $path.'?v='.filemtime($file) : $path;
    }
}
