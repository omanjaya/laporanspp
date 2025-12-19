<?php

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
        $middleware->web(append: [
            \App\Http\Middleware\AuthMiddleware::class,
            \App\Http\Middleware\RateLimitMiddleware::class,
        ]);

        // Add CORS middleware for API routes
        $middleware->group('api', [
            \App\Http\Middleware\CorsMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, Request $request) {
            // Custom exception rendering is handled in Handler.php
            return null;
        });
    })
    ->withCommands([
        \App\Console\Commands\GenerateApiKeys::class,
        \App\Console\Commands\SecurityAudit::class,
        \App\Console\Commands\TestBankCsvImportCommand::class,
    ])
    ->create();
