<?php

namespace App\Services\Cart;

use App\Models\DiscountRule;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\User;
use App\Services\Cart\Data\CartLineInput;
use App\Services\Cart\Data\PriceQuote;
use App\Services\Cart\Data\PriceQuoteLine;
use App\Services\Cart\Data\VendorQuoteGroup;
use App\Services\Discounts\Data\CartLine;
use App\Services\Discounts\Data\DiscountContext;
use App\Services\Discounts\DiscountEngine;
use App\Services\Discounts\DiscountHintResolver;
use App\Services\Discounts\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Resolves cart lines to concrete vendor offers and delegates every pricing and
 * discount decision to {@see DiscountEngine}. This is the single source of truth
 * for storefront prices, so future order creation can call it unchanged.
 */
class CartQuoter
{
    public function __construct(
        private readonly DiscountEngine $engine,
        private readonly DiscountHintResolver $hintResolver,
    ) {}

    /**
     * @param  Collection<int, CartLineInput>  $lines
     */
    public function quote(Collection $lines, ?User $customer = null): PriceQuote
    {
        $inputs = $lines->values();

        if ($inputs->isEmpty()) {
            return new PriceQuote([], [], 0, 0, 0);
        }

        $products = $this->loadProducts($inputs);

        $resolved = [];
        $errors = [];

        foreach ($inputs as $index => $input) {
            $product = $products->get($input->productId);

            if ($product === null || ! $product->is_active) {
                $errors["lines.{$index}.product_id"][] = 'This product is no longer available.';

                continue;
            }

            if ($input->quantity > $product->quantity) {
                $errors["lines.{$index}.quantity"][] = sprintf(
                    'Only %d left in stock for this product.',
                    $product->quantity,
                );
            }

            $offer = $this->resolveOffer($product, $input->vendorId);

            if ($offer === null) {
                $errors["lines.{$index}.vendor_id"][] = $input->vendorId === null
                    ? 'This product has no active vendor offer.'
                    : 'The selected vendor no longer offers this product.';

                continue;
            }

            $resolved[] = ['product' => $product, 'offer' => $offer, 'input' => $input];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $rules = $this->engine->activeRules();
        $result = $this->engine->calculate(
            collect($resolved)->map(fn (array $item): CartLine => new CartLine(
                productId: $item['product']->id,
                vendorId: (int) $item['offer']->vendor_id,
                quantity: $item['input']->quantity,
                unitPriceCents: Money::decimalToCents($item['offer']->price),
                categoryId: $item['product']->category_id,
            )),
            $rules,
            new DiscountContext($customer),
        );

        $quotedLines = [];
        $groups = [];
        $subtotal = 0;
        $discount = 0;
        $vendorOptionsByProduct = [];

        foreach ($resolved as $index => $item) {
            $product = $item['product'];
            $offer = $item['offer'];
            $engineLine = $result->lines[$index];
            $vendorId = (int) $offer->vendor_id;
            $lowestOffer = $this->sortedOffers($product)->first();

            $line = new PriceQuoteLine(
                productId: $product->id,
                productName: $product->name,
                productSlug: $product->slug,
                image: $this->primaryImageUrl($product),
                category: $product->category === null ? null : [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                ],
                selectedVendor: ['id' => $vendorId, 'name' => $offer->vendor->name],
                unitPriceCents: $engineLine->unitPriceCents,
                lowestPriceCents: Money::decimalToCents($lowestOffer->price),
                lowestVendor: [
                    'id' => (int) $lowestOffer->vendor_id,
                    'name' => $lowestOffer->vendor->name,
                ],
                quantity: $engineLine->quantity,
                stock: $product->quantity,
                lineSubtotalCents: $engineLine->lineSubtotalCents,
                discountAmountCents: $engineLine->discountAmountCents,
                appliedRules: $engineLine->appliedRules,
                lineTotalCents: $engineLine->lineTotalCents,
                discountHint: $this->hintResolver->hintFor($product->id, $vendorId),
                vendorOptions: $vendorOptionsByProduct[$product->id] ??= $this->vendorOptions($product, $rules),
            );

            $quotedLines[] = $line;
            $subtotal += $line->lineSubtotalCents;
            $discount += $line->discountAmountCents;

            $groups[$vendorId] ??= [
                'vendor_id' => $vendorId,
                'vendor_name' => $offer->vendor->name,
                'subtotal' => 0,
                'discount' => 0,
                'indexes' => [],
            ];
            $groups[$vendorId]['subtotal'] += $line->lineSubtotalCents;
            $groups[$vendorId]['discount'] += $line->discountAmountCents;
            $groups[$vendorId]['indexes'][] = $index;
        }

        $vendorGroups = [];

        foreach ($groups as $group) {
            $vendorGroups[] = new VendorQuoteGroup(
                vendorId: $group['vendor_id'],
                vendorName: $group['vendor_name'],
                subtotalCents: $group['subtotal'],
                discountTotalCents: $group['discount'],
                totalCents: $group['subtotal'] - $group['discount'],
                lineIndexes: $group['indexes'],
            );
        }

        return new PriceQuote($quotedLines, $vendorGroups, $subtotal, $discount, $subtotal - $discount);
    }

    /**
     * Computes the quantity-1 price for every active offer of each product.
     *
     * @param  Collection<int, Product>  $products
     * @return array<int, array{default_vendor: array{id: int, name: string}|null, offers: list<array<string, mixed>>}>
     */
    public function quoteListing(Collection $products): array
    {
        $rules = $this->engine->activeRules();

        $cartLines = [];
        $map = [];

        foreach ($products as $product) {
            foreach ($this->sortedOffers($product) as $offer) {
                $cartLines[] = new CartLine(
                    productId: $product->id,
                    vendorId: (int) $offer->vendor_id,
                    quantity: 1,
                    unitPriceCents: Money::decimalToCents($offer->price),
                    categoryId: $product->category_id,
                );
                $map[] = ['product' => $product, 'offer' => $offer];
            }
        }

        $result = $cartLines === [] ? null : $this->engine->calculate(collect($cartLines), $rules);

        $storefront = [];

        foreach ($products as $product) {
            $storefront[$product->id] = ['default_vendor' => null, 'offers' => []];
        }

        foreach ($map as $index => $item) {
            $product = $item['product'];
            $offer = $item['offer'];
            $engineLine = $result->lines[$index];
            $primaryRule = $engineLine->appliedRules[0] ?? null;

            $storefront[$product->id]['offers'][] = [
                'vendor_id' => (int) $offer->vendor_id,
                'vendor_name' => $offer->vendor->name,
                'is_lowest' => false,
                'price' => $offer->price,
                'discounted_price' => Money::centsToDecimal($engineLine->lineTotalCents),
                'discount_amount' => Money::centsToDecimal($engineLine->discountAmountCents),
                'applied_rule' => $primaryRule === null ? null : [
                    'id' => $primaryRule->ruleId,
                    'name' => $primaryRule->ruleName,
                ],
                'discount_hint' => $this->hintResolver
                    ->hintFor($product->id, (int) $offer->vendor_id)?->toArray(),
            ];
        }

        foreach ($storefront as $productId => $data) {
            if ($data['offers'] === []) {
                continue;
            }

            $storefront[$productId]['offers'][0]['is_lowest'] = true;
            $storefront[$productId]['default_vendor'] = [
                'id' => $data['offers'][0]['vendor_id'],
                'name' => $data['offers'][0]['vendor_name'],
            ];
        }

        return $storefront;
    }

    /**
     * @param  Collection<int, CartLineInput>  $inputs
     * @return Collection<int, Product>
     */
    private function loadProducts(Collection $inputs): Collection
    {
        $productIds = $inputs->pluck('productId')->unique()->all();

        return Product::query()
            ->whereIn('id', $productIds)
            ->with([
                'category',
                'images',
                'offers' => fn ($query) => $query
                    ->where('is_active', true)
                    ->whereHas('vendor', fn ($vendorQuery) => $vendorQuery->where('is_active', true))
                    ->with('vendor'),
            ])
            ->get()
            ->keyBy('id');
    }

    private function resolveOffer(Product $product, ?int $vendorId): ?ProductOffer
    {
        $offers = $this->sortedOffers($product);

        if ($vendorId === null) {
            return $offers->first();
        }

        return $offers->firstWhere('vendor_id', $vendorId);
    }

    /**
     * @return Collection<int, ProductOffer>
     */
    private function sortedOffers(Product $product): Collection
    {
        return $product->offers
            ->sortBy(fn (ProductOffer $offer): int => Money::decimalToCents($offer->price))
            ->values();
    }

    /**
     * @param  Collection<int, DiscountRule>  $rules
     * @return list<array{vendor_id: int, vendor_name: string, price: string, discounted_price: string, is_lowest: bool}>
     */
    private function vendorOptions(Product $product, Collection $rules): array
    {
        $offers = $this->sortedOffers($product);

        if ($offers->isEmpty()) {
            return [];
        }

        $cartLines = $offers->map(fn (ProductOffer $offer): CartLine => new CartLine(
            productId: $product->id,
            vendorId: (int) $offer->vendor_id,
            quantity: 1,
            unitPriceCents: Money::decimalToCents($offer->price),
            categoryId: $product->category_id,
        ))->values();

        $result = $this->engine->calculate($cartLines, $rules);

        $options = [];

        foreach ($offers as $index => $offer) {
            $options[] = [
                'vendor_id' => (int) $offer->vendor_id,
                'vendor_name' => $offer->vendor->name,
                'price' => $offer->price,
                'discounted_price' => Money::centsToDecimal($result->lines[$index]->lineTotalCents),
                'is_lowest' => $index === 0,
            ];
        }

        return $options;
    }

    private function primaryImageUrl(Product $product): ?string
    {
        $image = $product->images->firstWhere('is_primary', true) ?? $product->images->first();

        return $image === null ? null : Storage::disk('public')->url($image->path);
    }
}
