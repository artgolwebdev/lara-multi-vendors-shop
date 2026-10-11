<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\DiscountRule;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

class DiscountRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            //return;
        }

        $product = Product::query()->orderBy('id')->first();
        $category = Category::query()->orderBy('id')->first();
        $vendor = Vendor::query()->orderBy('id')->first();

        if ($product === null || $category === null) {
            return;
        }

        $rules = [
            [
                'name' => 'Product quantity break',
                'description' => 'Buy three or more of the sample product and save 10%.',
                'type' => DiscountRule::TYPE_QUANTITY,
                'discount_type' => DiscountRule::DISCOUNT_TYPE_PERCENTAGE,
                'value' => 10,
                'min_quantity' => 3,
                'product_id' => $product->id,
                'vendor_id' => $vendor?->id,
                'category_id' => null,
                'starts_at' => null,
                'ends_at' => null,
                'is_active' => true,
                'priority' => 30,
            ],
            [
                'name' => 'Storewide quantity break',
                'description' => 'Buy five or more units across the catalog and save 5%.',
                'type' => DiscountRule::TYPE_QUANTITY,
                'discount_type' => DiscountRule::DISCOUNT_TYPE_PERCENTAGE,
                'value' => 5,
                'min_quantity' => 5,
                'product_id' => null,
                'vendor_id' => null,
                'category_id' => null,
                'starts_at' => null,
                'ends_at' => null,
                'is_active' => true,
                'priority' => 30,
            ],
            [
                'name' => 'Expired category promotion',
                'description' => 'A finished category promotion kept for reporting.',
                'type' => DiscountRule::TYPE_CATEGORY,
                'discount_type' => DiscountRule::DISCOUNT_TYPE_PERCENTAGE,
                'value' => 20,
                'min_quantity' => null,
                'product_id' => null,
                'vendor_id' => null,
                'category_id' => $category->id,
                'starts_at' => now()->subMonths(2),
                'ends_at' => now()->subMonth(),
                'is_active' => true,
                'priority' => 30,
            ],
            [
                'name' => 'Disabled fixed discount',
                'description' => 'A draft rule that is currently disabled.',
                'type' => DiscountRule::TYPE_QUANTITY,
                'discount_type' => DiscountRule::DISCOUNT_TYPE_FIXED,
                'value' => 1.50,
                'min_quantity' => 2,
                'product_id' => null,
                'vendor_id' => null,
                'category_id' => null,
                'starts_at' => null,
                'ends_at' => null,
                'is_active' => false,
                'priority' => 30,
            ],
            [
                'name' => 'Welcome discount',
                'description' => '10% off your first order for all registered customers.',
                'type' => DiscountRule::TYPE_LOYALTY,
                'loyalty_condition' => DiscountRule::LOYALTY_CONDITION_FIRST_ORDER,
                'audience' => DiscountRule::AUDIENCE_ALL_CUSTOMERS,
                'discount_type' => DiscountRule::DISCOUNT_TYPE_PERCENTAGE,
                'value' => 10,
                'min_quantity' => null,
                'product_id' => null,
                'vendor_id' => null,
                'category_id' => null,
                'starts_at' => null,
                'ends_at' => null,
                'is_active' => true,
                'priority' => 30,
            ],
        ];

        foreach ($rules as $rule) {
            DiscountRule::query()->updateOrCreate(['name' => $rule['name']], $rule);
        }
    }
}
