<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons;

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Coupons\Commands\ExpireCouponsCommand;
use RoundlyConsulting\Coupons\Commands\PruneCouponsCommand;

final class CouponsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/coupons.php', 'coupons');

        $this->app->singleton(CouponManager::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ExpireCouponsCommand::class,
                PruneCouponsCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/coupons.php' => config_path('coupons.php'),
            ], 'coupons-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'coupons-migrations');
        }
    }
}
