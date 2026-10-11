<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderReadAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_order_with_guest_token(): void
    {
        $vendor = Vendor::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['quantity' => 10]);
        ProductOffer::factory()->for($product)->for($vendor)->create(['price' => '10.00']);

        $response = $this->postJson('/api/orders', $this->payload($product, $vendor));
        $response->assertCreated();
        $order = $response->json('data');

        $this->getJson("/api/orders/{$order['id']}")->assertStatus(404);
        $this->getJson("/api/orders/{$order['id']}?guest_token=wrong")->assertStatus(404);
        $this->getJson("/api/orders/{$order['id']}?guest_token={$order['guest_token']}")->assertOk();
        $this->withHeader('X-Guest-Token', $order['guest_token'])
            ->getJson("/api/orders/{$order['id']}")
            ->assertOk();
    }

    public function test_registered_customer_can_view_own_orders(): void
    {
        $customer = User::factory()->create(['is_admin' => false]);
        $vendor = Vendor::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['quantity' => 10]);
        ProductOffer::factory()->for($product)->for($vendor)->create(['price' => '10.00']);

        $this->actingAs($customer, 'web')->postJson('/api/orders', [
            'delivery' => $this->party(),
            'lines' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'vendor_id' => $vendor->id,
            ]],
        ])->assertCreated();

        $other = User::factory()->create(['is_admin' => false]);
        $this->actingAs($other, 'web')->getJson('/api/orders')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($customer, 'web')->getJson('/api/orders')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_registered_customer_can_view_single_order_and_refresh(): void
    {
        $customer = User::factory()->create(['is_admin' => false]);
        $vendor = Vendor::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['quantity' => 10]);
        ProductOffer::factory()->for($product)->for($vendor)->create(['price' => '25.00']);

        $res = $this->actingAs($customer, 'web')->postJson('/api/orders', [
            'delivery' => $this->party(),
            'lines' => [[
                'product_id' => $product->id,
                'quantity' => 2,
                'vendor_id' => $vendor->id,
            ]],
        ])->assertCreated();

        $orderId = $res->json('data.id');

        // First GET
        $read1 = $this->actingAs($customer, 'web')
            ->getJson("/api/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.order_number', $res->json('data.order_number'))
            ->assertJsonPath('data.total', '50.00');

        $this->assertNotEmpty($read1->json('data.sub_orders.0.items'));

        // Direct/refresh second GET
        $this->actingAs($customer, 'web')
            ->getJson("/api/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.total', '50.00');
    }

    public function test_other_customer_cannot_view_order_returns_404(): void
    {
        $customer = User::factory()->create(['is_admin' => false]);
        $other = User::factory()->create(['is_admin' => false]);
        $vendor = Vendor::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['quantity' => 10]);
        ProductOffer::factory()->for($product)->for($vendor)->create(['price' => '10.00']);

        $res = $this->actingAs($customer, 'web')->postJson('/api/orders', [
            'delivery' => $this->party(),
            'lines' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'vendor_id' => $vendor->id,
            ]],
        ])->assertCreated();

        $orderId = $res->json('data.id');

        $this->actingAs($other, 'web')
            ->getJson("/api/orders/{$orderId}")
            ->assertStatus(404);
    }

    public function test_admin_orders_index_returns_items_count_and_vendor_list(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $vendor = Vendor::factory()->create(['is_active' => true, 'name' => 'Acme Supplies']);
        $product = Product::factory()->create(['quantity' => 10]);
        ProductOffer::factory()->for($product)->for($vendor)->create(['price' => '10.00']);

        $this->postJson('/api/orders', $this->payload($product, $vendor))->assertCreated();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/orders')
            ->assertOk();

        $response->assertJsonPath('data.0.items_count', 1);
        $this->assertNotEmpty($response->json('data.0.sub_orders'));
        $this->assertSame('Acme Supplies', $response->json('data.0.sub_orders.0.vendor.name'));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Product $product, Vendor $vendor): array
    {
        return [
            'customer' => $this->party(),
            'delivery' => $this->party(),
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 1, 'vendor_id' => $vendor->id],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function party(): array
    {
        return [
            'name' => 'Guest',
            'email' => 'guest@example.com',
            'phone' => '+1 555 0100',
            'address' => '123 Street',
        ];
    }
}
