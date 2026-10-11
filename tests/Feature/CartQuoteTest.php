<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DiscountRule;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartQuoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_quote_without_vendor_uses_lowest_price(): void
    {
        $product = Product::factory()->create(['quantity' => 10]);
        $expensive = Vendor::factory()->create(['name' => 'Expensive', 'is_active' => true]);
        $cheap = Vendor::factory()->create(['name' => 'Cheap', 'is_active' => true]);
        ProductOffer::factory()->for($product)->for($expensive)->create(['price' => '16.25']);
        ProductOffer::factory()->for($product)->for($cheap)->create(['price' => '12.00']);

        $this->postJson('/api/cart/quote', [
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.lines.0.product_id', $product->id)
            ->assertJsonPath('data.lines.0.quantity', 2)
            ->assertJsonPath('data.lines.0.unit_price', '12.00')
            ->assertJsonPath('data.lines.0.selected_vendor.id', $cheap->id)
            ->assertJsonPath('data.lines.0.lowest_vendor.id', $cheap->id)
            ->assertJsonPath('data.lines.0.lowest_price', '12.00')
            ->assertJsonPath('data.lines.0.line_subtotal', '24.00')
            ->assertJsonPath('data.lines.0.discount_amount', '0.00')
            ->assertJsonPath('data.lines.0.line_total', '24.00')
            ->assertJsonPath('data.lines.0.applied_rule', null)
            ->assertJsonPath('data.subtotal', '24.00')
            ->assertJsonPath('data.discount_total', '0.00')
            ->assertJsonPath('data.grand_total', '24.00');
    }

    public function test_quote_with_chosen_vendor_uses_that_price_and_reports_lowest(): void
    {
        $product = Product::factory()->create(['quantity' => 10]);
        $expensive = Vendor::factory()->create(['name' => 'Expensive', 'is_active' => true]);
        $cheap = Vendor::factory()->create(['name' => 'Cheap', 'is_active' => true]);
        ProductOffer::factory()->for($product)->for($expensive)->create(['price' => '16.25']);
        ProductOffer::factory()->for($product)->for($cheap)->create(['price' => '12.00']);

        $this->postJson('/api/cart/quote', [
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 1, 'vendor_id' => $expensive->id],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.lines.0.unit_price', '16.25')
            ->assertJsonPath('data.lines.0.selected_vendor.id', $expensive->id)
            ->assertJsonPath('data.lines.0.lowest_vendor.id', $cheap->id)
            ->assertJsonPath('data.lines.0.lowest_price', '12.00')
            ->assertJsonPath('data.grand_total', '16.25');
    }

    public function test_quantity_discount_applies_per_product_and_vendor_group(): void
    {
        $product = Product::factory()->create(['quantity' => 20]);
        $vendor = Vendor::factory()->create(['is_active' => true]);
        ProductOffer::factory()->for($product)->for($vendor)->create(['price' => '10.00']);
        $rule = DiscountRule::factory()->quantity(2)->create([
            'name' => 'Two for ten off',
            'discount_type' => DiscountRule::DISCOUNT_TYPE_PERCENTAGE,
            'value' => 10,
        ]);

        $this->postJson('/api/cart/quote', [
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 1, 'vendor_id' => $vendor->id],
                ['product_id' => $product->id, 'quantity' => 1, 'vendor_id' => $vendor->id],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.lines.0.discount_amount', '1.00')
            ->assertJsonPath('data.lines.0.line_total', '9.00')
            ->assertJsonPath('data.lines.0.applied_rule.name', $rule->name)
            ->assertJsonPath('data.lines.1.discount_amount', '1.00')
            ->assertJsonPath('data.lines.1.line_total', '9.00')
            ->assertJsonPath('data.discount_total', '2.00')
            ->assertJsonPath('data.grand_total', '18.00');
    }

    public function test_quantity_discount_does_not_combine_across_vendors(): void
    {
        $product = Product::factory()->create(['quantity' => 20]);
        $firstVendor = Vendor::factory()->create(['is_active' => true]);
        $secondVendor = Vendor::factory()->create(['is_active' => true]);
        ProductOffer::factory()->for($product)->for($firstVendor)->create(['price' => '10.00']);
        ProductOffer::factory()->for($product)->for($secondVendor)->create(['price' => '9.00']);
        DiscountRule::factory()->quantity(2)->create([
            'discount_type' => DiscountRule::DISCOUNT_TYPE_PERCENTAGE,
            'value' => 10,
        ]);

        $this->postJson('/api/cart/quote', [
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 1, 'vendor_id' => $firstVendor->id],
                ['product_id' => $product->id, 'quantity' => 1, 'vendor_id' => $secondVendor->id],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.lines.0.discount_amount', '0.00')
            ->assertJsonPath('data.lines.1.discount_amount', '0.00')
            ->assertJsonPath('data.discount_total', '0.00')
            ->assertJsonPath('data.grand_total', '19.00');
    }

    public function test_quote_groups_totals_by_vendor(): void
    {
        $category = Category::factory()->create();
        $firstVendor = Vendor::factory()->create(['is_active' => true]);
        $secondVendor = Vendor::factory()->create(['is_active' => true]);
        $soupBowl = Product::factory()->create(['category_id' => $category->id, 'quantity' => 20]);
        $cerealBowl = Product::factory()->create(['category_id' => $category->id, 'quantity' => 20]);
        ProductOffer::factory()->for($soupBowl)->for($firstVendor)->create(['price' => '10.00']);
        ProductOffer::factory()->for($soupBowl)->for($secondVendor)->create(['price' => '9.00']);
        ProductOffer::factory()->for($cerealBowl)->for($secondVendor)->create(['price' => '20.00']);

        $this->postJson('/api/cart/quote', [
            'lines' => [
                ['product_id' => $soupBowl->id, 'quantity' => 2, 'vendor_id' => $firstVendor->id],
                ['product_id' => $cerealBowl->id, 'quantity' => 1, 'vendor_id' => $secondVendor->id],
                ['product_id' => $soupBowl->id, 'quantity' => 1, 'vendor_id' => $secondVendor->id],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.subtotal', '49.00')
            ->assertJsonPath('data.grand_total', '49.00')
            ->assertJsonCount(2, 'data.vendors')
            ->assertJsonPath('data.vendors.0.vendor_id', $firstVendor->id)
            ->assertJsonPath('data.vendors.0.subtotal', '20.00')
            ->assertJsonPath('data.vendors.0.total', '20.00')
            ->assertJsonPath('data.vendors.0.line_indexes', [0])
            ->assertJsonPath('data.vendors.1.vendor_id', $secondVendor->id)
            ->assertJsonPath('data.vendors.1.subtotal', '29.00')
            ->assertJsonPath('data.vendors.1.total', '29.00')
            ->assertJsonPath('data.vendors.1.line_indexes', [1, 2]);
    }

    public function test_quote_includes_category_discount_at_quantity_one(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'quantity' => 5]);
        $vendor = Vendor::factory()->create(['is_active' => true]);
        ProductOffer::factory()->for($product)->for($vendor)->create(['price' => '20.00']);
        $rule = DiscountRule::factory()->category($category)->create([
            'name' => 'Category sale',
            'discount_type' => DiscountRule::DISCOUNT_TYPE_PERCENTAGE,
            'value' => 25,
        ]);

        $this->postJson('/api/cart/quote', [
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 1, 'vendor_id' => $vendor->id],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.lines.0.discount_amount', '5.00')
            ->assertJsonPath('data.lines.0.line_total', '15.00')
            ->assertJsonPath('data.lines.0.applied_rule.name', $rule->name)
            ->assertJsonPath('data.discount_total', '5.00')
            ->assertJsonPath('data.grand_total', '15.00');
    }

    public function test_quote_rejects_unknown_vendor_for_product(): void
    {
        $product = Product::factory()->create(['quantity' => 10]);
        $otherVendor = Vendor::factory()->create(['is_active' => true]);
        $productVendor = Vendor::factory()->create(['is_active' => true]);
        ProductOffer::factory()->for($product)->for($productVendor)->create(['price' => '10.00']);

        $this->postJson('/api/cart/quote', [
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 1, 'vendor_id' => $otherVendor->id],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lines.0.vendor_id']);
    }

    public function test_quote_rejects_inactive_offer_and_vendor(): void
    {
        $product = Product::factory()->create(['quantity' => 10]);
        $inactiveOfferVendor = Vendor::factory()->create(['is_active' => true]);
        $inactiveVendor = Vendor::factory()->create(['is_active' => false]);
        ProductOffer::factory()->for($product)->for($inactiveOfferVendor)->create(['price' => '10.00', 'is_active' => false]);
        ProductOffer::factory()->for($product)->for($inactiveVendor)->create(['price' => '10.00']);

        $this->postJson('/api/cart/quote', [
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 1, 'vendor_id' => $inactiveOfferVendor->id],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lines.0.vendor_id']);

        $this->postJson('/api/cart/quote', [
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 1, 'vendor_id' => $inactiveVendor->id],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lines.0.vendor_id']);
    }

    public function test_quote_rejects_quantity_over_stock(): void
    {
        $product = Product::factory()->create(['quantity' => 3]);
        $vendor = Vendor::factory()->create(['is_active' => true]);
        ProductOffer::factory()->for($product)->for($vendor)->create(['price' => '10.00']);

        $this->postJson('/api/cart/quote', [
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 4, 'vendor_id' => $vendor->id],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lines.0.quantity']);
    }

    public function test_quote_rejects_inactive_product(): void
    {
        $product = Product::factory()->create(['quantity' => 5, 'is_active' => false]);
        ProductOffer::factory()->for($product)->for(Vendor::factory()->create(['is_active' => true]))->create(['price' => '10.00']);

        $this->postJson('/api/cart/quote', [
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lines.0.product_id']);
    }

    public function test_quote_validation_errors_for_missing_and_invalid_lines(): void
    {
        $this->postJson('/api/cart/quote', ['lines' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lines']);

        $this->postJson('/api/cart/quote', [
            'lines' => [
                ['product_id' => 9999, 'quantity' => 0],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lines.0.product_id', 'lines.0.quantity']);
    }

    public function test_client_cannot_influence_pricing(): void
    {
        $product = Product::factory()->create(['quantity' => 5]);
        $vendor = Vendor::factory()->create(['is_active' => true]);
        ProductOffer::factory()->for($product)->for($vendor)->create(['price' => '20.00']);

        $this->postJson('/api/cart/quote', [
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 2, 'vendor_id' => $vendor->id, 'unit_price' => '1.00', 'discount_percent' => 90],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.lines.0.unit_price', '20.00')
            ->assertJsonPath('data.lines.0.line_subtotal', '40.00')
            ->assertJsonPath('data.grand_total', '40.00');
    }
}
