<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        return ProductResource::collection($this->productsQuery()->paginate(15));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $offers = $validated['offers'];
        unset($validated['offers'], $validated['images']);

        $product = DB::transaction(function () use ($request, $validated, $offers): Product {
            $product = Product::create($validated);
            $product->vendors()->sync($this->offerSyncData($offers));
            $this->storeImages($request, $product);

            return $product;
        });

        return (new ProductResource($product->load(['images', 'offers.vendor', 'lowestOffer.vendor'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load(['images', 'offers.vendor', 'lowestOffer.vendor']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $validated = $request->validated();
        $offers = $validated['offers'];
        unset($validated['offers'], $validated['images']);

        DB::transaction(function () use ($request, $product, $validated, $offers): void {
            $product->update($validated);
            $product->vendors()->sync($this->offerSyncData($offers));
            $this->storeImages($request, $product);
        });

        return new ProductResource($product->refresh()->load(['images', 'offers.vendor', 'lowestOffer.vendor']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(status: 204);
    }

    private function productsQuery(): Builder
    {
        return Product::query()
            ->with(['images', 'offers.vendor', 'lowestOffer.vendor'])
            ->withCount('vendors')
            ->orderBy('name')
            ->orderBy('id');
    }

    /**
     * @param  array<int, array{vendor_id: int|string, price: int|float|string, vendor_sku?: ?string, is_active?: bool|string}>  $offers
     * @return array<int, array{price: int|float|string, vendor_sku: ?string, is_active: bool|string}>
     */
    private function offerSyncData(array $offers): array
    {
        $syncData = [];

        foreach ($offers as $offer) {
            $syncData[(int) $offer['vendor_id']] = [
                'price' => $offer['price'],
                'vendor_sku' => $offer['vendor_sku'] ?? null,
                'is_active' => $offer['is_active'] ?? true,
            ];
        }

        return $syncData;
    }

    private function storeImages(StoreProductRequest|UpdateProductRequest $request, Product $product): void
    {
        $hasPrimaryImage = $product->images()->where('is_primary', true)->exists();
        $sortOrder = (int) $product->images()->max('sort_order') + 1;

        foreach ($request->file('images', []) as $image) {
            $path = $image->storePublicly('products', 'public');

            $product->images()->create([
                'path' => $path,
                'sort_order' => $sortOrder++,
                'is_primary' => ! $hasPrimaryImage,
            ]);

            $hasPrimaryImage = true;
        }
    }
}
