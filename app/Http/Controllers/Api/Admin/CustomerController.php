<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\DiscountRule;
use App\Models\User;
use App\Services\Discounts\Contracts\CustomerOrderHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::where('is_admin', false);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $customers = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 20));

        $orderHistory = app(CustomerOrderHistory::class);
        $customers->getCollection()->transform(function ($user) use ($orderHistory) {
            $user->orders_count = $orderHistory->completedOrdersCount($user);

            return $user;
        });

        return UserResource::collection($customers)->response();
    }

    public function show(User $customer): JsonResponse
    {
        if ($customer->is_admin) {
            abort(404);
        }
        $orderHistory = app(CustomerOrderHistory::class);
        $customer->orders_count = $orderHistory->completedOrdersCount($customer);

        return (new UserResource($customer))->response();
    }

    public function loyaltyRules(User $customer): JsonResponse
    {
        if ($customer->is_admin) {
            abort(404);
        }

        $rules = DiscountRule::where('type', DiscountRule::TYPE_LOYALTY)
            ->where('is_active', true)
            ->with('customers')
            ->get();

        $result = $rules->map(function ($rule) use ($customer) {
            $attached = $rule->customers->contains('id', $customer->id);

            return [
                'id' => $rule->id,
                'name' => $rule->name,
                'audience' => $rule->audience,
                'loyalty_condition' => $rule->loyalty_condition,
                'applies_to_everyone' => $rule->audience === DiscountRule::AUDIENCE_ALL_CUSTOMERS,
                'attached' => $attached,
            ];
        });

        return response()->json(['data' => $result]);
    }

    public function attachLoyaltyRule(Request $request, User $customer, DiscountRule $discountRule): JsonResponse
    {
        if ($customer->is_admin || $discountRule->type !== DiscountRule::TYPE_LOYALTY) {
            abort(403);
        }
        $customer->loyaltyDiscountRules()->syncWithoutDetaching([$discountRule->id]);

        return response()->json(['message' => 'Rule attached']);
    }

    public function detachLoyaltyRule(User $customer, DiscountRule $discountRule): JsonResponse
    {
        if ($customer->is_admin || $discountRule->type !== DiscountRule::TYPE_LOYALTY) {
            abort(403);
        }
        $customer->loyaltyDiscountRules()->detach($discountRule->id);

        return response()->json(['message' => 'Rule detached']);
    }
}
