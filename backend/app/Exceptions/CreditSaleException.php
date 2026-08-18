<?php

namespace App\Exceptions;

use Exception;

class CreditSaleException extends Exception
{
    public static function dueToInsufficientInventory(): self
    {
        return new self('Insufficient inventory for credit sale creation');
    }

    public static function dueToInvalidCustomer(): self
    {
        return new self('Invalid or inactive customer for credit sale');
    }

    public static function dueToCreditLimitExceeded(): self
    {
        return new self('Credit limit exceeded for this customer');
    }

    public static function dueToInvalidInstallmentConfiguration(): self
    {
        return new self('Invalid installment configuration');
    }

    public static function dueToAlreadyCompleted(): self
    {
        return new self('Cannot modify completed credit sale');
    }
}