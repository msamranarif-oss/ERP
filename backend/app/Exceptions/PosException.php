<?php

namespace App\Exceptions;

class PosException extends BaseBusinessException
{
    public static function registerSessionNotOpen(): self
    {
        return new self(
            'Register session is not open',
            'REGISTER_SESSION_NOT_OPEN'
        );
    }

    public static function noWarehouseAssigned(): self
    {
        return new self(
            'No warehouse assigned to this branch or found in system',
            'NO_WAREHOUSE_ASSIGNED'
        );
    }

    public static function invalidPaymentAmount(float $amount, float $total): self
    {
        return new self(
            "Invalid payment amount: {$amount}. Must be greater than 0 and not exceed total amount: {$total}",
            'INVALID_PAYMENT_AMOUNT',
            ['amount' => $amount, 'total' => $total]
        );
    }

    public static function customerCreditLimitExceeded(float $amount, float $limit): self
    {
        return new self(
            "Payment amount {$amount} exceeds customer credit limit {$limit}",
            'CUSTOMER_CREDIT_LIMIT_EXCEEDED',
            ['amount' => $amount, 'limit' => $limit]
        );
    }

    public static function saleAlreadyCompleted(): self
    {
        return new self(
            'Cannot modify completed sale',
            'SALE_ALREADY_COMPLETED'
        );
    }

    public static function invalidBarcode(string $barcode): self
    {
        return new self(
            "Invalid barcode: {$barcode}",
            'INVALID_BARCODE',
            ['barcode' => $barcode]
        );
    }
}