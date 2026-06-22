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
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/workbooks');
        $middleware->alias([
            'audit.context' => \App\Http\Middleware\CaptureAuditContext::class,
            'installed' => \App\Http\Middleware\EnsureInstalled::class,
            'not.installed' => \App\Http\Middleware\EnsureNotInstalled::class,
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
        ]);
        $middleware->web(append: [
            \App\Http\Middleware\EnsureInstalled::class,
        ]);
        $middleware->api(append: [
            \App\Http\Middleware\EnsureInstalled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
