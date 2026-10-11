<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'product_sku' => $this->product_sku,
            'product_category' => $this->product_category,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'discount_amount' => $this->discount_amount,
            'applied_rule' => $this->applied_rule_id === null ? null : [
                'id' => $this->applied_rule_id,
                'name' => $this->applied_rule_name,
                'type' => $this->applied_rule_type,
            ],
            'applied_rules' => $this->applied_rules ?? [],
            'line_total' => $this->line_total,
        ];
    }
}
