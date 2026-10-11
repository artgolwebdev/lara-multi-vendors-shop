<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'slug' => $this->slug,
            'sku' => $this->sku,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'quantity' => $this->quantity,
            'is_active' => $this->is_active,
            'lowest_price' => $this->lowest_price,
            'lowest_offer' => new ProductOfferResource($this->whenLoaded('lowestOffer')),
            'vendors_count' => $this->whenCounted('vendors'),
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'offers' => ProductOfferResource::collection($this->whenLoaded('offers')),
        ];
    }
}
