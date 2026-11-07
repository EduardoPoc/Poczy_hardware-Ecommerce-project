<?php

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->booted(function (): void {
        foreach (['id', 'product', 'category', 'brand'] as $parameter) {
            Route::bind($parameter, function (string $value): int {
                $identifier = ltrim($value, '0');
                $maximum = (string) PHP_INT_MAX;
                if (strlen($identifier) > strlen($maximum) ||
                    (strlen($identifier) === strlen($maximum) && strcmp($identifier, $maximum) > 0)) {
                    abort(404);
                }

                return (int) $identifier;
            });
        }

        Gate::define('manage-shop', fn (User $user): bool => $user->role === 'ADMIN');

        RateLimiter::for('login', function (Request $request): array {
            $email = $request->input('email');
            $email = is_string($email) ? mb_strtolower(trim($email)) : '';

            return [
                Limit::perMinute(20)->by($request->ip()),
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
            ];
        });
        RateLimiter::for('registration', fn (Request $request): Limit => Limit::perHour(5)->by($request->ip()));
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: array_filter(array_map('trim', explode(',', env('TRUSTED_PROXIES', '127.0.0.1,::1')))));
        $middleware->redirectGuestsTo(fn (Request $request): ?string => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(fn (UniqueConstraintViolationException $exception) => response()->json([
            'message' => 'A record with the provided unique values already exists.',
        ], 409));
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => true,
        );
    })->create();
