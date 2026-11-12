<?php

use App\Http\Middleware\SecureResponse;
use App\Http\Middleware\SessionLimits;
use App\Http\Middleware\Superadmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [SecureResponse::class]);
        $middleware->alias(['limits' => SessionLimits::class, 'superadmin' => Superadmin::class]);
        $middleware->trimStrings(except: ['password', 'clave_actual', 'clave_nueva', 'clave_repetida', 'clave_operador']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
