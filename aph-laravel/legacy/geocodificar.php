<?php

declare(strict_types=1);
use Illuminate\Support\Facades\Cache;

function reverseAddress(PDO $db, float $lat, float $lon): string
{
    $endpoint = getenv('APH_REVERSE_URL') ?: 'https://nominatim.openstreetmap.org/reverse';
    if (! function_exists('curl_init')) {
        return '';
    }
    $query = http_build_query([
        'format' => 'jsonv2',
        'lat' => number_format($lat, 4, '.', ''),
        'lon' => number_format($lon, 4, '.', ''),
        'zoom' => 17,
        'addressdetails' => 1,
        'accept-language' => 'es',
    ], '', '&', PHP_QUERY_RFC3986);
    $key = hash('sha256', $endpoint.'?'.$query);
    $cached = $db->prepare('SELECT direccion FROM geocodificacion WHERE clave=?');
    $cached->execute([$key]);
    $value = $cached->fetchColumn();
    if ($value !== false) {
        return $value;
    }
    $now = microtime(true);
    if (! Cache::add('reverse-geocode-rate', true, 2)) {
        return '';
    }

    $curl = curl_init($endpoint.(str_contains($endpoint, '?') ? '&' : '?').$query);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_USERAGENT => 'CRE-APH-Piloto/1.0',
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ];
    if (defined('CURLOPT_PROTOCOLS_STR')) {
        $options[CURLOPT_PROTOCOLS_STR] = 'HTTPS,HTTP';
    } else {
        $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS | CURLPROTO_HTTP;
    }
    if (defined('CURLSSLOPT_NATIVE_CA') && PHP_OS_FAMILY === 'Windows') {
        $options[CURLOPT_SSL_OPTIONS] = CURLSSLOPT_NATIVE_CA;
    }
    curl_setopt_array($curl, $options);
    $raw = curl_exec($curl);
    $code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    if (! is_string($raw) || ! in_array($code, [200, 404], true)) {
        return '';
    }
    $result = json_decode($raw, true);
    if (! is_array($result)) {
        return '';
    }
    $address = $result['address'] ?? [];
    $parts = [
        $address['road'] ?? $address['pedestrian'] ?? $address['path'] ?? '',
        $address['neighbourhood'] ?? $address['suburb'] ?? '',
        $address['city'] ?? $address['town'] ?? $address['village'] ?? $address['hamlet'] ?? $address['municipality'] ?? $address['county'] ?? '',
        $address['state'] ?? '',
    ];
    $value = implode(', ', array_unique(array_filter($parts)));
    if ($value === '') {
        $value = is_string($result['display_name'] ?? null) ? $result['display_name'] : '';
    }
    $db->prepare('INSERT IGNORE INTO geocodificacion VALUES (?,?)')->execute([$key, $value]);

    return $value;
}
