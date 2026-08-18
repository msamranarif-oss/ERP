<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InstallmentScheduleResource extends JsonResource
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
            'credit_sale_id' => $this->credit_sale_id,
            'installment_number' => $this->installment_number,
            'due_date' => $this->due_date,
            'principal_amount' => $this->principal_amount,
            'interest_amount' => $this->interest_amount,
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'balance' => $this->balance,
            'penalty_amount' => $this->penalty_amount,
            'status' => $this->status,
            'paid_at' => $this->paid_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            // Relationships
            'credit_sale' => $this->whenLoaded('creditSale'),
            'payments' => $this->whenLoaded('payments'),
        ];
    }
}