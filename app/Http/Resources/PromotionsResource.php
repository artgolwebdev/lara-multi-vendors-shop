<?php

namespace App\Http\Resources;

use App\Models\DiscountRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromotionsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'discount_type' => $this->discount_type,
            'value' => $this->value,
            'discount_text' => $this->when(
                true,
                function () {
                    if ($this->discount_type === DiscountRule::DISCOUNT_TYPE_PERCENTAGE) {
                        $val = rtrim(rtrim((string) $this->value, '0'), '.');

                        return $val.'% off your first order';
                    }

                    return '$'.$this->value.' off your first order';
                }
            ),
        ];
    }
}
