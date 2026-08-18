<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentReminderResource extends JsonResource
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
            'installment_schedule_id' => $this->installment_schedule_id,
            'type' => $this->type,
            'status' => $this->status,
            'scheduled_at' => $this->scheduled_at,
            'sent_at' => $this->sent_at,
            'message' => $this->message,
            'response' => $this->response,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            // Relationships
            'credit_sale' => $this->whenLoaded('creditSale'),
            'installment_schedule' => $this->whenLoaded('installmentSchedule'),
        ];
    }
}