<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            'payment_method' => $this->payment_method->value,
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'total' => $this->total,
            'placed_at' => $this->placed_at?->toIso8601String(),
            'notes' => $this->notes,
            'customer' => [
                'name' => $this->customer_name,
                'email' => $this->customer_email,
                'phone' => $this->customer_phone,
                'address' => $this->customer_address,
            ],
            'delivery' => [
                'name' => $this->delivery_name,
                'email' => $this->delivery_email,
                'phone' => $this->delivery_phone,
                'address' => $this->delivery_address,
            ],
            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'sub_orders_count' => $this->whenCounted('subOrders'),
            'items_count' => $this->whenCounted('items'),
            'sub_orders' => SubOrderResource::collection($this->whenLoaded('subOrders')),
            'guest_token' => $this->when($request->routeIs('api.orders.store'), $this->guest_token),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
