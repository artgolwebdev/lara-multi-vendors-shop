<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class ProductImageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product, ProductImage $image): JsonResponse
    {
        $image = $product->images()->whereKey($image->getKey())->firstOrFail();
        $wasPrimary = $image->is_primary;

        Storage::disk('public')->delete($image->path);
        $image->delete();

        if ($wasPrimary) {
            $product->images()->orderBy('sort_order')->orderBy('id')->first()?->update(['is_primary' => true]);
        }

        return response()->json(status: 204);
    }
}
