<?php

use App\Support\LegacyStop;
use App\Support\LegacyTransport;

function aphHeader(string $header, bool $replace = true, int $code = 0): void
{
    $t = app(LegacyTransport::class);
    [$name,$value] = explode(':', $header, 2);
    $t->headers[$name] = trim($value);
    if ($code) {
        $t->status = $code;
    }
}
function aphStatus(int $code): void
{
    app(LegacyTransport::class)->status = $code;
}
function aphExit(string $message = ''): never
{
    echo $message;
    throw new LegacyStop;
}
