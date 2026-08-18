<?php

namespace App\Exceptions;

use Exception;

class PaymentException extends Exception
{
    public static function dueToInvalidAmount(float $amount): self
    {
        return new self("Invalid payment amount: {$amount}");
    }

    public static function dueToExceedingInstallmentBalance(float $amount, float $balance): self
    {
        return new self("Payment amount {$amount} exceeds remaining balance {$balance}");
    }

    public static function dueToInactivePaymentMethod(): self
    {
        return new self('Selected payment method is inactive');
    }

    public static function dueToInvalidInstallment(): self
    {
        return new self('Invalid installment for payment');
    }

    public static function dueToAlreadyPaid(): self
    {
        return new self('Installment is already fully paid');
    }
}