<?php

namespace Tests\Feature;

use App\Jobs\NotifyVendorJob;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\SubOrder;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderNotifyTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_notify_job_dispatched_per_sub_order_on_success(): void
    {
        Queue::fake();

        $vendors = Vendor::factory()->count(2)->create(['is_active' => true]);
        $product = Product::factory()->create(['quantity' => 10]);
        ProductOffer::factory()->for($product)->for($vendors[0])->create(['price' => '10.00']);
        ProductOffer::factory()->for($product)->for($vendors[1])->create(['price' => '11.00']);

        $this->postJson('/api/orders', $this->payload($product, $vendors))->assertCreated();

        Queue::assertPushed(NotifyVendorJob::class, 2);
        Queue::assertPushed(NotifyVendorJob::class, fn (NotifyVendorJob $job): bool => $job->subOrderId > 0);
    }

    public function test_no_jobs_dispatched_on_failed_order_creation(): void
    {
        Queue::fake();

        $vendor = Vendor::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['quantity' => 1]);
        ProductOffer::factory()->for($product)->for($vendor)->create(['price' => '10.00']);

        $payload = $this->payload($product, [$vendor]);
        $payload['expected_total'] = '99.00';

        $this->postJson('/api/orders', $payload)->assertStatus(409);

        Queue::assertPushed(NotifyVendorJob::class, 0);
    }

    public function test_auto_process_drains_queue_and_sets_notified_at_in_testing(): void
    {
        config(['shop.queue_auto_process' => true]);
        Log::spy();

        $vendor = Vendor::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['quantity' => 10]);
        ProductOffer::factory()->for($product)->for($vendor)->create(['price' => '10.00']);

        $response = $this->postJson('/api/orders', $this->payload($product, [$vendor]));
        $response->assertCreated();
        $this->app->terminate();

        $subOrder = SubOrder::first();
        $this->assertNotNull($subOrder);
        $this->assertEquals('sent', $subOrder->notification_status->value);
        $this->assertNotNull($subOrder->notified_at);
        Log::shouldHaveReceived('info');
    }

    public function test_auto_process_disabled_does_not_drain_immediately(): void
    {
        config(['shop.queue_auto_process' => false]);
        Queue::fake();

        $vendor = Vendor::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['quantity' => 10]);
        ProductOffer::factory()->for($product)->for($vendor)->create(['price' => '10.00']);

        $this->postJson('/api/orders', $this->payload($product, [$vendor]))->assertCreated();

        Queue::assertPushed(NotifyVendorJob::class, 1);
    }

    public function test_notify_endpoint_resets_and_dispatches_job(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['is_admin' => true]);

        $vendor = Vendor::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['quantity' => 10]);
        ProductOffer::factory()->for($product)->for($vendor)->create(['price' => '10.00']);

        $order = $this->postJson('/api/orders', $this->payload($product, [$vendor]))
            ->assertCreated()
            ->json('data');
        $subOrder = SubOrder::first();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/sub-orders/{$subOrder->id}/notify")
            ->assertOk();

        Queue::assertPushed(NotifyVendorJob::class, 2);
    }

    /**
     * @param  Collection<int, Vendor>|array<Vendor>  $vendors
     */
    private function payload(Product $product, $vendors): array
    {
        return [
            'customer' => [
                'name' => 'Casey',
                'email' => 'casey@example.com',
                'phone' => '+1 555 0100',
                'address' => '12 Market Street',
            ],
            'delivery' => [
                'name' => 'Casey',
                'email' => 'casey@example.com',
                'phone' => '+1 555 0100',
                'address' => '12 Market Street',
            ],
            'lines' => collect($vendors)->map(fn (Vendor $vendor): array => [
                'product_id' => $product->id,
                'quantity' => 1,
                'vendor_id' => $vendor->id,
            ])->all(),
        ];
    }
}
