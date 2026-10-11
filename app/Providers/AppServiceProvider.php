<?php

namespace App\Providers;

use App\Services\Discounts\CategoryDiscountRule;
use App\Services\Discounts\DiscountEngine;
use App\Services\Discounts\QuantityDiscountRule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->tag([
            QuantityDiscountRule::class,
            CategoryDiscountRule::class,
        ], 'discount.rules');

        $this->app->singleton(DiscountEngine::class, function (Application $app): DiscountEngine {
            return new DiscountEngine($app->tagged('discount.rules'));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
