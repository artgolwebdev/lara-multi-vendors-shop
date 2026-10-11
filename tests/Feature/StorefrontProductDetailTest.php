<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DiscountRule;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductOffer;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontProductDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_detail_returns_description_images_and_offers(): void
    {
        $category = Category::factory()->create(['name' => 'Home']);
        $vendor = Vendor::factory()->create(['is_active' => true]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Linen Apron',
            'sku' => 'APN-001',
            'description' => 'Woven linen, apron strings included.',
            'quantity' => 7,
        ]);
        ProductOffer::factory()->for($product)->for($vendor)->create(['price' => '24.00']);
        ProductImage::factory()->for($product)->create(['path' => 'products/apron.jpg', 'sort_order' => 1, 'is_primary' => true]);

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Linen Apron')
            ->assertJsonPath('data.sku', 'APN-001')
            ->assertJsonPath('data.description', 'Woven linen, apron strings included.')
            ->assertJsonPath('data.stock', 7)
            ->assertJsonPath('data.in_stock', true)
            ->assertJsonPath('data.category.name', 'Home')
            ->assertJsonPath('data.default_vendor.id', $vendor->id)
            ->assertJsonPath('data.offers.0.price', '24.00')
            ->assertJsonCount(1, 'data.images');
    }

    public function test_inactive_product_detail_returns_404(): void
    {
        $product = Product::factory()->create(['is_active' => false]);
        ProductOffer::factory()->for($product)->for(Vendor::factory()->create(['is_active' => true]))->create(['price' => '10.00']);

        $this->getJson("/api/products/{$product->id}")->assertNotFound();
    }

    public function test_detail_includes_category_discount_at_quantity_one(): void
    {
        $category = Category::factory()->create();
        $vendor = Vendor::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id, 'quantity' => 5]);
        ProductOffer::factory()->for($product)->for($vendor)->create(['price' => '20.00']);
        $rule = DiscountRule::factory()->category($category)->create([
            'discount_type' => DiscountRule::DISCOUNT_TYPE_PERCENTAGE,
            'value' => 25,
        ]);

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.offers.0.discounted_price', '15.00')
            ->assertJsonPath('data.offers.0.applied_rule.name', $rule->name);
    }
}
