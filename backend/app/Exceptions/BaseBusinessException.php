<?php

namespace App\Exceptions;

use Exception;

class BaseBusinessException extends Exception
{
    protected string $errorCode;
    protected array $context = [];

    public function __construct(
        string $message = "", 
        string $errorCode = "BUSINESS_ERROR", 
        array $context = [],
        int $code = 422,
        ?Exception $previous = null
    ) {
        $this->errorCode = $errorCode;
        $this->context = $context;
        parent::__construct($message, $code, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
            'context' => $this->context,
            'timestamp' => now()->toISOString()
        ];
    }
}