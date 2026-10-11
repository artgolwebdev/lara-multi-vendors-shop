<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('category')->index();
        });

        $legacyCategories = DB::table('products')
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        foreach ($legacyCategories as $legacyCategory) {
            $categoryName = trim((string) $legacyCategory);

            if ($categoryName === '') {
                $categoryName = 'Uncategorized';
            }

            DB::table('categories')->insertOrIgnore([
                'name' => $categoryName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $categoryId = DB::table('categories')->where('name', $categoryName)->value('id');
            $products = DB::table('products');

            if ($legacyCategory === null) {
                $products->whereNull('category');
            } else {
                $products->where('category', $legacyCategory);
            }

            $products->update(['category_id' => $categoryId]);
        }

        if (DB::table('products')->whereNull('category_id')->exists()) {
            throw new RuntimeException('Every existing product must be assigned to a category before conversion.');
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable(false)->change();
            $table->foreign('category_id')->references('id')->on('categories')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('category')->nullable()->after('sku');
        });

        $categories = DB::table('categories')->select('id', 'name')->orderBy('id')->get();

        foreach ($categories as $category) {
            DB::table('products')->where('category_id', $category->id)->update([
                'category' => $category->name,
            ]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropIndex(['category_id']);
            $table->dropColumn('category_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('category')->nullable(false)->change();
            $table->index('category');
        });
    }
};
