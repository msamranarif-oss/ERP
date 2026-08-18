<?php

namespace App\Exceptions;

class InventoryException extends BaseBusinessException
{
    public static function insufficientStock(string $productName, float $requested, float $available): self
    {
        return new self(
            "Insufficient stock for {$productName}. Requested: {$requested}, Available: {$available}",
            'INSUFFICIENT_STOCK',
            ['product_name' => $productName, 'requested' => $requested, 'available' => $available]
        );
    }

    public static function productNotFound(int $productId): self
    {
        return new self(
            "Product with ID {$productId} not found",
            'PRODUCT_NOT_FOUND',
            ['product_id' => $productId]
        );
    }

    public static function duplicateSKU(string $sku): self
    {
        return new self(
            "A product with SKU '{$sku}' already exists",
            'SKU_TAKEN',
            ['sku' => $sku]
        );
    }

    public static function duplicateBarcode(string $barcode): self
    {
        return new self(
            "A product with barcode '{$barcode}' already exists",
            'BARCODE_TAKEN',
            ['barcode' => $barcode]
        );
    }

    public static function invalidProductType(string $type): self
    {
        return new self(
            "Invalid product type: {$type}. Must be one of: simple, variant, bundle, service",
            'INVALID_PRODUCT_TYPE',
            ['type' => $type]
        );
    }

    public static function invalidValuationMethod(string $method): self
    {
        return new self(
            "Invalid valuation method: {$method}. Must be one of: avg_cost, fifo, lifo",
            'INVALID_VALUATION_METHOD',
            ['method' => $method]
        );
    }

    public static function priceTooHigh(string $field, float $price): self
    {
        return new self(
            "{$field} exceeds maximum allowed value (999,999,999)",
            'PRICE_TOO_HIGH',
            ['field' => $field, 'price' => $price]
        );
    }

    public static function dimensionOutOfRange(string $field, float $value): self
    {
        return new self(
            "{$field} value {$value} is out of acceptable range",
            'DIMENSION_OUT_OF_RANGE',
            ['field' => $field, 'value' => $value]
        );
    }

    public static function invalidTaxRate(float $rate): self
    {
        return new self(
            "Invalid tax rate: {$rate}%. Must be between 0 and 100",
            'INVALID_TAX_RATE',
            ['rate' => $rate]
        );
    }

    public static function categoryNotFound(int $categoryId): self
    {
        return new self(
            "Category with ID {$categoryId} not found",
            'CATEGORY_NOT_FOUND',
            ['category_id' => $categoryId]
        );
    }

    public static function brandNotFound(int $brandId): self
    {
        return new self(
            "Brand with ID {$brandId} not found",
            'BRAND_NOT_FOUND',
            ['brand_id' => $brandId]
        );
    }

    public static function unitNotFound(int $unitId): self
    {
        return new self(
            "Unit with ID {$unitId} not found",
            'UNIT_NOT_FOUND',
            ['unit_id' => $unitId]
        );
    }

    public static function batchExpired(string $batchNumber): self
    {
        return new self(
            "Batch {$batchNumber} has expired",
            'BATCH_EXPIRED',
            ['batch_number' => $batchNumber]
        );
    }

    public static function invalidQuantity(float $quantity): self
    {
        return new self(
            "Invalid quantity: {$quantity}. Quantity must be positive",
            'INVALID_QUANTITY',
            ['quantity' => $quantity]
        );
    }

    public static function negativeStockNotAllowed(string $productName): self
    {
        return new self(
            "Negative stock not allowed for {$productName}",
            'NEGATIVE_STOCK_NOT_ALLOWED',
            ['product_name' => $productName]
        );
    }
}