<?php

namespace App\Jobs;

use App\Models\SubOrder;
use App\Services\Orders\Enums\NotificationStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Simulated vendor notification: records the payload to the log and marks the
 * sub-order as notified. Idempotent so retries never log twice.
 */
class NotifyVendorJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $subOrderId) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [5, 15, 30];
    }

    public function handle(): void
    {
        $subOrder = SubOrder::query()
            ->with(['order', 'vendor', 'items'])
            ->find($this->subOrderId);

        if ($subOrder === null || $subOrder->notification_status === NotificationStatus::Sent) {
            return;
        }

        Log::info('vendor.notification', [
            'sub_order_id' => $subOrder->id,
            'order_number' => $subOrder->order?->order_number,
            'vendor' => [
                'id' => $subOrder->vendor_id,
                'name' => $subOrder->vendor_name,
                'email' => $subOrder->vendor_email,
            ],
            'items' => $subOrder->items->map(fn ($item): array => [
                'product_name' => $item->product_name,
                'product_sku' => $item->product_sku,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount_amount' => $item->discount_amount,
            ])->all(),
            'subtotal' => $subOrder->subtotal,
            'discount_total' => $subOrder->discount_total,
            'total' => $subOrder->total,
        ]);

        $subOrder->forceFill([
            'notified_at' => now(),
            'notification_status' => NotificationStatus::Sent,
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        SubOrder::query()
            ->whereKey($this->subOrderId)
            ->update(['notification_status' => NotificationStatus::Failed->value]);

        Log::error('vendor.notification.failed', [
            'sub_order_id' => $this->subOrderId,
            'message' => $exception?->getMessage(),
        ]);
    }
}
