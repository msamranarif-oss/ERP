<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'payment_number' => $this->payment_number,
            'credit_sale_id' => $this->credit_sale_id,
            'installment_id' => $this->installment_id,
            'payment_method_id' => $this->payment_method_id,
            'amount' => $this->amount,
            'status' => $this->status,
            'notes' => $this->notes,
            'payment_date' => $this->payment_date,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            // Relationships
            'credit_sale' => $this->whenLoaded('creditSale'),
            'installment' => $this->whenLoaded('installment'),
            'payment_method' => $this->whenLoaded('paymentMethod'),
            'created_by_user' => $this->whenLoaded('createdBy'),
        ];
    }
}