<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Services\Discounts\Data\CartLine;
use App\Services\Discounts\DiscountEngine;
use App\Services\Discounts\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DiscountTestController extends Controller
{
    /**
     * Resolve a product/vendor active offer and run the discount engine without
     * writing any catalog, offer, or rule data.
     */
    public function __invoke(Request $request, DiscountEngine $engine): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'vendor_id' => ['required', 'integer', Rule::exists('vendors', 'id')->whereNull('deleted_at')],
            'quantity' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        $offer = ProductOffer::query()
            ->where('product_id', $validated['product_id'])
            ->where('vendor_id', $validated['vendor_id'])
            ->where('is_active', true)
            ->whereHas('vendor', fn (Builder $query): Builder => $query->where('is_active', true))
            ->first();

        if ($offer === null) {
            return response()->json([
                'message' => 'No active offer exists for the selected product and vendor.',
            ], 422);
        }

        $product = Product::query()->findOrFail($validated['product_id']);

        $line = new CartLine(
            productId: $product->id,
            vendorId: (int) $offer->vendor_id,
            quantity: (int) $validated['quantity'],
            unitPriceCents: Money::decimalToCents($offer->price),
            categoryId: $product->category_id,
        );

        $result = $engine->calculate(collect([$line]));

        return response()->json(['data' => $result->toArray()]);
    }
}
