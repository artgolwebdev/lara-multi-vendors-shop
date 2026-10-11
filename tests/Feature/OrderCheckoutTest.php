<?php

namespace Tests\Feature;

use App\Jobs\NotifyVendorJob;
use App\Models\Category;
use App\Models\DiscountRule;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_groups_lines_into_one_sub_order_per_vendor(): void
    {
        $category = Category::factory()->create();
        $vendors = Vendor::factory()->count(3)->create();
        $products = collect(range(0, 2))->map(
            fn (int $index): Product => Product::factory()->create([
                'category_id' => $category->id,
                'quantity' => 10,
            ]),
        );

        foreach ($products as $index => $product) {
            ProductOffer::factory()->for($product)->for($vendors[$index])->create(['price' => '10.00']);
        }

        $response = $this->postJson('/api/orders', $this->guestPayload([
            ['product_id' => $products[0]->id, 'quantity' => 1, 'vendor_id' => $vendors[0]->id],
            ['product_id' => $products[1]->id, 'quantity' => 1, 'vendor_id' => $vendors[1]->id],
            ['product_id' => $products[2]->id, 'quantity' => 1, 'vendor_id' => $vendors[2]->id],
        ]));

        $response->assertCreated()
            ->assertJsonCount(3, 'data.sub_orders')
            ->assertJsonPath('data.subtotal', '30.00')
            ->assertJsonPath('data.total', '30.00');

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('sub_orders', 3);
        $this->assertDatabaseCount('order_items', 3);
    }

    public function test_lowest_price_policy_ignores_the_chosen_vendor(): void
    {
        config(['shop.vendor_selection' => 'lowest_price']);

        $product = Product::factory()->create(['quantity' => 10]);
        $expensive = Vendor::factory()->create(['is_active' => true]);
        $cheap = Vendor::factory()->create(['is_active' => true]);
        ProductOffer::factory()->for($product)->for($expensive)->create(['price' => '20.00']);
        ProductOffer::factory()->for($product)->for($cheap)->create(['price' => '12.00']);

        $response = $this->postJson('/api/orders', $this->guestPayload([
            ['product_id' => $product->id, 'quantity' => 1, 'vendor_id' => $expensive->id],
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.subtotal', '12.00')
            ->assertJsonPath('data.sub_orders.0.vendor.id', $cheap->id);
    }

    public function test_customer_choice_rejects_vendor_without_offer(): void
    {
        $product = Product::factory()->create(['quantity' => 10]);
        $offeredVendor = Vendor::factory()->create(['is_active' => true]);
        $otherVendor = Vendor::factory()->create(['is_active' => true]);
        ProductOffer::factory()->for($product)->for($offeredVendor)->create(['price' => '10.00']);

        $this->postJson('/api/orders', $this->guestPayload([
            ['product_id' => $product->id, 'quantity' => 1, 'vendor_id' => $otherVendor->id],
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lines.0.vendor_id']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_stored_totals_match_the_quote_with_a_quantity_discount(): void
    {
        $product = Product::factory()->create(['quantity' => 20]);
        $vendor = Vendor::factory()->create(['is_active' => true]);
        ProductOffer::factory()->for($product)->for($vendor)->create(['price' => '10.00']);

        DiscountRule::factory()->quantity(2)->create([
            'discount_type' => DiscountRule::DISCOUNT_TYPE_PERCENTAGE,
            'value' => 10,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/orders', $this->guestPayload([
            ['product_id' => $product->id, 'quantity' => 1, 'vendor_id' => $vendor->id],
            ['product_id' => $product->id, 'quantity' => 1, 'vendor_id' => $vendor->id],
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.subtotal', '20.00')
            ->assertJsonPath('data.discount_total', '2.00')
            ->assertJsonPath('data.total', '18.00')
            ->assertJsonPath('data.sub_orders.0.items.0.discount_amount', '1.00')
            ->assertJsonPath('data.sub_orders.0.items.0.line_total', '9.00');

        $this->assertDatabaseHas('order_items', ['discount_amount' => '1.00', 'line_total' => '9.00']);
    }

    public function test_loyalty_first_order_discount_applies_once(): void
    {
        $vendor = Vendor::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['quantity' => 10]);
        ProductOffer::factory()->for($product)->for($vendor)->create(['price' => '100.00']);

        DiscountRule::factory()->create([
            'type' => DiscountRule::TYPE_LOYALTY,
            'loyalty_condition' => DiscountRule::LOYALTY_CONDITION_FIRST_ORDER,
            'audience' => DiscountRule::AUDIENCE_ALL_CUSTOMERS,
            'discount_type' => DiscountRule::DISCOUNT_TYPE_PERCENTAGE,
            'value' => '10.00',
            'is_active' => true,
        ]);

        $customer = User::factory()->create(['is_admin' => false]);

        $payload = [
            'delivery' => $this->party(),
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 1, 'vendor_id' => $vendor->id],
            ],
        ];

        $this->actingAs($customer, 'web')->postJson('/api/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('data.discount_total', '10.00')
            ->assertJsonPath('data.total', '90.00');

        $this->actingAs($customer, 'web')->postJson('/api/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('data.discount_total', '0.00')
            ->assertJsonPath('data.total', '100.00');
    }

    public function test_first_order_stacks_welcome_loyalty_and_quantity_discounts_for_five_units(): void
    {
        $vendor = Vendor::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['quantity' => 20]);
        ProductOffer::factory()->for($product)->for($vendor)->create(['price' => '20.00']);
        $welcomeRule = DiscountRule::factory()->create([
            'name' => 'Welcome discount',
            'type' => DiscountRule::TYPE_LOYALTY,
            'loyalty_condition' => DiscountRule::LOYALTY_CONDITION_FIRST_ORDER,
            'audience' => DiscountRule::AUDIENCE_ALL_CUSTOMERS,
            'discount_type' => DiscountRule::DISCOUNT_TYPE_PERCENTAGE,
            'value' => '10.00',
            'priority' => 20,
            'is_active' => true,
        ]);
        $quantityRule = DiscountRule::factory()->quantity(5)->create([
            'name' => 'Five or more discount',
            'discount_type' => DiscountRule::DISCOUNT_TYPE_PERCENTAGE,
            'value' => '10.00',
            'priority' => 10,
            'is_active' => true,
        ]);
        $customer = User::factory()->create(['is_admin' => false]);

        $payload = [
            'delivery' => $this->party(),
            'lines' => [[
                'product_id' => $product->id,
                'quantity' => 5,
                'vendor_id' => $vendor->id,
            ]],
        ];

        $firstOrder = $this->actingAs($customer, 'web')->postJson('/api/orders', $payload);

        $firstOrder->assertCreated()
            ->assertJsonPath('data.subtotal', '100.00')
            ->assertJsonPath('data.discount_total', '20.00')
            ->assertJsonPath('data.total', '80.00')
            ->assertJsonPath('data.sub_orders.0.items.0.discount_amount', '20.00')
            ->assertJsonPath('data.sub_orders.0.items.0.line_total', '80.00')
            ->assertJsonCount(2, 'data.sub_orders.0.items.0.applied_rules')
            ->assertJsonPath('data.sub_orders.0.items.0.applied_rules.0.rule_id', $welcomeRule->id)
            ->assertJsonPath('data.sub_orders.0.items.0.applied_rules.0.amount', '10.00')
            ->assertJsonPath('data.sub_orders.0.items.0.applied_rules.1.rule_id', $quantityRule->id)
            ->assertJsonPath('data.sub_orders.0.items.0.applied_rules.1.amount', '10.00');

        $secondOrder = $this->actingAs($customer, 'web')->postJson('/api/orders', $payload);

        $secondOrder->assertCreated()
            ->assertJsonPath('data.subtotal', '100.00')
            ->assertJsonPath('data.discount_total', '10.00')
            ->assertJsonPath('data.total', '90.00')
            ->assertJsonCount(1, 'data.sub_orders.0.items.0.applied_rules')
            ->assertJsonPath('data.sub_orders.0.items.0.applied_rules.0.rule_id', $quantityRule->id)
            ->assertJsonPath('data.sub_orders.0.items.0.applied_rules.0.amount', '10.00');
    }

    public function test_guest_checkout_creates_order_without_customer_or_loyalty(): void
    {
        $vendor = Vendor::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['quantity' => 10]);
        ProductOffer::factory()->for($product)->for($vendor)->create(['price' => '50.00']);

        DiscountRule::factory()->create([
            'type' => DiscountRule::TYPE_LOYALTY,
            'loyalty_condition' => DiscountRule::LOYALTY_CONDITION_FIRST_ORDER,
            'audience' => DiscountRule::AUDIENCE_ALL_CUSTOMERS,
            'discount_type' => DiscountRule::DISCOUNT_TYPE_PERCENTAGE,
            'value' => '25.00',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/orders', $this->guestPayload([
            ['product_id' => $product->id, 'quantity' => 1, 'vendor_id' => $vendor->id],
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.user_id', null)
            ->assertJsonPath('data.payment_method', 'cash_on_delivery')
            ->assertJsonPath('data.discount_total', '0.00')
            ->assertJsonPath('data.total', '50.00');

        $this->assertNotNull($response->json('data.guest_token'));

        $this->assertDatabaseHas('orders', [
            'id' => $response->json('data.id'),
            'user_id' => null,
            'payment_method' => 'cash_on_delivery',
        ]);
    }

    public function test_registered_checkout_autofills_customer_from_profile_and_allows_override(): void
    {
        $vendor = Vendor::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['quantity' => 10]);
        ProductOffer::factory()->for($product)->for($vendor)->create(['price' => '10.00']);

        $customer = User::factory()->create([
            'is_admin' => false,
            'name' => 'Profile Name',
            'email' => 'profile@example.com',
            'phone' => '+1 555 0000',
            'address' => '1 Profile Way',
        ]);

        $lines = [[
            'product_id' => $product->id,
            'quantity' => 1,
            'vendor_id' => $vendor->id,
        ]];

        $this->actingAs($customer, 'web')->postJson('/api/orders', [
            'delivery' => $this->party(),
            'lines' => $lines,
        ])
            ->assertCreated()
            ->assertJsonPath('data.customer.name', 'Profile Name')
            ->assertJsonPath('data.customer.email', 'profile@example.com')
            ->assertJsonPath('data.guest_token', null);

        $this->actingAs($customer, 'web')->postJson('/api/orders', [
            'customer' => ['name' => 'Override Name', 'email' => 'override@example.com'],
            'delivery' => $this->party(),
            'lines' => $lines,
        ])
            ->assertCreated()
            ->assertJsonPath('data.customer.name', 'Override Name')
            ->assertJsonPath('data.customer.email', 'override@example.com');
    }

    public function test_payment_method_is_always_cash_on_delivery(): void
    {
        $vendor = Vendor::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['quantity' => 10]);
        ProductOffer::factory()->for($product)->for($vendor)->create(['price' => '10.00']);

        $payload = $this->guestPayload([
            ['product_id' => $product->id, 'quantity' => 1, 'vendor_id' => $vendor->id],
        ]);
        $payload['payment_method'] = 'credit_card';

        $this->postJson('/api/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('data.payment_method', 'cash_on_delivery');
    }

    public function test_idempotency_key_returns_the_same_order(): void
    {
        $vendor = Vendor::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['quantity' => 10]);
        ProductOffer::factory()->for($product)->for($vendor)->create(['price' => '10.00']);

        $payload = $this->guestPayload([
            ['product_id' => $product->id, 'quantity' => 1, 'vendor_id' => $vendor->id],
        ]);
        $payload['idempotency_key'] = 'guest-key-1';

        $first = $this->postJson('/api/orders', $payload)->assertCreated();
        $second = $this->postJson('/api/orders', $payload)->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_idempotency_key_reused_by_another_customer_conflicts(): void
    {
        $vendor = Vendor::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['quantity' => 10]);
        ProductOffer::factory()->for($product)->for($vendor)->create(['price' => '10.00']);

        $payload = $this->guestPayload([
            ['product_id' => $product->id, 'quantity' => 1, 'vendor_id' => $vendor->id],
        ]);
        $payload['idempotency_key'] = 'shared-key';

        $this->postJson('/api/orders', $payload)->assertCreated();

        $customer = User::factory()->create(['is_admin' => false]);

        $this->actingAs($customer, 'web')->postJson('/api/orders', [
            'delivery' => $this->party(),
            'lines' => $payload['lines'],
            'idempotency_key' => 'shared-key',
        ])
            ->assertStatus(409);

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_price_change_returns_409_and_creates_nothing(): void
    {
        $vendor = Vendor::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['quantity' => 10]);
        ProductOffer::factory()->for($product)->for($vendor)->create(['price' => '10.00']);

        $payload = $this->guestPayload([
            ['product_id' => $product->id, 'quantity' => 1, 'vendor_id' => $vendor->id],
        ]);
        $payload['expected_total'] = '99.00';

        $this->postJson('/api/orders', $payload)
            ->assertStatus(409)
            ->assertJsonPath('data.grand_total', '10.00');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_out_of_stock_rejects_order_and_creates_nothing(): void
    {
        $vendor = Vendor::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['quantity' => 3]);
        ProductOffer::factory()->for($product)->for($vendor)->create(['price' => '10.00']);

        $this->postJson('/api/orders', $this->guestPayload([
            ['product_id' => $product->id, 'quantity' => 5, 'vendor_id' => $vendor->id],
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lines.0.quantity']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(3, $product->refresh()->quantity);
    }

    public function test_stock_is_decremented_on_success(): void
    {
        $vendor = Vendor::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['quantity' => 10]);
        ProductOffer::factory()->for($product)->for($vendor)->create(['price' => '10.00']);

        $this->postJson('/api/orders', $this->guestPayload([
            ['product_id' => $product->id, 'quantity' => 4, 'vendor_id' => $vendor->id],
        ]))->assertCreated();

        $this->assertSame(6, $product->refresh()->quantity);
    }

    public function test_snapshots_survive_later_product_and_vendor_changes(): void
    {
        $vendor = Vendor::factory()->create(['is_active' => true, 'name' => 'Original Vendor']);
        $product = Product::factory()->create(['quantity' => 10, 'name' => 'Original Product']);
        $offer = ProductOffer::factory()->for($product)->for($vendor)->create(['price' => '10.00']);

        $this->postJson('/api/orders', $this->guestPayload([
            ['product_id' => $product->id, 'quantity' => 1, 'vendor_id' => $vendor->id],
        ]))->assertCreated();

        $product->update(['name' => 'Renamed Product']);
        $vendor->update(['name' => 'Renamed Vendor']);
        $offer->update(['price' => '99.00']);

        $this->assertDatabaseHas('order_items', [
            'product_name' => 'Original Product',
            'unit_price' => '10.00',
        ]);
        $this->assertDatabaseHas('sub_orders', ['vendor_name' => 'Original Vendor']);
    }

    public function test_one_notify_job_is_queued_per_sub_order(): void
    {
        Queue::fake();

        $vendors = Vendor::factory()->count(2)->create(['is_active' => true]);
        $product = Product::factory()->create(['quantity' => 10]);
        ProductOffer::factory()->for($product)->for($vendors[0])->create(['price' => '10.00']);
        ProductOffer::factory()->for($product)->for($vendors[1])->create(['price' => '11.00']);

        $this->postJson('/api/orders', $this->guestPayload([
            ['product_id' => $product->id, 'quantity' => 1, 'vendor_id' => $vendors[0]->id],
            ['product_id' => $product->id, 'quantity' => 1, 'vendor_id' => $vendors[1]->id],
        ]))->assertCreated();

        Queue::assertPushed(NotifyVendorJob::class, 2);
    }

    /**
     * @param  list<array{product_id: int, quantity: int, vendor_id?: int}>  $lines
     * @return array<string, mixed>
     */
    private function guestPayload(array $lines): array
    {
        return [
            'customer' => $this->party(),
            'delivery' => $this->party(),
            'lines' => $lines,
        ];
    }

    /**
     * @return array{name: string, email: string, phone: string, address: string}
     */
    private function party(): array
    {
        return [
            'name' => 'Casey Guest',
            'email' => 'casey.guest@example.com',
            'phone' => '+1 555 0100',
            'address' => '12 Market Street',
        ];
    }
}
