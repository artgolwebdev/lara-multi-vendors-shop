<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Cart\Data\CartLineInput;
use App\Services\Orders\PlaceOrderService;
use App\Services\Orders\QueueAutoProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(
        private readonly PlaceOrderService $placeOrder,
        private readonly QueueAutoProcessor $queueAutoProcessor,
    ) {}

    /**
     * Place an order as a guest or an authenticated customer.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $customer = $request->user('web');
        $validated = $request->validated();
        $contact = $validated['customer'] ?? [];

        if ($customer !== null) {
            $contact = array_filter([
                'name' => $contact['name'] ?? $customer->name,
                'email' => $contact['email'] ?? $customer->email,
                'phone' => $contact['phone'] ?? $customer->phone,
                'address' => $contact['address'] ?? $customer->address,
            ], static fn ($value): bool => $value !== null && $value !== '');
        } elseif (empty($contact['name']) || empty($contact['email'])) {
            throw ValidationException::withMessages([
                'customer.name' => 'Your name and email are required to place an order.',
            ]);
        }

        $order = $this->placeOrder->placeOrder(
            $customer,
            $contact,
            $this->inputs($validated['lines']),
            $validated['delivery'],
            $validated['expected_total'] ?? null,
            $validated['idempotency_key'] ?? null,
            $validated['notes'] ?? null,
        );

        $this->queueAutoProcessor->drainAfterResponse();

        return (new OrderResource($order))->response()->setStatusCode(201);
    }

    /**
     * List the authenticated customer's orders.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(max($request->integer('per_page', 10), 1), 50);

        $orders = Order::query()
            ->where('user_id', $request->user('web')->id)
            ->withCount('subOrders')
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return OrderResource::collection($orders);
    }

    /**
     * Show an order for its owner, or for a guest holding the guest token.
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorizeView($request, $order);

        return (new OrderResource($order->load('subOrders.items')))->response();
    }

    /**
     * @param  list<array{product_id: int|string, quantity: int|string, vendor_id?: int|string|null}>  $lines
     * @return Collection<int, CartLineInput>
     */
    private function inputs(array $lines): Collection
    {
        return collect($lines)->map(
            fn (array $line): CartLineInput => new CartLineInput(
                productId: (int) $line['product_id'],
                quantity: (int) $line['quantity'],
                vendorId: isset($line['vendor_id']) && $line['vendor_id'] !== null ? (int) $line['vendor_id'] : null,
            ),
        );
    }

    private function authorizeView(Request $request, Order $order): void
    {
        $user = $request->user('web');

        if ($user !== null && $order->user_id === $user->id) {
            return;
        }

        $token = $request->query('guest_token') ?? $request->header('X-Guest-Token');

        if ($order->guest_token !== null && is_string($token) && hash_equals($order->guest_token, $token)) {
            return;
        }

        abort(404);
    }
}
