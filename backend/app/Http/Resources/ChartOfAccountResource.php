<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ChartOfAccountResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'parent_id' => $this->parent_id,
            'account_type_id' => $this->account_type_id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'is_system' => $this->is_system,
            'allow_direct_posting' => $this->allow_direct_posting,
            'opening_balance' => $this->opening_balance,
            'current_balance' => $this->current_balance,
            'level' => $this->level,
            'account_type' => $this->whenLoaded('accountType', function () {
                return new AccountTypeResource($this->accountType);
            }),
            'parent' => $this->whenLoaded('parent', function () {
                return new self($this->parent);
            }),
            'children' => $this->whenLoaded('children', function () {
                return self::collection($this->children);
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
