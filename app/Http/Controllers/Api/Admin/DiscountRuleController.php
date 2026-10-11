<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDiscountRuleRequest;
use App\Http\Requests\Admin\UpdateDiscountRuleRequest;
use App\Http\Resources\DiscountRuleResource;
use App\Models\Category;
use App\Models\DiscountRule;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DiscountRuleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        return DiscountRuleResource::collection(DiscountRule::query()
            ->with(['vendor', 'product', 'category'])
            ->orderByDesc('priority')
            ->orderBy('name')
            ->paginate(15));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDiscountRuleRequest $request): JsonResponse
    {
        $rule = DiscountRule::create($request->validated());

        return (new DiscountRuleResource($rule->load(['vendor', 'product', 'category'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Return select options for the discount-rule form.
     */
    public function options(): JsonResponse
    {
        return response()->json(['data' => [
            'types' => DiscountRule::types(),
            'discount_types' => DiscountRule::discountTypes(),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'vendors' => Vendor::query()->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->orderBy('name')->get(['id', 'name', 'sku', 'category_id']),
        ]]);
    }

    /**
     * Display the specified resource.
     */
    public function show(DiscountRule $discountRule): DiscountRuleResource
    {
        return new DiscountRuleResource($discountRule->load(['vendor', 'product', 'category']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDiscountRuleRequest $request, DiscountRule $discountRule): DiscountRuleResource
    {
        $discountRule->update($request->validated());

        return new DiscountRuleResource($discountRule->refresh()->load(['vendor', 'product', 'category']));
    }

    /**
     * Toggle or set the active status of the specified resource.
     */
    public function status(Request $request, DiscountRule $discountRule): DiscountRuleResource
    {
        $validated = $request->validate([
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $discountRule->update([
            'is_active' => $validated['is_active'] ?? ! $discountRule->is_active,
        ]);

        return new DiscountRuleResource($discountRule->refresh()->load(['vendor', 'product', 'category']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DiscountRule $discountRule): JsonResponse
    {
        $discountRule->delete();

        return response()->json(status: 204);
    }
}
