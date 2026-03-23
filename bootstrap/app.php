<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        api: __DIR__.'/../routes/api.php',   // ← DAPAT MERON ITO
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Request ID must run first so all responses carry it
        $middleware->prepend(\App\Http\Middleware\RequestId::class);

        // Global security middleware
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->append(\App\Http\Middleware\SanitizeInput::class);
        $middleware->append(\App\Http\Middleware\LogSecurityEvents::class);

        // Alias middleware for route-specific use
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'signature' => \App\Http\Middleware\ValidateApiSignature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Stateless JSON API — never redirect to a login route, always return JSON
        $exceptions->shouldRenderJsonWhen(fn ($request, $e) => true);

        // Use our custom JSON format for all exceptions
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            $handler = new \App\Exceptions\Handler(app());
            return $handler->render($request, $e);
        });
    })->create();
