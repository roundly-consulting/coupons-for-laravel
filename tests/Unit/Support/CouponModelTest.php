<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\Support\CouponModel;
use RoundlyConsulting\Coupons\Tests\Fixtures\Customer;
use RoundlyConsulting\Coupons\Tests\Fixtures\LockRecordingCoupon;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

it('resolves the packaged model by default', function (): void {
    expect(CouponModel::class())->toBe(Coupon::class);
});

it('resolves a host subclass configured on coupons.model', function (): void {
    config()->set('coupons.model', LockRecordingCoupon::class);

    expect(CouponModel::class())->toBe(LockRecordingCoupon::class);
});

it('falls back to the packaged model when the configured model is not a coupon', function (): void {
    config()->set('coupons.model', Customer::class);

    expect(CouponModel::class())->toBe(Coupon::class);
});

it('rejects a configured value that is not an eloquent model', function (): void {
    config()->set('coupons.model', 'NotAModel');

    CouponModel::class();
})->throws(InvalidConfigurationException::class);
