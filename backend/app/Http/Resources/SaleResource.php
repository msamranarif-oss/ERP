<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                        => $this->id,
            'sale_number'               => $this->sale_number,
            'sale_date'                 => $this->sale_date,
            'type'                      => $this->type,
            'order_type'                => $this->order_type,
            'subtotal_amount'           => $this->subtotal,
            'discount_amount'           => $this->discount_amount,
            'tax_amount'                => $this->tax_amount,
            'shipping_amount'           => $this->shipping_amount,
            'total_amount'              => $this->total,
            'paid_amount'               => $this->paid_amount,
            'change_amount'             => $this->change_amount,
            'balance_due'               => $this->balance_due,
            'payment_status'            => $this->payment_status,
            'status'                    => $this->status,
            // Task 5: accounting tracking fields
            'accounting_status'         => $this->accounting_status,
            'accounting_failure_reason' => $this->accounting_failure_reason,
            'notes'                     => $this->notes,
            'customer'                  => new CustomerResource($this->whenLoaded('customer')),
            'items'                     => SaleItemResource::collection($this->whenLoaded('items')),
            'payments'                  => SalePaymentResource::collection($this->whenLoaded('payments')),
            'branch'                    => new BranchResource($this->whenLoaded('branch')),
            'register_session'          => $this->whenLoaded('registerSession'),
            'sold_by'                   => $this->sold_by,
            'voided_at'                 => $this->voided_at,
            'void_reason'               => $this->void_reason,
            'created_at'                => $this->created_at,
            'updated_at'                => $this->updated_at,
        ];
    }
}
