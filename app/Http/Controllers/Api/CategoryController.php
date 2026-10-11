<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryListingResource;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CategoryListingResource::collection(
            Category::query()
                ->withCount([
                    'products' => fn (Builder $query) => $query
                        ->where('is_active', true)
                        ->whereHas('offers', fn (Builder $offerQuery) => $offerQuery
                            ->where('is_active', true)
                            ->whereHas('vendor', fn (Builder $vendorQuery) => $vendorQuery->where('is_active', true))),
                ])
                ->orderBy('name')
                ->get(),
        );
    }

    public function show(Category $category): CategoryListingResource
    {
        return new CategoryListingResource($category->loadCount([
            'products' => fn (Builder $query) => $query
                ->where('is_active', true)
                ->whereHas('offers', fn (Builder $offerQuery) => $offerQuery
                    ->where('is_active', true)
                    ->whereHas('vendor', fn (Builder $vendorQuery) => $vendorQuery->where('is_active', true))),
        ]));
    }
}
