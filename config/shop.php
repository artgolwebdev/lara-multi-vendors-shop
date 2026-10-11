<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Vendor Selection Policy
    |--------------------------------------------------------------------------
    |
    | "customer_choice" honours the vendor the shopper picked for each line and
    | falls back to the lowest active offer. "lowest_price" always ignores the
    | shopper's choice and uses the lowest active offer.
    |
    | Supported: "customer_choice", "lowest_price"
    |
    */

    'vendor_selection' => env('SHOP_VENDOR_SELECTION', 'customer_choice'),

    /*
    |--------------------------------------------------------------------------
    | Queue Auto-Process
    |--------------------------------------------------------------------------
    |
    | When enabled, order placement drains queued vendor notification jobs after
    | the HTTP response is sent, so notifications run without a separate worker.
    | Enabled by default only in the local environment.
    |
    */

    'queue_auto_process' => env('SHOP_QUEUE_AUTO_PROCESS', env('APP_ENV', 'production') === 'local'),

    'queue_auto_process_timeout' => (int) env('SHOP_QUEUE_AUTO_PROCESS_TIMEOUT', 30),

];
