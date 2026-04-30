<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\ForceHttps::class);
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->alias([
            'havenhr.auth' => \App\Http\Middleware\JwtAuth::class,
            'tenant.resolve' => \App\Http\Middleware\TenantResolver::class,
            'rbac' => \App\Http\Middleware\RbacMiddleware::class,
            'candidate.auth' => \App\Http\Middleware\CandidateAuth::class,
        ]);

        $middleware->encryptCookies(except: [
            'access_token',
            'refresh_token',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
