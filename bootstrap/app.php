<?php

use App\Http\Middleware\EnsureUserIsManager;
use App\Http\Middleware\EnsureUserIsProfessional;
use App\Http\Middleware\InstalledVersion;
use App\Support\ErrorReporting;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [InstalledVersion::class]);
        $middleware->alias([
            'manager' => EnsureUserIsManager::class,
            'professional' => EnsureUserIsProfessional::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (Throwable $throwable): void {
            app(ErrorReporting::class)->log($throwable);
        });
    })->create();
