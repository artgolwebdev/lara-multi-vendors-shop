<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

class AdminCatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            //return;
        }

        $admin = User::query()->firstOrNew(['email' => 'admin@admin.com']);
        $admin->name = 'Store Administrator';
        $admin->password = 'secret';
        $admin->forceFill(['is_admin' => true])->save();

        $categories = collect(['Home', 'Kitchen', 'Accessories'])
            ->map(fn (string $name): Category => Category::query()->firstOrCreate(['name' => $name])
            );
        $vendors = Vendor::factory()->count(4)->create();

        foreach (range(0, 7) as $index) {
            $product = Product::factory()->create([
                'category_id' => $categories[$index % $categories->count()]->id,
            ]);
            $offerCount = 2 + ($index % 2);

            foreach ($vendors->take($offerCount)->values() as $vendorIndex => $vendor) {
                ProductOffer::factory()->for($product)->for($vendor)->create([
                    'price' => 12 + ($index * 3) + ($vendorIndex * 2.5),
                ]);
            }
        }
    }
}
