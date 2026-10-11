<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                'vendors' => Vendor::query()->count(),
                'active_vendors' => Vendor::query()->where('is_active', true)->count(),
                'products' => Product::query()->count(),
                'active_products' => Product::query()->where('is_active', true)->count(),
                'active_offers' => ProductOffer::query()
                    ->where('is_active', true)
                    ->whereHas('vendor', fn ($query) => $query->where('is_active', true))
                    ->count(),
                'orders' => Order::query()->count(),
            ],
        ]);
    }
}
