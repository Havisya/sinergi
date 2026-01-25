<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Middleware\HandleCors;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\LogAdminActivity; // ✅ Tambahkan ini

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // ✅ Alias middleware kustom
       $middleware->alias([
    'log.admin' => \App\Http\Middleware\LogAdminActivity::class,
    'role' => \App\Http\Middleware\CheckRole::class,
]);


        // ✅ CORS aktif untuk web & api
        $middleware->web(append: [
            HandleCors::class,
        ]);

        $middleware->api(prepend: [
            HandleCors::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->dontReport([
            \Illuminate\Auth\AuthenticationException::class,
        ]);

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthenticated.',
                ], 401);
            }
        });

        $exceptions->reportable(function (\Throwable $e) {
            \Log::error('💥 Error tidak ter-handle: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        });

        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Terjadi kesalahan server.',
                    'detail' => config('app.debug') ? $e->getMessage() : null,
                ], 500);
            }
        });
    })
    ->create();
