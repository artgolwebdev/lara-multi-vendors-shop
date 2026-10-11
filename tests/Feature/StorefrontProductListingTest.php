<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DiscountRule;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StorefrontProductListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_returns_active_products_with_lowest_vendor_default(): void
    {
        $category = Category::factory()->create(['name' => 'Kitchen']);
        $firstVendor = Vendor::factory()->create(['name' => 'Vendor A', 'is_active' => true]);
        $secondVendor = Vendor::factory()->create(['name' => 'Vendor B', 'is_active' => true]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Stoneware Bowl',
            'sku' => 'BOWL-001',
            'quantity' => 10,
        ]);
        ProductOffer::factory()->for($product)->for($firstVendor)->create(['price' => '16.25']);
        ProductOffer::factory()->for($product)->for($secondVendor)->create(['price' => '12.00']);

        $response = $this->getJson('/api/products')->assertOk();

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Stoneware Bowl')
            ->assertJsonPath('data.0.sku', 'BOWL-001')
            ->assertJsonPath('data.0.stock', 10)
            ->assertJsonPath('data.0.in_stock', true)
            ->assertJsonPath('data.0.category.name', 'Kitchen')
            ->assertJsonPath('data.0.default_vendor.id', $secondVendor->id)
            ->assertJsonPath('data.0.offers.0.is_lowest', true)
            ->assertJsonPath('data.0.offers.0.vendor_id', $secondVendor->id)
            ->assertJsonPath('data.0.offers.0.price', '12.00')
            ->assertJsonPath('data.0.offers.1.vendor_id', $firstVendor->id)
            ->assertJsonPath('data.0.offers.1.is_lowest', false);
    }

    public function test_listing_excludes_inactive_offers_and_vendors(): void
    {
        $product = Product::factory()->create(['quantity' => 5]);
        $activeVendor = Vendor::factory()->create(['is_active' => true]);
        $inactiveVendor = Vendor::factory()->create(['is_active' => false]);
        ProductOffer::factory()->for($product)->for($activeVendor)->create(['price' => '20.00']);
        ProductOffer::factory()->for($product)->for($inactiveVendor)->create(['price' => '5.00']);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.0.default_vendor.id', $activeVendor->id)
            ->assertJsonPath('data.0.offers.0.price', '20.00')
            ->assertJsonCount(1, 'data.0.offers');
    }

    public function test_listing_supports_search_by_name_and_sku(): void
    {
        Category::factory()->create();
        $first = Product::factory()->create(['name' => 'Ceramic Mug', 'sku' => 'MUG-001', 'quantity' => 3]);
        $second = Product::factory()->create(['name' => 'Linen Apron', 'sku' => 'APN-002', 'quantity' => 3]);
        foreach ([$first, $second] as $product) {
            ProductOffer::factory()->for($product)->for(Vendor::factory()->create(['is_active' => true]))->create(['price' => '10.00']);
        }

        $this->getJson('/api/products?search=ceramic')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'MUG-001');

        $this->getJson('/api/products?search=APN-002')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'APN-002');
    }

    public function test_listing_filters_by_category(): void
    {
        $kitchen = Category::factory()->create(['name' => 'Kitchen']);
        $home = Category::factory()->create(['name' => 'Home']);
        $bowl = Product::factory()->create(['category_id' => $kitchen->id, 'name' => 'Bowl', 'quantity' => 3]);
        $candle = Product::factory()->create(['category_id' => $home->id, 'name' => 'Candle', 'quantity' => 3]);
        foreach ([$bowl, $candle] as $product) {
            ProductOffer::factory()->for($product)->for(Vendor::factory()->create(['is_active' => true]))->create(['price' => '10.00']);
        }

        $this->getJson("/api/products?category={$kitchen->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Bowl');
    }

    public function test_listing_sorts_by_price_and_name(): void
    {
        $category = Category::factory()->create();
        $prices = ['30.00', '10.00', '20.00'];
        $products = [];

        foreach ($prices as $price) {
            $product = Product::factory()->create(['category_id' => $category->id, 'name' => 'Product '.$price, 'quantity' => 3]);
            ProductOffer::factory()->for($product)->for(Vendor::factory()->create(['is_active' => true]))->create(['price' => $price]);
            $products[] = $product;
        }

        $this->getJson('/api/products?sort=price_asc')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Product 10.00')
            ->assertJsonPath('data.2.name', 'Product 30.00');

        $this->getJson('/api/products?sort=price_desc')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Product 30.00')
            ->assertJsonPath('data.2.name', 'Product 10.00');

        $this->getJson('/api/products?sort=name_desc')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_listing_includes_discounted_prices_and_quantity_hints(): void
    {
        $category = Category::factory()->create(['name' => 'Kitchen']);
        $vendor = Vendor::factory()->create(['name' => 'North Star', 'is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id, 'quantity' => 10]);
        ProductOffer::factory()->for($product)->for($vendor)->create(['price' => '10.00']);

        $categoryRule = DiscountRule::factory()->category($category)->create([
            'discount_type' => DiscountRule::DISCOUNT_TYPE_PERCENTAGE,
            'value' => 10,
        ]);
        DiscountRule::factory()->quantity(2)->create([
            'name' => 'Volume discount',
            'discount_type' => DiscountRule::DISCOUNT_TYPE_PERCENTAGE,
            'value' => 10,
        ]);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.0.offers.0.price', '10.00')
            ->assertJsonPath('data.0.offers.0.discounted_price', '9.00')
            ->assertJsonPath('data.0.offers.0.discount_amount', '1.00')
            ->assertJsonPath('data.0.offers.0.applied_rule.id', $categoryRule->id)
            ->assertJsonPath('data.0.offers.0.applied_rule.name', $categoryRule->name)
            ->assertJsonPath('data.0.offers.0.discount_hint.min_quantity', 2)
            ->assertJsonPath('data.0.offers.0.discount_hint.label', 'Buy 2+ and get 10% off');
    }

    public function test_listing_marks_out_of_stock_products(): void
    {
        $product = Product::factory()->create(['quantity' => 0]);
        ProductOffer::factory()->for($product)->for(Vendor::factory()->create(['is_active' => true]))->create(['price' => '10.00']);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.0.in_stock', false);
    }

    public function test_listing_query_count_is_flat_as_product_count_grows(): void
    {
        $vendor = Vendor::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['quantity' => 5]);
        ProductOffer::factory()->for($product)->for($vendor)->create(['price' => '10.00']);

        $countQueries = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();

            $this->getJson('/api/products')->assertOk();

            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $single = $countQueries();

        foreach (range(1, 4) as $ignored) {
            $extra = Product::factory()->create(['quantity' => 5]);
            ProductOffer::factory()->for($extra)->for($vendor)->create(['price' => '10.00']);
        }

        $this->assertSame($single, $countQueries());
    }
}
