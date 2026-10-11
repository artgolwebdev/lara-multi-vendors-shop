<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $storefront = $this->storefront ?? ['default_vendor' => null, 'offers' => []];

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'description' => $this->description,
            'stock' => $this->quantity,
            'in_stock' => $this->quantity > 0,
            'category' => $this->whenLoaded('category', fn (): array => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'images' => ProductImageResource::collection($this->images),
            'default_vendor' => $storefront['default_vendor'],
            'offers' => $storefront['offers'],
        ];
    }
}
