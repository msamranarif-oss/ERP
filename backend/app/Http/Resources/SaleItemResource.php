<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SaleItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'variant_id' => $this->variant_id,
            'unit_id' => $this->unit_id,
            'product_name' => $this->product_name,
            'quantity' => (float) $this->quantity,
            'base_quantity' => (float) $this->base_quantity,
            'conversion_factor' => (float) $this->conversion_factor,
            'unit_price' => (float) $this->unit_price,
            'discount_amount' => (float) $this->discount,
            'tax_amount' => (float) $this->tax,
            'tax_percent' => (float) $this->tax_rate,
            'total_amount' => (float) $this->total,
            'unit' => new UnitResource($this->whenLoaded('unit')),
            'product' => new ProductResource($this->whenLoaded('product')),
        ];
    }
}
