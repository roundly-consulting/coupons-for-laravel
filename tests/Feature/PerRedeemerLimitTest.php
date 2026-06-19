<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\Exceptions\CouponAlreadyRedeemed;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\Models\CouponRedemption;
use RoundlyConsulting\Coupons\Tests\Fixtures\Customer;
use RoundlyConsulting\Coupons\ValueObjects\Money;

it('enforces a one-per-customer cap', function (): void {
    $coupon = Coupon::factory()->fixed(500)->active()->create(['max_usage_per_redeemer' => 1]);
    $ada = Customer::query()->create(['name' => 'Ada']);
    $lin = Customer::query()->create(['name' => 'Lin']);

    $coupon->redeemBy($ada, new Money(5000, 'EUR'));

    expect(fn () => $coupon->redeemBy($ada, new Money(5000, 'EUR')))
        ->toThrow(CouponAlreadyRedeemed::class);

    $coupon->redeemBy($lin, new Money(5000, 'EUR'));

    expect($coupon->usageBy($ada))->toBe(1)
        ->and($coupon->usageBy($lin))->toBe(1)
        ->and($coupon->fresh()->usage)->toBe(2);
});

it('removes redemptions when the coupon is deleted', function (): void {
    $coupon = Coupon::factory()->fixed(500)->active()->create();
    $coupon->redeemBy(Customer::query()->create(['name' => 'Ada']), new Money(5000, 'EUR'));

    $coupon->forceDelete();

    expect(CouponRedemption::withTrashed()->count())->toBe(0);
});
