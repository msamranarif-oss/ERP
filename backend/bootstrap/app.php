<?php

use App\Http\Middleware\SetTenantMiddleware;
use App\Http\Middleware\ApiRateLimiter;
use App\Http\Middleware\AuthRateLimiter;
use App\Http\Middleware\CheckTokenBlacklist;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule) {
        $schedule->command('batches:check-expiry')->daily();
        $schedule->command('installments:apply-penalties')->dailyAt('00:05');
    })
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => SetTenantMiddleware::class,
            'api.rate' => ApiRateLimiter::class,
            'auth.rate' => AuthRateLimiter::class,
            'token.blacklist' => CheckTokenBlacklist::class,
        ]);

        // Apply rate limiting to API routes
        $middleware->api(
            prepend: [
                'api.rate:60,1', // 60 requests per minute default
            ]
        );

        // Apply strict rate limiting to auth routes
        $middleware->appendToGroup('api', [
            'auth.rate',
        ], function ($group) {
            return $group->where('auth/*');
        });

        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function ($request, Throwable $e) {
            return $request->expectsJson();
        });

        $exceptions->render(function (Throwable $e, $request) {
            if ($request->expectsJson()) {
                $statusCode = 500;
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                    $statusCode = $e->getStatusCode();
                } elseif ($e instanceof \Illuminate\Auth\AuthenticationException) {
                    $statusCode = 401;
                } elseif ($e instanceof \Illuminate\Validation\ValidationException) {
                    $statusCode = 422;
                } elseif ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException || $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                    $statusCode = 404;
                } elseif ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                    $statusCode = 403;
                }
                
                // In production, mask internal server errors
                if (app()->environment('production') && $statusCode === 500) {
                    return response()->json([
                        'success' => false,
                        'message' => 'An internal error occurred. Please try again later.',
                    ], 500);
                }
                
                $message = $e->getMessage();
                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    return response()->json([
                        'success' => false,
                        'message' => 'The given data was invalid.',
                        'errors' => $e->errors(),
                    ], 422);
                }

                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], $statusCode);
            }
        });
    })->create();

