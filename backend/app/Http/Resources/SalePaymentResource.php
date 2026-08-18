<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SalePaymentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'sale_id' => $this->sale_id,
            'payment_method_id' => $this->payment_method_id,
            'amount' => $this->amount,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'payment_method' => new PaymentMethodResource($this->whenLoaded('paymentMethod')),
        ];
    }
}
