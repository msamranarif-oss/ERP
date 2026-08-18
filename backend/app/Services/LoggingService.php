<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class LoggingService
{
    /**
     * Log an info message with context
     */
    public static function info(string $message, array $context = []): void
    {
        Log::info($message, self::addContext($context));
    }

    /**
     * Log a warning message with context
     */
    public static function warning(string $message, array $context = []): void
    {
        Log::warning($message, self::addContext($context));
    }

    /**
     * Log an error message with context
     */
    public static function error(string $message, array $context = []): void
    {
        Log::error($message, self::addContext($context));
    }

    /**
     * Log a debug message with context
     */
    public static function debug(string $message, array $context = []): void
    {
        Log::debug($message, self::addContext($context));
    }

    /**
     * Log an emergency message with context
     */
    public static function emergency(string $message, array $context = []): void
    {
        Log::emergency($message, self::addContext($context));
    }

    /**
     * Log a critical message with context
     */
    public static function critical(string $message, array $context = []): void
    {
        Log::critical($message, self::addContext($context));
    }

    /**
     * Log an alert message with context
     */
    public static function alert(string $message, array $context = []): void
    {
        Log::alert($message, self::addContext($context));
    }

    /**
     * Log a notice message with context
     */
    public static function notice(string $message, array $context = []): void
    {
        Log::notice($message, self::addContext($context));
    }

    /**
     * Add common context to log messages
     */
    private static function addContext(array $context = []): array
    {
        $baseContext = [
            'timestamp' => now()->toISOString(),
            'user_id' => Auth::check() ? Auth::id() : null,
            'tenant_id' => Auth::check() ? Auth::user()->tenant_id : null,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'request_id' => request()?->header('X-Request-ID'),
        ];

        return array_merge($baseContext, $context);
    }

    /**
     * Log user activity for audit purposes
     */
    public static function audit(string $action, string $model, $modelId = null, array $changes = []): void
    {
        $context = [
            'action' => $action,
            'model' => $model,
            'model_id' => $modelId,
            'changes' => $changes,
        ];

        self::info("User performed action: {$action} on {$model}", $context);
    }

    /**
     * Log security-related events
     */
    public static function security(string $event, array $details = []): void
    {
        $context = array_merge([
            'event' => $event,
            'severity' => 'medium',
        ], $details);

        self::warning("Security event: {$event}", $context);
    }

    /**
     * Log API request/response information
     */
    public static function api(string $endpoint, string $method, int $statusCode, array $details = []): void
    {
        $context = array_merge([
            'endpoint' => $endpoint,
            'method' => $method,
            'status_code' => $statusCode,
            'response_time_ms' => microtime(true) - (defined('LARAVEL_START') ? LARAVEL_START : microtime(true)),
        ], $details);

        if ($statusCode >= 500) {
            self::error("API Error: {$method} {$endpoint} returned {$statusCode}", $context);
        } elseif ($statusCode >= 400) {
            self::warning("API Warning: {$method} {$endpoint} returned {$statusCode}", $context);
        } else {
            self::info("API Success: {$method} {$endpoint} returned {$statusCode}", $context);
        }
    }
}