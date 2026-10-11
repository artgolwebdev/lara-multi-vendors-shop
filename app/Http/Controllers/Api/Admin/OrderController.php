<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Resources\SubOrderResource;
use App\Jobs\NotifyVendorJob;
use App\Models\Order;
use App\Models\SubOrder;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Discounts\Money;
use App\Services\Orders\Enums\NotificationStatus;
use App\Services\Orders\Enums\OrderStatus;
use App\Services\Orders\QueueAutoProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(private readonly QueueAutoProcessor $queueAutoProcessor) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(max($request->integer('per_page', 20), 1), 100);

        $query = Order::query()
            ->withCount('subOrders')
            ->with('user:id,name,email')
            ->orderByDesc('placed_at')
            ->orderByDesc('id');

        if ($request->filled('vendor_id')) {
            $vendorId = $request->integer('vendor_id');
            $query->whereHas('subOrders', fn ($subQuery) => $subQuery->where('vendor_id', $vendorId));
        }

        if ($request->filled('customer_id')) {
            $query->where('user_id', $request->integer('customer_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('placed_at', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('placed_at', '<=', $request->date('date_to'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->string('search');
            $query->where(function ($subQuery) use ($search): void {
                $subQuery->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }

        return OrderResource::collection($query->paginate($perPage));
    }

    public function show(Order $order): JsonResponse
    {
        return (new OrderResource($order->load(['user', 'subOrders.items'])))->response();
    }

    public function updateSubOrderStatus(Request $request, SubOrder $subOrder): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
        ]);

        $subOrder->update(['status' => $validated['status']]);

        return (new OrderResource($subOrder->order->load('subOrders.items')))->response();
    }

    public function notify(SubOrder $subOrder): JsonResponse
    {
        $subOrder->forceFill([
            'notification_status' => NotificationStatus::Pending,
            'notified_at' => null,
        ])->save();

        NotifyVendorJob::dispatch($subOrder->id);
        $this->queueAutoProcessor->drainAfterResponse();

        return (new SubOrderResource($subOrder->refresh()->load('items')))->response();
    }

    public function vendorOrders(Vendor $vendor): JsonResponse
    {
        $orders = Order::query()
            ->whereHas('subOrders', fn ($query) => $query->where('vendor_id', $vendor->id))
            ->with(['subOrders' => fn ($query) => $query->where('vendor_id', $vendor->id)->with('items')])
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->paginate(20);

        $totalSales = Money::centsToDecimal(
            Money::decimalToCents(SubOrder::query()->where('vendor_id', $vendor->id)->sum('total')),
        );

        return OrderResource::collection($orders)
            ->additional(['total_sales' => $totalSales])
            ->response();
    }

    public function customerOrders(User $customer): JsonResponse
    {
        if ($customer->is_admin) {
            abort(404);
        }

        $orders = Order::query()
            ->where('user_id', $customer->id)
            ->withCount('subOrders')
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->paginate(20);

        $lifetimeTotal = Money::centsToDecimal(
            Money::decimalToCents(
                Order::query()
                    ->where('user_id', $customer->id)
                    ->where('status', '!=', OrderStatus::Cancelled->value)
                    ->sum('total'),
            ),
        );

        return OrderResource::collection($orders)
            ->additional(['lifetime_total' => $lifetimeTotal])
            ->response();
    }
}
