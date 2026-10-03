<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\Support\CouponModel;
use RoundlyConsulting\Coupons\Tests\Fixtures\CustomCoupon;
use RoundlyConsulting\Coupons\Tests\Fixtures\Customer;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

it('resolves the packaged model by default', function (): void {
    expect(CouponModel::class())->toBe(Coupon::class);
});

it('resolves a host subclass configured on coupons.model', function (): void {
    config()->set('coupons.model', CustomCoupon::class);

    expect(CouponModel::class())->toBe(CustomCoupon::class);
});

it('refuses a foreign model instead of falling back to the packaged one', function (): void {
    // The toolkit refuses any class that is not the packaged model or a subclass of it.
    config()->set('coupons.model', Customer::class);

    expect(fn (): string => CouponModel::class())->toThrow(
        InvalidConfigurationException::class,
        'Configuration value [coupons.model] must be a class-string of ['.Coupon::class.'], ['.Customer::class.'] given.',
    );
});

it('rejects a configured value that is not an eloquent model', function (): void {
    config()->set('coupons.model', 'NotAModel');

    CouponModel::class();
})->throws(InvalidConfigurationException::class);
