<?php

namespace App\Services;

use App\Models\CreditSaleItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreditSaleItemService extends BaseService
{
    public function __construct()
    {
        parent::__construct(new CreditSaleItem());
    }

    /**
     * Get credit sale items by credit sale ID
     */
    public function getByCreditSaleId(int $creditSaleId)
    {
        return CreditSaleItem::with(['product', 'unit'])
            ->where('credit_sale_id', $creditSaleId)
            ->get();
    }

    /**
     * Calculate total amount for a credit sale
     */
    public function calculateCreditSaleItemsTotal(int $creditSaleId)
    {
        $items = $this->getByCreditSaleId($creditSaleId);
        $total = 0;

        foreach ($items as $item) {
            $total += $item->total_amount;
        }

        return $total;
    }

    /**
     * Create multiple credit sale items
     */
    public function createMultiple(array $itemsData)
    {
        try {
            DB::beginTransaction();

            $items = [];
            foreach ($itemsData as $itemData) {
                $items[] = CreditSaleItem::create($itemData);
            }

            DB::commit();

            return collect($items);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating multiple credit sale items', [
                'error' => $e->getMessage(),
                'data' => $itemsData
            ]);
            
            throw $e;
        }
    }
}