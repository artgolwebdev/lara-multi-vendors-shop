<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_show_and_soft_delete_vendor(): void
    {
        $admin = $this->admin();
        $payload = [
            'name' => 'North Star Goods',
            'email' => 'contact@northstar.example',
            'phone' => '555-0100',
            'address' => '10 Market Street',
            'city' => 'Austin',
            'country' => 'United States',
            'contact_person' => 'Riley Chen',
            'notes' => 'Seasonal home goods',
            'is_active' => true,
        ];

        $response = $this->actingAs($admin)->postJson('/api/admin/vendors', $payload);

        $response->assertCreated()->assertJsonPath('data.name', 'North Star Goods');
        $vendorId = $response->json('data.id');
        $this->assertDatabaseHas('vendors', ['id' => $vendorId, 'email' => $payload['email']]);

        $this->getJson("/api/admin/vendors/{$vendorId}")->assertOk()->assertJsonPath('data.city', 'Austin');
        $this->patchJson("/api/admin/vendors/{$vendorId}", [
            ...$payload,
            'name' => 'North Star Supply',
        ])->assertOk()->assertJsonPath('data.name', 'North Star Supply');

        $this->deleteJson("/api/admin/vendors/{$vendorId}")->assertNoContent();
        $this->assertSoftDeleted('vendors', ['id' => $vendorId]);
    }

    public function test_vendor_email_must_be_unique(): void
    {
        $vendor = Vendor::factory()->create(['email' => 'taken@vendor.example']);

        $this->actingAs($this->admin())
            ->postJson('/api/admin/vendors', [
                'name' => 'Duplicate Vendor',
                'email' => $vendor->email,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_admin_can_create_product_with_images_and_multiple_vendor_offers(): void
    {
        Storage::fake('public');
        $vendors = Vendor::factory()->count(2)->create();

        $admin = $this->admin();
        $response = $this->actingAs($admin)->post('/api/admin/products', [
            'name' => 'Stoneware Bowl',
            'slug' => 'stoneware-bowl',
            'sku' => 'BOWL-001',
            'description' => 'Hand-finished ceramic bowl.',
            'category_id' => Category::factory()->create(['name' => 'Kitchen'])->id,
            'quantity' => 25,
            'is_active' => '1',
            'offers' => [
                ['vendor_id' => $vendors[0]->id, 'price' => '18.50', 'vendor_sku' => 'NORTH-BOWL'],
                ['vendor_id' => $vendors[1]->id, 'price' => '16.25', 'vendor_sku' => 'FIELD-BOWL'],
            ],
            'images' => [
                UploadedFile::fake()->image('bowl-front.jpg'),
                UploadedFile::fake()->image('bowl-side.jpg'),
            ],
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Stoneware Bowl')
            ->assertJsonPath('data.category.name', 'Kitchen')
            ->assertJsonPath('data.lowest_price', '16.25')
            ->assertJsonCount(2, 'data.offers')
            ->assertJsonCount(2, 'data.images');

        $productId = $response->json('data.id');
        $this->assertDatabaseHas('products', ['id' => $productId, 'sku' => 'BOWL-001']);
        $this->assertSame('16.25', ProductOffer::query()->where('product_id', $productId)->orderBy('price')->value('price'));
        $this->actingAs($admin)->getJson("/api/admin/products/{$productId}")
            ->assertOk()
            ->assertJsonPath('data.sku', 'BOWL-001');

        foreach ($response->json('data.images') as $image) {
            Storage::disk('public')->assertExists($image['path']);
        }

        $primaryImage = $response->json('data.images.0');
        $this->deleteJson("/api/admin/products/{$productId}/images/{$primaryImage['id']}")->assertNoContent();
        Storage::disk('public')->assertMissing($primaryImage['path']);
        $this->assertDatabaseHas('product_images', ['product_id' => $productId, 'is_primary' => true]);
    }

    public function test_product_rejects_duplicate_vendors_and_non_positive_prices(): void
    {
        $vendor = Vendor::factory()->create();

        $this->actingAs($this->admin())
            ->postJson('/api/admin/products', [
                'name' => 'Duplicate Offer Product',
                'slug' => 'duplicate-offer-product',
                'sku' => 'DUP-001',
                'category_id' => Category::factory()->create()->id,
                'quantity' => 5,
                'offers' => [
                    ['vendor_id' => $vendor->id, 'price' => 10],
                    ['vendor_id' => $vendor->id, 'price' => 0],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['offers.1.vendor_id', 'offers.1.price']);

        $this->assertDatabaseMissing('products', ['sku' => 'DUP-001']);
    }

    public function test_product_rejects_non_images_and_files_over_the_size_limit(): void
    {
        $vendor = Vendor::factory()->create();

        $this->actingAs($this->admin())
            ->post('/api/admin/products', [
                'name' => 'Invalid Image Product',
                'slug' => 'invalid-image-product',
                'sku' => 'IMAGE-INVALID',
                'category_id' => Category::factory()->create()->id,
                'quantity' => 1,
                'offers' => [['vendor_id' => $vendor->id, 'price' => 5]],
                'images' => [
                    UploadedFile::fake()->create('manual.pdf', 50, 'application/pdf'),
                    UploadedFile::fake()->image('large.jpg')->size(2049),
                ],
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['images.0', 'images.1']);

        $this->assertDatabaseMissing('products', ['sku' => 'IMAGE-INVALID']);
    }

    public function test_lowest_price_ignores_inactive_offers_and_vendors(): void
    {
        $product = Product::factory()->create();
        $activeVendor = Vendor::factory()->create(['is_active' => true]);
        $secondActiveVendor = Vendor::factory()->create(['is_active' => true]);
        $inactiveVendor = Vendor::factory()->create(['is_active' => false]);

        $winningOffer = ProductOffer::factory()->for($product)->for($activeVendor)->create([
            'price' => '8.25',
            'is_active' => true,
        ]);
        ProductOffer::factory()->for($product)->for($secondActiveVendor)->create([
            'price' => '2.00',
            'is_active' => false,
        ]);
        ProductOffer::factory()->for($product)->for($inactiveVendor)->create([
            'price' => '1.00',
            'is_active' => true,
        ]);

        $product->load('lowestOffer');

        $this->assertSame($winningOffer->id, $product->lowestOffer->id);
        $this->assertSame('8.25', $product->lowest_price);
    }

    public function test_updating_product_synchronizes_vendor_offers(): void
    {
        $product = Product::factory()->create();
        $vendors = Vendor::factory()->count(2)->create();
        ProductOffer::factory()->for($product)->for($vendors[0])->create(['price' => 10]);
        ProductOffer::factory()->for($product)->for($vendors[1])->create(['price' => 12]);

        $this->actingAs($this->admin())->patchJson("/api/admin/products/{$product->id}", [
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'category_id' => $product->category_id,
            'quantity' => $product->quantity,
            'is_active' => true,
            'offers' => [
                ['vendor_id' => $vendors[1]->id, 'price' => '9.50', 'is_active' => true],
            ],
        ])->assertOk()->assertJsonPath('data.lowest_price', '9.50');

        $this->assertDatabaseCount('product_vendor', 1);
        $this->assertDatabaseHas('product_vendor', [
            'product_id' => $product->id,
            'vendor_id' => $vendors[1]->id,
            'price' => 9.5,
        ]);
    }

    public function test_admin_can_soft_delete_product(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/products/{$product->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_development_seeder_creates_the_requested_admin_and_vendor_offers(): void
    {
        $this->seed(AdminCatalogSeeder::class);

        $admin = User::query()->where('email', 'admin@admin.com')->firstOrFail();

        $this->assertTrue($admin->is_admin);
        $this->assertTrue(Hash::check('secret', $admin->password));
        $this->assertSame(4, Vendor::query()->count());
        $this->assertSame(8, Product::query()->count());
        $this->assertSame(20, ProductOffer::query()->count());

        foreach (Product::query()->withCount('offers')->get() as $product) {
            $this->assertContains($product->offers_count, [2, 3]);
        }
    }

    public function test_registration_cannot_set_admin_privilege(): void
    {
        $payload = [
            'name' => 'Regular User',
            'email' => 'regular@example.com',
            'password' => 'a-secure-password',
            'password_confirmation' => 'a-secure-password',
            'is_admin' => true,
        ];

        $this->postJson('/api/register', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['is_admin']);

        $this->from('/register')->post('/register', $payload)->assertSessionHasErrors(['is_admin']);
        $this->assertDatabaseMissing('users', ['email' => 'regular@example.com']);

        $user = User::create([
            'name' => 'Mass Assignment Check',
            'email' => 'mass-assignment@example.com',
            'password' => 'a-secure-password',
            'is_admin' => true,
        ]);

        $this->assertFalse($user->refresh()->is_admin);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }
}
