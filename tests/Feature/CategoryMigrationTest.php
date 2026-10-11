<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CategoryMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_conversion_backfills_existing_product_category_values(): void
    {
        $migration = require database_path('migrations/2026_10_11_070818_convert_products_category_to_category_id.php');
        $migration->down();

        $productId = DB::table('products')->insertGetId([
            'name' => 'Legacy Product',
            'slug' => 'legacy-product',
            'sku' => 'LEGACY-001',
            'description' => null,
            'category' => 'Kitchen',
            'quantity' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration->up();

        $categoryId = DB::table('products')->where('id', $productId)->value('category_id');

        $this->assertDatabaseHas('categories', ['id' => $categoryId, 'name' => 'Kitchen']);
        $this->assertSame($categoryId, DB::table('products')->where('id', $productId)->value('category_id'));
        $this->assertFalse(Schema::hasColumn('products', 'category'));
    }
}
