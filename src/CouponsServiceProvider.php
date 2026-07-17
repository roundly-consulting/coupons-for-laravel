<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons;

use RoundlyConsulting\Coupons\Commands\ExpireCouponsCommand;
use RoundlyConsulting\Coupons\Commands\PruneCouponsCommand;
use RoundlyConsulting\Coupons\Support\CouponModel;
use RoundlyConsulting\Coupons\Support\RedemptionGuard;
use RoundlyConsulting\PackageToolkit\Concerns\RegistersBlueprintMacros;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;

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
                'Default currency' => self::currency(),
                'Redeemer tracking' => config('coupons.redeemer.track', true) === true ? 'ON' : 'OFF',
                // The alphabet is reported by size only: printing it would hand a
                // brute-forcer the exact key space generated codes are drawn from.
                'Generated codes' => self::codeFormat(),
                'Route key' => self::routeKey(),
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

    private static function currency(): string
    {
        $currency = config('coupons.default_currency', 'USD');

        return is_string($currency) && $currency !== '' ? $currency : 'USD';
    }

    private static function codeFormat(): string
    {
        $charset = config('coupons.code.charset', '');
        $length = (int) config('coupons.code.length', 6);

        return $length.' chars from a '.mb_strlen(is_string($charset) ? $charset : '').'-symbol alphabet';
    }

    private static function routeKey(): string
    {
        $key = config('coupons.route_key', 'code');

        return is_string($key) && $key !== '' ? $key : 'code';
    }
}
