<?php

namespace Tests\Feature;

use App\Models\DiscountRule;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoyaltyQuoteIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_eligible_customer_gets_loyalty_discount(): void
    {
        $vendor = Vendor::factory()->create();
        $product = Product::factory()->create(['quantity' => 10]);
        $product->offers()->create(['vendor_id' => $vendor->id, 'price' => '100.00', 'is_active' => true]);

        DiscountRule::factory()->create([
            'type' => DiscountRule::TYPE_LOYALTY,
            'loyalty_condition' => DiscountRule::LOYALTY_CONDITION_FIRST_ORDER,
            'audience' => DiscountRule::AUDIENCE_ALL_CUSTOMERS,
            'discount_type' => DiscountRule::DISCOUNT_TYPE_PERCENTAGE,
            'value' => '10.00',
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $customer = User::factory()->create(['is_admin' => false]);
        $response = $this->actingAs($customer, 'web')->postJson('/api/cart/quote', [
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(200);
        $line = $response->json('data.lines.0');
        $this->assertEquals('10.00', $line['discount_amount']);
    }

    public function test_guest_does_not_get_loyalty_discount(): void
    {
        $vendor = Vendor::factory()->create();
        $product = Product::factory()->create(['quantity' => 10]);
        $product->offers()->create(['vendor_id' => $vendor->id, 'price' => '100.00', 'is_active' => true]);

        DiscountRule::factory()->create([
            'type' => DiscountRule::TYPE_LOYALTY,
            'loyalty_condition' => DiscountRule::LOYALTY_CONDITION_FIRST_ORDER,
            'audience' => DiscountRule::AUDIENCE_ALL_CUSTOMERS,
            'discount_type' => DiscountRule::DISCOUNT_TYPE_PERCENTAGE,
            'value' => '10.00',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/cart/quote', [
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(200);
        $line = $response->json('data.lines.0');
        $this->assertEquals('0.00', $line['discount_amount']);
    }

    public function test_promotions_expose_only_all_customer_rules_to_guests(): void
    {
        $vendor = Vendor::factory()->create();
        $product = Product::factory()->create(['quantity' => 10]);
        $product->offers()->create(['vendor_id' => $vendor->id, 'price' => '100.00', 'is_active' => true]);

        DiscountRule::factory()->create([
            'type' => DiscountRule::TYPE_LOYALTY,
            'loyalty_condition' => DiscountRule::LOYALTY_CONDITION_FIRST_ORDER,
            'audience' => DiscountRule::AUDIENCE_ALL_CUSTOMERS,
            'discount_type' => DiscountRule::DISCOUNT_TYPE_PERCENTAGE,
            'value' => '15.00',
            'is_active' => true,
            'name' => 'Welcome Discount',
        ]);
        DiscountRule::factory()->create([
            'type' => DiscountRule::TYPE_LOYALTY,
            'loyalty_condition' => DiscountRule::LOYALTY_CONDITION_FIRST_ORDER,
            'audience' => DiscountRule::AUDIENCE_SELECTED_CUSTOMERS,
            'discount_type' => DiscountRule::DISCOUNT_TYPE_PERCENTAGE,
            'value' => '50.00',
            'is_active' => true,
            'name' => 'VIP Only',
        ]);

        $response = $this->postJson('/api/cart/quote', [
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(200);
        $promotions = $response->json('data.promotions');
        $this->assertCount(1, $promotions);
        $this->assertEquals('Welcome Discount', $promotions[0]['name']);
    }

    public function test_customer_id_in_request_body_is_ignored(): void
    {
        $vendor = Vendor::factory()->create();
        $product = Product::factory()->create(['quantity' => 10]);
        $product->offers()->create(['vendor_id' => $vendor->id, 'price' => '100.00', 'is_active' => true]);

        DiscountRule::factory()->create([
            'type' => DiscountRule::TYPE_LOYALTY,
            'loyalty_condition' => DiscountRule::LOYALTY_CONDITION_FIRST_ORDER,
            'audience' => DiscountRule::AUDIENCE_ALL_CUSTOMERS,
            'discount_type' => DiscountRule::DISCOUNT_TYPE_PERCENTAGE,
            'value' => '10.00',
            'is_active' => true,
        ]);

        $otherCustomer = User::factory()->create(['is_admin' => false]);

        $response = $this->postJson('/api/cart/quote', [
            'user_id' => $otherCustomer->id,
            'customer_id' => $otherCustomer->id,
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertEquals('0.00', $response->json('data.lines.0.discount_amount'));
    }
}
