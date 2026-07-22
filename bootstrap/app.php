<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;

return Application::configure(
    basePath: dirname(__DIR__)
)
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' =>
                \App\Http\Middleware\AdminMiddleware::class,

            'user' =>
                \App\Http\Middleware\UserMiddleware::class,

            'role' =>
                \App\Http\Middleware\RoleMiddleware::class,

            'account.active' =>
                \App\Http\Middleware\EnsureAccountIsActive::class,
        ]);

        $middleware->redirectGuestsTo(
            fn (Request $request) =>
                $request->is('api/*')
                    ? null
                    : '/login'
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) =>
                $request->is('api/*')
        );

        $exceptions->render(
            function (
                AuthenticationException $exception,
                Request $request
            ) {
                if ($request->is('api/*')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthenticated.',
                    ], 401);
                }

                return null;
            }
        );
    })
    ->create();