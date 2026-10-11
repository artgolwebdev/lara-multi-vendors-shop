<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductOfferResource extends JsonResource
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
            'vendor_id' => $this->vendor_id,
            'price' => $this->price,
            'vendor_sku' => $this->vendor_sku,
            'is_active' => $this->is_active,
            'vendor' => new VendorResource($this->whenLoaded('vendor')),
        ];
    }
}
