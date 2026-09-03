<?php

use App\Http\Middleware\IsAdmin;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/*
|--------------------------------------------------------------------------
| Ye POORI file hai (bootstrap/app.php) — seedha replace kar dein.
|--------------------------------------------------------------------------
| Naya hissa sirf withExceptions() ke andar hai — is se Laravel ke saare
| built-in errors (401, 403, 404, 422, 429, 500...) bhi hamari standard
| {success, message, data, errors} shape mein aayenge, na ke Laravel ki
| apni default shape mein. Controllers mein kuch badalne ki zaroorat nahi.
*/

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'is_admin' => IsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {

        // ---------- 422: Validation error ----------
        $exceptions->render(function (ValidationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'The given data was invalid.',
                    'data'    => null,
                    'errors'  => collect($e->errors())->flatten()->values()->all(),
                ], 422);
            }
        });

        // ---------- 401: Not logged in / bad token ----------
        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                    'data'    => null,
                    'errors'  => [],
                ], 401);
            }
        });

        // ---------- 403: Forbidden (abort_if, Gate::authorize waghera) ----------
        $exceptions->render(function (AuthorizationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'This action is unauthorized.',
                    'data'    => null,
                    'errors'  => [],
                ], 403);
            }
        });

        // ---------- 404: Model not found (Route::model binding fail) ----------
        $exceptions->render(function (ModelNotFoundException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Resource not found.',
                    'data'    => null,
                    'errors'  => [],
                ], 404);
            }
        });

        // ---------- 404: Route hi maujood nahi ----------
        $exceptions->render(function (NotFoundHttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'The requested endpoint was not found.',
                    'data'    => null,
                    'errors'  => [],
                ], 404);
            }
        });

        // ---------- Baqi sab HTTP errors (403 abort(), 405, 429 throttle, waghera) ----------
        $exceptions->render(function (HttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'Something went wrong.',
                    'data'    => null,
                    'errors'  => [],
                ], $e->getStatusCode());
            }
        });

        // ---------- Aakhri safety net: koi bhi anexpected server error (500) ----------
        $exceptions->render(function (Throwable $e, $request) {
            if (($request->is('api/*') || $request->expectsJson()) && ! config('app.debug')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Server error. Please try again later.',
                    'data'    => null,
                    'errors'  => [],
                ], 500);
            }
            // APP_DEBUG=true (local) ho to Laravel ki normal detailed error dikhti rahegi — debugging ke liye
        });
    })->create();