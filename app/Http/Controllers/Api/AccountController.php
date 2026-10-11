<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdatePasswordRequest;
use App\Http\Requests\Account\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\DiscountRule;
use App\Services\Discounts\Contracts\CustomerOrderHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AccountController extends Controller
{
    public function profile(): JsonResponse
    {
        return (new UserResource(auth()->user()))->response();
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = auth()->user();
        $user->update($request->validated());

        return (new UserResource($user))->response();
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = auth()->user();
        $user->password = Hash::make($request->validated('password'));
        $user->save();

        return response()->json(['message' => 'Password updated successfully']);
    }

    public function discounts(): JsonResponse
    {
        $user = auth()->user();
        $now = now();

        $eligible = DiscountRule::where('type', DiscountRule::TYPE_LOYALTY)
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->where('loyalty_condition', DiscountRule::LOYALTY_CONDITION_FIRST_ORDER)
            ->get()
            ->filter(function ($rule) use ($user) {
                if ($user->is_admin) {
                    return false;
                }
                if ($rule->audience === DiscountRule::AUDIENCE_SELECTED_CUSTOMERS) {
                    return $rule->customers()->where('user_id', $user->id)->exists();
                }

                // Check first order
                return app(CustomerOrderHistory::class)->completedOrdersCount($user) === 0;
            })
            ->map(function ($rule) {
                return [
                    'id' => $rule->id,
                    'name' => $rule->name,
                    'description' => $rule->description,
                    'discount_type' => $rule->discount_type,
                    'value' => $rule->value,
                ];
            });

        return response()->json(['data' => $eligible->values()]);
    }
}
