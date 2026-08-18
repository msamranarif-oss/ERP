<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class BaseFormRequest extends FormRequest
{
    /**
     * Sanitize input data to prevent XSS and injection attacks
     */
    protected function prepareForValidation(): void
    {
        $this->sanitizeInputs();
    }

    /**
     * Sanitize all input fields
     */
    protected function sanitizeInputs(): void
    {
        $sanitized = [];

        foreach ($this->all() as $key => $value) {
            $sanitized[$key] = $this->sanitizeValue($value, $key);
        }

        $this->replace($sanitized);
    }

    /**
     * Sanitize individual values based on field type
     */
    protected function sanitizeValue($value, string $key)
    {
        // Handle arrays recursively
        if (is_array($value)) {
            return array_map(function ($item) use ($key) {
                return $this->sanitizeValue($item, $key);
            }, $value);
        }

        // Skip files and null values
        if ($value === null || $this->isFileField($key)) {
            return $value;
        }

        // Convert to string for sanitization
        $value = (string) $value;

        // Remove potentially dangerous characters for most fields
        if (!$this->isRichTextField($key)) {
            // Strip HTML tags
            $value = strip_tags($value);
            
            // Remove script tags and JavaScript
            $value = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $value);
            
            // Remove potentially dangerous attributes
            $value = preg_replace('/\bon\w+\s*=\s*["\'][^"\']*["\']/i', '', $value);
        }

        // Remove extra whitespace
        $value = trim($value);
        
        // Remove null bytes
        $value = str_replace("\0", '', $value);

        // Sanitize for specific field types
        return $this->sanitizeByFieldType($value, $key);
    }

    /**
     * Additional sanitization based on field type
     */
    protected function sanitizeByFieldType(string $value, string $key)
    {
        // Email fields
        if (Str::contains($key, 'email')) {
            return filter_var($value, FILTER_SANITIZE_EMAIL);
        }

        // URL fields
        if (Str::contains($key, ['url', 'link', 'website'])) {
            return filter_var($value, FILTER_SANITIZE_URL);
        }

        // Phone fields
        if (Str::contains($key, ['phone', 'mobile', 'tel'])) {
            return preg_replace('/[^0-9+\-\s\(\)]/', '', $value);
        }

        // Numeric fields (allow only numbers, dots, and minus sign)
        if (Str::contains($key, ['amount', 'price', 'cost', 'quantity', 'percent'])) {
            return preg_replace('/[^0-9.\-]/', '', $value);
        }

        // General text fields - basic cleanup
        return $value;
    }

    /**
     * Determine if a field is a file upload field
     */
    protected function isFileField(string $key): bool
    {
        $fileFields = ['image', 'photo', 'file', 'document', 'avatar'];
        return $this->hasFile($key) || in_array($key, $fileFields);
    }

    /**
     * Determine if a field allows rich text content
     */
    protected function isRichTextField(string $key): bool
    {
        $richTextFields = ['description', 'content', 'notes', 'body', 'message'];
        return in_array($key, $richTextFields);
    }

    /**
     * Get safe input value for specific field
     */
    public function safeInput(string $key, $default = null)
    {
        return $this->has($key) ? $this->sanitizeValue($this->input($key), $key) : $default;
    }

    /**
     * Get all inputs after sanitization
     */
    public function safeInputs(): array
    {
        return $this->safe()->toArray();
    }
}