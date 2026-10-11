<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontCategoryTest extends TestCase
{
    use RefreshDatabase;

    private function sellableProduct(int $categoryId): Product
    {
        $product = Product::factory()->create([
            'category_id' => $categoryId,
            'quantity' => 3,
        ]);
        ProductOffer::factory()->for($product)->for(Vendor::factory()->create(['is_active' => true]))->create(['price' => '10.00']);

        return $product;
    }

    public function test_categories_index_returns_buyable_product_counts(): void
    {
        $kitchen = Category::factory()->create(['name' => 'Kitchen']);
        $home = Category::factory()->create(['name' => 'Home']);

        $this->sellableProduct($kitchen->id);
        $this->sellableProduct($kitchen->id);
        $this->sellableProduct($home->id);

        // Home has a storefront offer.
        Product::factory()->create(['category_id' => $home->id, 'quantity' => 0, 'is_active' => false]);

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Home')
            ->assertJsonPath('data.0.products_count', 1)
            ->assertJsonPath('data.1.name', 'Kitchen')
            ->assertJsonPath('data.1.products_count', 2);
    }

    public function test_categories_show_returns_single_category(): void
    {
        $category = Category::factory()->create(['name' => 'Kitchen']);
        $this->sellableProduct($category->id);

        $this->getJson("/api/categories/{$category->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Kitchen')
            ->assertJsonPath('data.products_count', 1);
    }

    public function test_categories_show_missing_returns_404(): void
    {
        $this->getJson('/api/categories/9999')->assertNotFound();
    }
}
