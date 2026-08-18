<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'description' => $this->description,
            'image' => $this->image ? asset('storage/' . $this->image) : null,
            'cost_price' => $this->cost_price,
            'selling_price' => $this->selling_price,
            'min_price' => $this->min_price,
            'reorder_level' => $this->reorder_level,
            'reorder_quantity' => $this->reorder_quantity,
            'is_active' => $this->is_active,
            'tenant_id' => $this->tenant_id,
            'is_sellable' => $this->is_sellable,
            'is_purchasable' => $this->is_purchasable,
            'track_inventory' => $this->track_inventory,
            'has_variants' => $this->has_variants,
            'allow_negative_stock' => $this->allow_negative_stock,
            'tax_type' => $this->tax_type,
            'tax_rate' => $this->tax_rate,
            'attributes' => $this->attributes,
            'category' => $this->whenLoaded('category', function () {
                return new CategoryResource($this->category);
            }),
            'base_unit' => $this->whenLoaded('baseUnit', function () {
                return new UnitResource($this->baseUnit);
            }),
            'product_units' => $this->whenLoaded('productUnits', function () {
                return ProductUnitResource::collection($this->productUnits);
            }),
            'variants' => $this->whenLoaded('variants', function () {
                return ProductVariantResource::collection($this->variants);
            }),
            'batches' => $this->whenLoaded('batches'),
            'serial_numbers' => $this->whenLoaded('serialNumbers'),
            'total_stock' => $this->total_stock,
            'available_stock' => $this->available_stock,
            'matched_unit_id' => $this->matched_unit_id ?? null,
            'dynamic_quantity' => $this->dynamic_quantity ?? null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
