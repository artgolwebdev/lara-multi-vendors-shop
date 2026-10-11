<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDiscountRuleRequest;
use App\Http\Requests\Admin\UpdateDiscountRuleRequest;
use App\Http\Resources\DiscountRuleResource;
use App\Models\Category;
use App\Models\DiscountRule;
use App\Models\Product;
use App\Models\User;
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
            ->with(['vendor', 'product', 'category', 'customers'])
            ->orderByDesc('priority')
            ->orderBy('name')
            ->paginate(15));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDiscountRuleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $userIds = $data['user_ids'] ?? [];
        unset($data['user_ids']);

        $rule = DiscountRule::create($data);
        $this->syncCustomers($rule, $userIds);

        return (new DiscountRuleResource($rule->load(['vendor', 'product', 'category', 'customers'])))
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
            'loyalty_conditions' => [DiscountRule::LOYALTY_CONDITION_FIRST_ORDER],
            'audiences' => [DiscountRule::AUDIENCE_ALL_CUSTOMERS, DiscountRule::AUDIENCE_SELECTED_CUSTOMERS],
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'vendors' => Vendor::query()->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->orderBy('name')->get(['id', 'name', 'sku', 'category_id']),
            'customers' => User::query()->where('is_admin', false)->orderBy('name')->get(['id', 'name', 'email']),
        ]]);
    }

    /**
     * Display the specified resource.
     */
    public function show(DiscountRule $discountRule): DiscountRuleResource
    {
        return new DiscountRuleResource($discountRule->load(['vendor', 'product', 'category', 'customers']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDiscountRuleRequest $request, DiscountRule $discountRule): DiscountRuleResource
    {
        $data = $request->validated();
        $userIds = $data['user_ids'] ?? [];
        unset($data['user_ids']);

        $discountRule->update($data);
        $this->syncCustomers($discountRule, $userIds);

        return new DiscountRuleResource($discountRule->refresh()->load(['vendor', 'product', 'category', 'customers']));
    }

    /**
     * @param  array<int, int|string>  $userIds
     */
    private function syncCustomers(DiscountRule $rule, array $userIds): void
    {
        if ($rule->type !== DiscountRule::TYPE_LOYALTY || $rule->audience !== DiscountRule::AUDIENCE_SELECTED_CUSTOMERS) {
            $rule->customers()->sync([]);

            return;
        }

        $rule->customers()->sync(array_map('intval', $userIds));
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
