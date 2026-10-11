<?php

namespace App\Services\Orders;

use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Optionally drains the queue after the HTTP response has been sent so queued
 * vendor notifications run without a separate worker process. Disabled when
 * shop.queue_auto_process is false, in which case a real worker is required.
 */
class QueueAutoProcessor
{
    public function drainAfterResponse(): void
    {
        if (! config('shop.queue_auto_process')) {
            return;
        }

        app()->terminating(function (): void {
            try {
                Artisan::call('queue:work', [
                    '--stop-when-empty' => true,
                    '--max-time' => (int) config('shop.queue_auto_process_timeout', 30),
                    '--tries' => 3,
                ]);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }
}
