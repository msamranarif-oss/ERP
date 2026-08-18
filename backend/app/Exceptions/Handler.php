<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;
use App\Exceptions\BaseBusinessException;
use App\Exceptions\InventoryException;
use App\Exceptions\PosException;
use App\Exceptions\AuthenticationException;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $exception): JsonResponse
    {
        // Handle our custom business exceptions
        if ($exception instanceof BaseBusinessException) {
            return $this->handleBusinessException($exception);
        }

        // Handle specific custom exceptions
        if ($exception instanceof CreditSaleException) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'error_code' => 'CREDIT_SALE_ERROR'
            ], 422);
        }

        if ($exception instanceof PaymentException) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'error_code' => 'PAYMENT_ERROR'
            ], 422);
        }

        // Handle Laravel's validation exceptions
        if ($exception instanceof ValidationException) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $exception->errors(),
                'error_code' => 'VALIDATION_ERROR'
            ], 422);
        }

        // Handle model not found
        if ($exception instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found',
                'error_code' => 'MODEL_NOT_FOUND'
            ], 404);
        }

        // Handle authorization exceptions
        if ($exception instanceof \Illuminate\Auth\AuthenticationException) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
                'error_code' => 'UNAUTHENTICATED'
            ], 401);
        }

        if ($exception instanceof \Illuminate\Auth\Access\AuthorizationException) {
            return response()->json([
                'success' => false,
                'message' => 'This action is unauthorized',
                'error_code' => 'UNAUTHORIZED'
            ], 403);
        }

        // Handle query exceptions (database errors)
        if ($exception instanceof \Illuminate\Database\QueryException) {
            Log::error('Database error', [
                'message' => $exception->getMessage(),
                'sql' => $exception->getSql(),
                'bindings' => $exception->getBindings()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Database error occurred',
                'error_code' => 'DATABASE_ERROR'
            ], 500);
        }

        // Handle general exceptions in API requests
        if ($request->expectsJson()) {
            return $this->handleGeneralException($exception);
        }

        return parent::render($request, $exception);
    }

    /**
     * Handle business exceptions with proper structure
     */
    protected function handleBusinessException(BaseBusinessException $exception): JsonResponse
    {
        $responseData = [
            'success' => false,
            'message' => $exception->getMessage(),
            'error_code' => $exception->getErrorCode(),
        ];

        // Add context data if available
        if (!empty($exception->getContext())) {
            $responseData['context'] = $exception->getContext();
        }

        // Log the business exception for monitoring
        Log::warning('Business Exception', [
            'error_code' => $exception->getErrorCode(),
            'message' => $exception->getMessage(),
            'context' => $exception->getContext(),
            'user_id' => 'unknown',
            'tenant_id' => 'unknown'
        ]);

        return response()->json($responseData, $exception->getCode() ?: 422);
    }

    /**
     * Handle general exceptions with proper error response
     */
    protected function handleGeneralException(Throwable $exception): JsonResponse
    {
        // Log the full exception details
        Log::error('API Exception', [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'user_id' => 'unknown',
            'tenant_id' => 'unknown',
            'request_url' => request()->fullUrl(),
            'request_method' => request()->method(),
        ]);

        // Don't expose internal error details in production
        $message = app()->environment('production') 
            ? 'An error occurred while processing your request' 
            : $exception->getMessage();

        return response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => 'INTERNAL_ERROR',
            'timestamp' => now()->toISOString()
        ], 500);
    }
}