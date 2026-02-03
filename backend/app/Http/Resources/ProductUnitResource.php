<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductUnitResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'unit_id' => $this->unit_id,
            'unit' => $this->whenLoaded('unit', function () {
                return new UnitResource($this->unit);
            }),
            'conversion_factor' => $this->conversion_factor,
            'selling_price' => $this->selling_price,
            'cost_price' => $this->cost_price,
            'barcode' => $this->barcode,
            'is_purchase_unit' => $this->is_purchase_unit,
            'is_sale_unit' => $this->is_sale_unit,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
