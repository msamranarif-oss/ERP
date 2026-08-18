<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Facades\Auth;

class ApiResponse
{
    /**
     * Return a successful response
     */
    public static function success($data = null, string $message = '', int $code = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->toISOString(),
            'request_id' => request()?->header('X-Request-ID')
        ];

        // Add pagination metadata if data is paginated
        if ($data instanceof AbstractPaginator) {
            $response['meta'] = [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem()
            ];
            $response['links'] = [
                'first' => $data->url(1),
                'last' => $data->url($data->lastPage()),
                'prev' => $data->previousPageUrl(),
                'next' => $data->nextPageUrl()
            ];
        }

        return response()->json($response, $code);
    }

    /**
     * Return an error response
     */
    public static function error(string $message, int $code = 400, array $errors = [], string $errorCode = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        if ($errorCode) {
            $response['error_code'] = $errorCode;
        }

        return response()->json($response, $code);
    }

    /**
     * Return a validation error response
     */
    public static function validationError(array $errors, string $message = 'Validation failed'): JsonResponse
    {
        return self::error($message, 422, $errors, 'VALIDATION_ERROR');
    }

    /**
     * Return an authorization error response
     */
    public static function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return self::error($message, 401, [], 'UNAUTHORIZED');
    }

    /**
     * Return a forbidden error response
     */
    public static function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return self::error($message, 403, [], 'FORBIDDEN');
    }

    /**
     * Return a not found error response
     */
    public static function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return self::error($message, 404, [], 'NOT_FOUND');
    }

    /**
     * Return a server error response
     */
    public static function serverError(string $message = 'Internal server error'): JsonResponse
    {
        return self::error($message, 500, [], 'SERVER_ERROR');
    }

    /**
     * Return a resource response
     */
    public static function resource(JsonResource $resource, string $message = '', int $code = 200): JsonResponse
    {
        return self::success($resource, $message, $code);
    }

    /**
     * Return a collection response
     */
    public static function collection(AnonymousResourceCollection $collection, string $message = '', int $code = 200): JsonResponse
    {
        return self::success($collection, $message, $code);
    }

    /**
     * Return a created response
     */
    public static function created($data = null, string $message = 'Resource created successfully'): JsonResponse
    {
        return self::success($data, $message, 201);
    }

    /**
     * Return an updated response
     */
    public static function updated($data = null, string $message = 'Resource updated successfully'): JsonResponse
    {
        return self::success($data, $message, 200);
    }

    /**
     * Return a deleted response
     */
    public static function deleted(string $message = 'Resource deleted successfully'): JsonResponse
    {
        return self::success(null, $message, 200);
    }

    /**
     * Return a no content response
     */
    public static function noContent(string $message = 'Request processed successfully'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'timestamp' => now()->toISOString()
        ], 204);
    }
}