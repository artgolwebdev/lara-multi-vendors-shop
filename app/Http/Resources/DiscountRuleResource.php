<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiscountRuleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'discount_type' => $this->discount_type,
            'value' => $this->value,
            'min_quantity' => $this->min_quantity,
            'vendor_id' => $this->vendor_id,
            'product_id' => $this->product_id,
            'category_id' => $this->category_id,
            'loyalty_condition' => $this->loyalty_condition,
            'audience' => $this->audience,
            'customer_ids' => $this->whenLoaded('customers', fn () => $this->customers->pluck('id')->all()),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'is_active' => $this->is_active,
            'priority' => $this->priority,
            'status' => $this->status(),
            'vendor_name' => $this->whenLoaded('vendor', fn (): ?string => $this->vendor?->name),
            'product_name' => $this->whenLoaded('product', fn (): ?string => $this->product?->name),
            'category_name' => $this->whenLoaded('category', fn (): ?string => $this->category?->name),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
