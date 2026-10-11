<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'vendor' => [
                'id' => $this->vendor_id,
                'name' => $this->vendor_name,
                'email' => $this->vendor_email,
                'phone' => $this->vendor_phone,
            ],
            'status' => $this->status->value,
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'total' => $this->total,
            'notification_status' => $this->notification_status->value,
            'notified_at' => $this->notified_at?->toIso8601String(),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
