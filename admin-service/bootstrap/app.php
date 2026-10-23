<?php

use App\Http\Middleware\EnsureSessionTokenIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\LogRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        health: '/up',
        // "/api" belongs to the Nest api-gateway once Nginx routes both services.
        apiPrefix: 'admin/api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(LogRequests::class);
        $middleware->web(append: EnsureSessionTokenIsActive::class);
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('admin/api/*') || $request->expectsJson(),
        );
    })->create();
