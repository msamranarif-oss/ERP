<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class AuthRateLimiter
{
    /**
     * Handle authentication requests with stricter rate limiting
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $this->resolveRequestSignature($request);

        // Stricter limits for authentication endpoints
        $maxAttempts = 5; // Only 5 attempts
        $decayMinutes = 15; // 15 minutes window
        $decaySeconds = $decayMinutes * 60;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);

            return response()->json([
                'success' => false,
                'message' => 'Too many authentication attempts. Please try again in '.$this->formatRetryAfter($retryAfter).'.',
                'error_code' => 'AUTH_RATE_LIMIT_EXCEEDED',
                'retry_after' => $retryAfter,
            ], 429, [
                'Retry-After' => $retryAfter,
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => 0,
                'X-RateLimit-Reset' => now()->addSeconds($retryAfter)->timestamp,
            ]);
        }

        $response = $next($request);

        // Only count failed authentication attempts
        if ($response->status() === 401 || $response->status() === 422) {
            RateLimiter::hit($key, $decaySeconds);
        } elseif ($response->isSuccessful() && $this->shouldClearAttempts($request)) {
            RateLimiter::clear($key);
        }

        return $response;
    }

    /**
     * Resolve request signature for authentication rate limiting
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $identifier = strtolower(trim((string) ($request->input('email') ?? $request->input('username') ?? 'anonymous')));

        return sha1(
            'auth'.'|'.
            $request->path().'|'.
            $request->ip().'|'.
            $identifier.'|'.
            $request->header('User-Agent')
        );
    }

    protected function shouldClearAttempts(Request $request): bool
    {
        return in_array($request->path(), [
            'api/v1/auth/login',
            'api/v1/auth/pin-login',
        ], true);
    }

    protected function formatRetryAfter(int $seconds): string
    {
        $minutes = intdiv($seconds, 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes > 0 && $remainingSeconds > 0) {
            return sprintf(
                '%d minute%s %d second%s',
                $minutes,
                $minutes === 1 ? '' : 's',
                $remainingSeconds,
                $remainingSeconds === 1 ? '' : 's'
            );
        }

        if ($minutes > 0) {
            return sprintf('%d minute%s', $minutes, $minutes === 1 ? '' : 's');
        }

        return sprintf('%d second%s', $remainingSeconds, $remainingSeconds === 1 ? '' : 's');
    }
}
