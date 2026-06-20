<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\DataTransferObjects\RedemptionResult;
use RoundlyConsulting\Coupons\Exceptions\CouponAlreadyRedeemed;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\Tests\Fixtures\Customer;
use RoundlyConsulting\Coupons\ValueObjects\Money;

it('redeems a coupon through the trait with a cart total', function (): void {
    $coupon = Coupon::factory()->active()->fixed(250)->create(['code' => 'SAVE']);
    $customer = Customer::query()->create(['name' => 'Ada']);

    $result = $customer->redeemCoupon('SAVE', new Money(1000, 'USD'));

    expect($result)->toBeInstanceOf(RedemptionResult::class)
        ->and($result->discount->getAmount())->toBe(250)
        ->and($customer->couponRedemptions()->count())->toBe(1);
});

it('redeems a coupon through the trait without a cart total', function (): void {
    $coupon = Coupon::factory()->active()->fixed(100)->create(['code' => 'FREE']);
    $customer = Customer::query()->create(['name' => 'Ada']);

    $result = $customer->redeemCoupon($coupon);

    expect($result)->toBeInstanceOf(RedemptionResult::class)
        ->and($result->total->getCurrency())->toBe('USD');
});

it('enforces the per-redeemer cap through the trait', function (): void {
    Coupon::factory()->active()->fixed()->create(['code' => 'ONCE', 'max_usage_per_redeemer' => 1]);
    $customer = Customer::query()->create(['name' => 'Ada']);

    $customer->redeemCoupon('ONCE', new Money(1000, 'USD'));

    $customer->redeemCoupon('ONCE', new Money(1000, 'USD'));
})->throws(CouponAlreadyRedeemed::class);

it('scopes coupon redemptions to the redeemer', function (): void {
    Coupon::factory()->active()->fixed()->create(['code' => 'SHARED']);
    $ada = Customer::query()->create(['name' => 'Ada']);
    $lin = Customer::query()->create(['name' => 'Lin']);

    $ada->redeemCoupon('SHARED', new Money(1000, 'USD'));

    expect($ada->couponRedemptions()->count())->toBe(1)
        ->and($lin->couponRedemptions()->count())->toBe(0);
});

it('reports whether a redeemer has redeemed a code', function (): void {
    Coupon::factory()->active()->fixed()->create(['code' => 'HELLO']);
    $customer = Customer::query()->create(['name' => 'Ada']);

    expect($customer->hasRedeemed('HELLO'))->toBeFalse();

    $customer->redeemCoupon('HELLO', new Money(1000, 'USD'));

    expect($customer->hasRedeemed('HELLO'))->toBeTrue()
        ->and($customer->hasRedeemed('OTHER'))->toBeFalse();
});

it('reports no redemption when tracking is disabled', function (): void {
    config()->set('coupons.redeemer.track', false);

    Coupon::factory()->active()->fixed()->create(['code' => 'UNTRACKED']);
    $customer = Customer::query()->create(['name' => 'Ada']);

    $customer->redeemCoupon('UNTRACKED', new Money(1000, 'USD'));

    expect($customer->hasRedeemed('UNTRACKED'))->toBeFalse();
});
