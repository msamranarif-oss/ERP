<?php

namespace App\Exceptions;

use Exception;

class AuthorizationException extends Exception
{
    public static function dueToTenantMismatch(): self
    {
        return new self('Access denied: Tenant mismatch');
    }

    public static function dueToInsufficientPermissions(): self
    {
        return new self('Access denied: Insufficient permissions');
    }

    public static function dueToModelNotFound(): self
    {
        return new self('Requested resource not found or access denied');
    }

    public static function dueToInactiveUser(): self
    {
        return new self('User account is inactive');
    }

    public static function dueToInactiveTenant(): self
    {
        return new self('Organization account is inactive');
    }
}