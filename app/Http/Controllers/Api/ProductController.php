<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductDetailResource;
use App\Http\Resources\ProductListingResource;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Services\Cart\CartQuoter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request, CartQuoter $quoter): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'sort' => ['nullable', Rule::in(['price_asc', 'price_desc', 'name_asc', 'name_desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
        ]);

        $products = $this->productsQuery($validated)->paginate($validated['per_page'] ?? 12);

        $storefront = $quoter->quoteListing($products->getCollection());

        foreach ($products as $product) {
            $product->setAttribute('storefront', $storefront[$product->id] ?? ['default_vendor' => null, 'offers' => []]);
        }

        return ProductListingResource::collection($products);
    }

    public function show(Product $product, CartQuoter $quoter): ProductDetailResource
    {
        abort_unless($product->is_active, 404);

        $product->load([
            'category',
            'images',
            'offers' => fn ($query) => $query
                ->where('is_active', true)
                ->whereHas('vendor', fn (Builder $vendorQuery) => $vendorQuery->where('is_active', true))
                ->with('vendor'),
        ]);

        $storefront = $quoter->quoteListing(collect([$product]));
        $product->setAttribute('storefront', $storefront[$product->id] ?? ['default_vendor' => null, 'offers' => []]);

        return new ProductDetailResource($product);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function productsQuery(array $filters): Builder
    {
        $query = Product::query()
            ->where('is_active', true)
            ->whereHas('offers', fn (Builder $offerQuery) => $offerQuery
                ->where('is_active', true)
                ->whereHas('vendor', fn (Builder $vendorQuery) => $vendorQuery->where('is_active', true)))
            ->with([
                'category',
                'images',
                'offers' => fn ($offerQuery) => $offerQuery
                    ->where('is_active', true)
                    ->whereHas('vendor', fn (Builder $vendorQuery) => $vendorQuery->where('is_active', true))
                    ->with('vendor'),
            ]);

        if (($filters['search'] ?? '') !== '') {
            $search = $filters['search'];

            $query->where(function (Builder $nested) use ($search): void {
                $nested->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if (($filters['category'] ?? null) !== null) {
            $query->where('category_id', (int) $filters['category']);
        }

        $sort = $filters['sort'] ?? 'name_asc';

        if ($sort === 'price_asc' || $sort === 'price_desc') {
            $lowestPrice = ProductOffer::query()
                ->select('price')
                ->whereColumn('product_vendor.product_id', 'products.id')
                ->where('is_active', true)
                ->whereHas('vendor', fn (Builder $vendorQuery) => $vendorQuery->where('is_active', true))
                ->orderBy('price')
                ->orderBy('id')
                ->limit(1);

            $query->orderBy($lowestPrice, $sort === 'price_asc' ? 'asc' : 'desc');
        } elseif ($sort === 'name_desc') {
            $query->orderByDesc('name');
        } else {
            $query->orderBy('name');
        }

        return $query->orderBy('id');
    }
}
