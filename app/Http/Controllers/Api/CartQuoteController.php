<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CartQuoteRequest;
use App\Http\Resources\PromotionsResource;
use App\Models\DiscountRule;
use App\Services\Cart\CartQuoter;
use App\Services\Cart\Data\CartLineInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class CartQuoteController extends Controller
{
    public function __invoke(CartQuoteRequest $request, CartQuoter $quoter): JsonResponse
    {
        $quote = $quoter->quote($this->inputs($request->validated('lines')), $request->user('web'));

        $data = $quote->toArray();
        $data['promotions'] = PromotionsResource::collection($this->promotionsForGuest($request))->toArray($request);

        return response()->json(['data' => $data]);
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

    private function promotionsForGuest(CartQuoteRequest $request): Collection
    {
        if ($request->user('web')) {
            return collect();
        }
        $now = now();

        return DiscountRule::where('type', DiscountRule::TYPE_LOYALTY)
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->where('loyalty_condition', DiscountRule::LOYALTY_CONDITION_FIRST_ORDER)
            ->where('audience', DiscountRule::AUDIENCE_ALL_CUSTOMERS)
            ->get();
    }
}
