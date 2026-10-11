<?php

namespace Tests\Unit\Services\Cart;

use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\Vendor;
use App\Services\Cart\CartQuoter;
use App\Services\Cart\Data\CartLineInput;
use App\Services\Discounts\Data\AppliedRule;
use App\Services\Discounts\Data\DiscountContext;
use App\Services\Discounts\Data\DiscountLineResult;
use App\Services\Discounts\Data\DiscountResult;
use App\Services\Discounts\DiscountEngine;
use App\Services\Discounts\DiscountHintResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CartQuoterTest extends TestCase
{
    use RefreshDatabase;

    private function quoter(): CartQuoter
    {
        $engine = new FakeDiscountEngine;

        return new CartQuoter($engine, new DiscountHintResolver($engine));
    }

    private function input(int $productId, int $quantity, ?int $vendorId = null): CartLineInput
    {
        return new CartLineInput($productId, $quantity, $vendorId);
    }

    public function test_it_resolves_the_lowest_offer_when_no_vendor_is_chosen(): void
    {
        $product = Product::factory()->create(['quantity' => 10]);
        $cheap = Vendor::factory()->create(['name' => 'Cheap', 'is_active' => true]);
        $expensive = Vendor::factory()->create(['name' => 'Expensive', 'is_active' => true]);
        ProductOffer::factory()->for($product)->for($expensive)->create(['price' => '16.25']);
        ProductOffer::factory()->for($product)->for($cheap)->create(['price' => '12.00']);

        $quote = $this->quoter()->quote(collect([$this->input($product->id, 2)]));

        $line = $quote->lines[0];

        $this->assertSame($cheap->id, $line->selectedVendor['id']);
        $this->assertSame($cheap->id, $line->lowestVendor['id']);
        $this->assertSame(1200, $line->unitPriceCents);
        $this->assertSame(1200, $line->lowestPriceCents);
        $this->assertSame(2400, $line->lineSubtotalCents);
    }

    public function test_it_uses_the_chosen_vendor_offer_and_still_reports_the_lowest(): void
    {
        $product = Product::factory()->create(['quantity' => 10]);
        $cheap = Vendor::factory()->create(['name' => 'Cheap', 'is_active' => true]);
        $expensive = Vendor::factory()->create(['name' => 'Expensive', 'is_active' => true]);
        ProductOffer::factory()->for($product)->for($expensive)->create(['price' => '16.25']);
        ProductOffer::factory()->for($product)->for($cheap)->create(['price' => '12.00']);

        $quote = $this->quoter()->quote(collect([$this->input($product->id, 1, $expensive->id)]));

        $line = $quote->lines[0];

        $this->assertSame($expensive->id, $line->selectedVendor['id']);
        $this->assertSame(1625, $line->unitPriceCents);
        $this->assertSame($cheap->id, $line->lowestVendor['id']);
        $this->assertSame(1200, $line->lowestPriceCents);
    }

    public function test_it_exposes_vendor_options_sorted_by_price_per_product_once(): void
    {
        $product = Product::factory()->create(['quantity' => 20]);
        $cheap = Vendor::factory()->create(['name' => 'Cheap', 'is_active' => true]);
        $expensive = Vendor::factory()->create(['name' => 'Expensive', 'is_active' => true]);
        ProductOffer::factory()->for($product)->for($expensive)->create(['price' => '16.25']);
        ProductOffer::factory()->for($product)->for($cheap)->create(['price' => '12.00']);

        $quote = $this->quoter()->quote(collect([
            $this->input($product->id, 1),
            $this->input($product->id, 2),
        ]));

        $options = $quote->lines[0]->vendorOptions;

        $this->assertCount(2, $options);
        $this->assertSame($cheap->id, $options[0]['vendor_id']);
        $this->assertSame('12.00', $options[0]['price']);
        $this->assertTrue($options[0]['is_lowest']);
        $this->assertSame($expensive->id, $options[1]['vendor_id']);
        $this->assertFalse($options[1]['is_lowest']);
    }

    public function test_it_rejects_an_over_stock_quantity(): void
    {
        $product = Product::factory()->create(['quantity' => 2]);
        ProductOffer::factory()->for($product)->for(Vendor::factory()->create(['is_active' => true]))->create(['price' => '10.00']);

        $this->expectException(ValidationException::class);

        try {
            $this->quoter()->quote(collect([$this->input($product->id, 3)]));
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lines.0.quantity', $exception->errors());

            throw $exception;
        }
    }

    public function test_it_rejects_an_inactive_product(): void
    {
        $product = Product::factory()->create(['quantity' => 5, 'is_active' => false]);
        ProductOffer::factory()->for($product)->for(Vendor::factory()->create(['is_active' => true]))->create(['price' => '10.00']);

        $this->expectException(ValidationException::class);

        try {
            $this->quoter()->quote(collect([$this->input($product->id, 1)]));
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lines.0.product_id', $exception->errors());

            throw $exception;
        }
    }

    public function test_it_rejects_a_vendor_that_does_not_offer_the_product(): void
    {
        $product = Product::factory()->create(['quantity' => 5]);
        $otherVendor = Vendor::factory()->create(['is_active' => true]);
        ProductOffer::factory()->for($product)->for(Vendor::factory()->create(['is_active' => true]))->create(['price' => '10.00']);

        $this->expectException(ValidationException::class);

        try {
            $this->quoter()->quote(collect([$this->input($product->id, 1, $otherVendor->id)]));
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lines.0.vendor_id', $exception->errors());

            throw $exception;
        }
    }

    public function test_it_groups_totals_by_vendor(): void
    {
        $firstVendor = Vendor::factory()->create(['is_active' => true]);
        $secondVendor = Vendor::factory()->create(['is_active' => true]);
        $soupBowl = Product::factory()->create(['quantity' => 20]);
        $cerealBowl = Product::factory()->create(['quantity' => 20]);
        ProductOffer::factory()->for($soupBowl)->for($firstVendor)->create(['price' => '10.00']);
        ProductOffer::factory()->for($soupBowl)->for($secondVendor)->create(['price' => '9.00']);
        ProductOffer::factory()->for($cerealBowl)->for($secondVendor)->create(['price' => '20.00']);

        $quote = $this->quoter()->quote(collect([
            $this->input($soupBowl->id, 2, $firstVendor->id),
            $this->input($cerealBowl->id, 1, $secondVendor->id),
            $this->input($soupBowl->id, 1, $secondVendor->id),
        ]));

        $this->assertCount(2, $quote->vendors);
        $this->assertSame($firstVendor->id, $quote->vendors[0]->vendorId);
        $this->assertSame([0], $quote->vendors[0]->lineIndexes);
        $this->assertSame($secondVendor->id, $quote->vendors[1]->vendorId);
        $this->assertSame([1, 2], $quote->vendors[1]->lineIndexes);
        $this->assertSame(4900, $quote->grandTotalCents);
        $this->assertSame('49.00', $quote->toArray()['grand_total']);
    }

    public function test_it_passes_engine_discounts_through(): void
    {
        $product = Product::factory()->create(['quantity' => 5]);
        $vendor = Vendor::factory()->create(['is_active' => true]);
        ProductOffer::factory()->for($product)->for($vendor)->create(['price' => '10.00']);

        $engine = new FlatDiscountEngine;
        $quoter = new CartQuoter($engine, new DiscountHintResolver($engine));

        $quote = $quoter->quote(collect([$this->input($product->id, 2, $vendor->id)]));

        $this->assertSame(2000, $quote->lines[0]->lineSubtotalCents);
        $this->assertSame(200, $quote->lines[0]->discountAmountCents);
        $this->assertSame(1800, $quote->lines[0]->lineTotalCents);
        $this->assertSame('18.00', $quote->toArray()['grand_total']);
    }

    public function test_an_empty_cart_returns_an_empty_quote(): void
    {
        $quote = $this->quoter()->quote(collect());

        $this->assertSame([], $quote->lines);
        $this->assertSame([], $quote->vendors);
        $this->assertSame(0, $quote->grandTotalCents);
    }
}

/**
 * Deterministic engine that applies no discount without touching the database,
 * so CartQuoter's orchestration can be asserted in isolation.
 */
class FakeDiscountEngine extends DiscountEngine
{
    public int $activeRulesCalls = 0;

    public function __construct() {}

    public function activeRules(): Collection
    {
        $this->activeRulesCalls++;

        return collect();
    }

    public function calculate(Collection $cartLines, ?Collection $rules = null, ?DiscountContext $context = null): DiscountResult
    {
        $lines = [];

        foreach ($cartLines->values() as $line) {
            $lines[] = new DiscountLineResult(
                productId: $line->productId,
                vendorId: $line->vendorId,
                quantity: $line->quantity,
                unitPriceCents: $line->unitPriceCents,
                lineSubtotalCents: $line->subtotalCents(),
                discountAmountCents: 0,
                appliedRules: [],
                lineTotalCents: $line->subtotalCents(),
            );
        }

        $subtotal = array_sum(array_map(static fn (DiscountLineResult $line): int => $line->lineSubtotalCents, $lines));

        return new DiscountResult($lines, $subtotal, 0, $subtotal);
    }
}

/**
 * Engine double that applies a flat 10% discount so CartQuoter pass-through can
 * be verified without depending on real rule semantics.
 */
class FlatDiscountEngine extends DiscountEngine
{
    public function __construct() {}

    public function activeRules(): Collection
    {
        return collect();
    }

    public function calculate(Collection $cartLines, ?Collection $rules = null, ?DiscountContext $context = null): DiscountResult
    {
        $lines = [];
        $subtotal = 0;
        $discount = 0;

        foreach ($cartLines->values() as $line) {
            $lineSubtotal = $line->subtotalCents();
            $lineDiscount = intdiv($lineSubtotal, 10);
            $lines[] = new DiscountLineResult(
                productId: $line->productId,
                vendorId: $line->vendorId,
                quantity: $line->quantity,
                unitPriceCents: $line->unitPriceCents,
                lineSubtotalCents: $lineSubtotal,
                discountAmountCents: $lineDiscount,
                appliedRules: $lineDiscount === 0 ? [] : [new AppliedRule(1, 'Flat 10%', $lineDiscount)],
                lineTotalCents: $lineSubtotal - $lineDiscount,
            );
            $subtotal += $lineSubtotal;
            $discount += $lineDiscount;
        }

        return new DiscountResult($lines, $subtotal, $discount, $subtotal - $discount);
    }
}
