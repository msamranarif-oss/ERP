<?php

namespace App\Http\Controllers;

use App\Traits\SendsApiResponses;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class ApiController extends BaseController
{
    use AuthorizesRequests, ValidatesRequests, SendsApiResponses;

    /**
     * Return a successful response
     * @deprecated Use successResponse instead
     */
    protected function success($data = null, string $message = '', int $code = 200)
    {
        return $this->successResponse($data, $message, $code);
    }

    /**
     * Return an error response
     * @deprecated Use errorResponse instead
     */
    protected function error(string $message, int $code = 400, array $errors = [], string $errorCode = null)
    {
        return $this->errorResponse($message, $code, $errors, $errorCode);
    }
}