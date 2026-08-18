<?php

namespace App\Services;

use App\Models\UnitConversion;

class UnitConversionService
{
    /**
     * Convert a quantity from one unit to another.
     *
     * Tries the direct conversion first; if not found, tries the reverse.
     *
     * @throws \Exception if no conversion path exists between the units
     */
    public function convert(float $quantity, int $fromUnitId, int $toUnitId): float
    {
        if ($fromUnitId === $toUnitId) {
            return $quantity;
        }

        // Direct conversion
        $direct = UnitConversion::where('from_unit_id', $fromUnitId)
            ->where('to_unit_id', $toUnitId)
            ->first();

        if ($direct) {
            return $quantity * $direct->conversion_factor;
        }

        // Reverse conversion
        $reverse = UnitConversion::where('from_unit_id', $toUnitId)
            ->where('to_unit_id', $fromUnitId)
            ->first();

        if ($reverse && $reverse->conversion_factor != 0) {
            return $quantity / $reverse->conversion_factor;
        }

        throw new \Exception("No conversion path found from unit {$fromUnitId} to unit {$toUnitId}");
    }

    /**
     * Convert a quantity to the product's base unit.
     */
    public function toBaseUnit(float $quantity, int $fromUnitId, int $baseUnitId): float
    {
        return $this->convert($quantity, $fromUnitId, $baseUnitId);
    }
}
