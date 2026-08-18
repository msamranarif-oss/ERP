<?php

namespace App\Services;

use App\Models\SerialNumber;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SerialNumberService
{
    public function assignToSale(int $serialId, int $saleItemId, int $customerId): SerialNumber
    {
        $serial = SerialNumber::findOrFail($serialId);

        if ($serial->status !== 'in_stock') {
            throw new \Exception("Serial number is not available (status: {$serial->status}).");
        }

        $serial->update([
            'status'       => 'sold',
            'sale_item_id' => $saleItemId,
            'sold_to'      => $customerId,
            'sold_at'      => now(),
        ]);

        return $serial->fresh();
    }

    public function processReturn(int $serialId, string $reason = ''): SerialNumber
    {
        $serial = SerialNumber::findOrFail($serialId);
        $serial->update([
            'status'       => 'returned',
            'sale_item_id' => null,
            'notes'        => $reason ?: $serial->notes,
        ]);
        return $serial->fresh();
    }

    public function markDefective(int $serialId, string $reason): SerialNumber
    {
        $serial = SerialNumber::findOrFail($serialId);
        $serial->update(['status' => 'defective', 'notes' => $reason]);
        return $serial->fresh();
    }

    public function search(string $query): Collection
    {
        return SerialNumber::where('serial_number', 'like', "%{$query}%")
                            ->orWhere('imei', 'like', "%{$query}%")
                            ->with(['product', 'customer'])
                            ->limit(50)
                            ->get();
    }

    public function getWarrantyExpiring(int $days = 30): Collection
    {
        return SerialNumber::warrantyExpiringSoon($days)
                            ->with(['product', 'customer'])
                            ->get();
    }

    public function bulkCreate(array $items): Collection
    {
        return DB::transaction(function () use ($items) {
            return collect($items)->map(fn($item) => SerialNumber::create($item));
        });
    }
}
