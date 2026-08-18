<?php

namespace App\Exceptions;

class AuthenticationException extends BaseBusinessException
{
    public static function invalidCredentials(): self
    {
        return new self(
            'Invalid credentials provided',
            'INVALID_CREDENTIALS',
            [],
            401
        );
    }

    public static function userNotActive(): self
    {
        return new self(
            'User account is not active',
            'USER_NOT_ACTIVE',
            [],
            401
        );
    }

    public static function tokenExpired(): self
    {
        return new self(
            'Authentication token has expired',
            'TOKEN_EXPIRED',
            [],
            401
        );
    }

    public static function unauthorizedAccess(string $permission): self
    {
        return new self(
            "Unauthorized access. Required permission: {$permission}",
            'UNAUTHORIZED_ACCESS',
            ['permission' => $permission],
            403
        );
    }

    public static function invalidPin(): self
    {
        return new self(
            'Invalid PIN provided',
            'INVALID_PIN',
            [],
            401
        );
    }

    public static function tooManyLoginAttempts(): self
    {
        return new self(
            'Too many login attempts. Please try again later',
            'TOO_MANY_LOGIN_ATTEMPTS',
            [],
            429
        );
    }
}