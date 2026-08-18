<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\CategoryService;
use App\Services\BrandService;
use App\Services\StockLevelService;

class CustomServicesServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register custom services for automatic dependency injection
        $this->app->singleton(CategoryService::class, function ($app) {
            return new CategoryService();
        });

        $this->app->singleton(BrandService::class, function ($app) {
            return new BrandService();
        });

        $this->app->singleton(StockLevelService::class, function ($app) {
            return new StockLevelService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
