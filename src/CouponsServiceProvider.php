<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons;

use Closure;
use RoundlyConsulting\Coupons\Commands\ExpireCouponsCommand;
use RoundlyConsulting\Coupons\Commands\PruneCouponsCommand;
use RoundlyConsulting\Coupons\Exceptions\InvalidCouponConfiguration;
use RoundlyConsulting\Coupons\Support\CodeFormat;
use RoundlyConsulting\Coupons\Support\CouponConfig;
use RoundlyConsulting\Coupons\Support\CouponModel;
use RoundlyConsulting\Coupons\Support\RedemptionGuard;
use RoundlyConsulting\PackageToolkit\Concerns\RegistersBlueprintMacros;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;
use RoundlyConsulting\PackageToolkit\Support\Config;

final class CouponsServiceProvider extends PackageServiceProvider
{
    use RegistersBlueprintMacros;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('coupons')
            ->hasConfigFile()
            ->hasMigrations()
            ->hasTranslations()
            ->hasCommands([
                ExpireCouponsCommand::class,
                PruneCouponsCommand::class,
            ])
            ->contributesToAbout(static fn (): array => [
                'Model' => class_basename(CouponModel::class()),
                'Default currency' => self::orInvalid(CouponConfig::defaultCurrency(...)),
                'Redeemer tracking' => Config::boolean('coupons.redeemer.track', true) ? 'ON' : 'OFF',
                // The alphabet is reported by size only: printing it would hand a
                // brute-forcer the exact key space generated codes are drawn from.
                'Generated codes' => CodeFormat::describe(),
                'Route key' => self::orInvalid(CouponConfig::routeKey(...)),
            ]);
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(RedemptionGuard::class);
        $this->app->singleton(CouponManager::class);
    }

    public function boot(): void
    {
        parent::boot();

        // The migration's key-type-aware morph column is a macro, so it must exist
        // before a host runs `php artisan migrate`.
        $this->registerBlueprintMacros();
    }

    /**
     * A strict read for the `about` row: a broken value renders as INVALID (with the reason)
     * rather than as the default it no longer falls back to, and `about` keeps working.
     *
     * @param  Closure(): string  $read
     */
    private static function orInvalid(Closure $read): string
    {
        try {
            return $read();
        } catch (InvalidCouponConfiguration $e) {
            return 'INVALID: '.$e->getMessage();
        }
    }
}
