<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CartQuoteRequest;
use App\Services\Cart\CartQuoter;
use App\Services\Cart\Data\CartLineInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class CartQuoteController extends Controller
{
    public function __invoke(CartQuoteRequest $request, CartQuoter $quoter): JsonResponse
    {
        $quote = $quoter->quote($this->inputs($request->validated('lines')));

        return response()->json(['data' => $quote->toArray()]);
    }

    /**
     * @param  list<array{product_id: int|string, quantity: int|string, vendor_id?: int|string|null}>  $lines
     * @return Collection<int, CartLineInput>
     */
    private function inputs(array $lines): Collection
    {
        return collect($lines)->map(
            fn (array $line): CartLineInput => new CartLineInput(
                productId: (int) $line['product_id'],
                quantity: (int) $line['quantity'],
                vendorId: isset($line['vendor_id']) && $line['vendor_id'] !== null ? (int) $line['vendor_id'] : null,
            ),
        );
    }
}
