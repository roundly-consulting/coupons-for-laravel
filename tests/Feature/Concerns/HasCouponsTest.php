<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\DataTransferObjects\RedemptionResult;
use RoundlyConsulting\Coupons\Exceptions\CouponAlreadyRedeemed;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\Tests\Fixtures\Customer;
use RoundlyConsulting\Money\Money;

it('redeems a coupon through the trait with a cart total', function (): void {
    $coupon = Coupon::factory()->active()->fixed(250)->create(['code' => 'SAVE']);
    $customer = Customer::query()->create(['name' => 'Ada']);

    $result = $customer->redeemCoupon('SAVE', Money::ofMinor(1000, 'USD'));

    expect($result)->toBeInstanceOf(RedemptionResult::class)
        ->and($result->discount->minor())->toBe('250')
        ->and($customer->couponRedemptions()->count())->toBe(1);
});

// Regression: with no cart total the trait redeemed against a zero amount in
// `coupons.default_currency` — a fixed coupon in any other currency threw CurrencyMismatch,
// and anything else burned a use at a 0.00 discount (the customer's only use of a single-use
// coupon, gone for nothing). A redemption now always prices a real cart.
it('requires a cart total, so a use is never burned at a zero discount', function (): void {
    $coupon = Coupon::factory()->active()->percentage(2000)->create(['code' => 'ONCE', 'max_usage' => 1]);
    $customer = Customer::query()->create(['name' => 'Ada']);

    expect(fn () => $customer->redeemCoupon('ONCE'))->toThrow(ArgumentCountError::class)
        ->and($coupon->fresh()?->usage)->toBe(0);

    $result = $customer->redeemCoupon('ONCE', Money::ofMinor(5000, 'EUR'));

    expect($result->discount->minor())->toBe('1000')
        ->and($coupon->fresh()?->isAtMaximumUsage())->toBeTrue();
});

it('redeems a fixed coupon in a currency other than the default', function (): void {
    config()->set('coupons.default_currency', 'USD');
    Coupon::factory()->active()->fixed(500, 'EUR')->create(['code' => 'EURO']);
    $customer = Customer::query()->create(['name' => 'Ada']);

    $result = $customer->redeemCoupon('EURO', Money::ofMinor(2000, 'EUR'));

    expect($result)->toBeInstanceOf(RedemptionResult::class)
        ->and($result->total->minor())->toBe('1500')
        ->and($result->total->currency()->code)->toBe('EUR');
});

it('enforces the per-redeemer cap through the trait', function (): void {
    Coupon::factory()->active()->fixed()->create(['code' => 'ONCE', 'max_usage_per_redeemer' => 1]);
    $customer = Customer::query()->create(['name' => 'Ada']);

    $customer->redeemCoupon('ONCE', Money::ofMinor(1000, 'USD'));

    $customer->redeemCoupon('ONCE', Money::ofMinor(1000, 'USD'));
})->throws(CouponAlreadyRedeemed::class);

it('scopes coupon redemptions to the redeemer', function (): void {
    Coupon::factory()->active()->fixed()->create(['code' => 'SHARED']);
    $ada = Customer::query()->create(['name' => 'Ada']);
    $lin = Customer::query()->create(['name' => 'Lin']);

    $ada->redeemCoupon('SHARED', Money::ofMinor(1000, 'USD'));

    expect($ada->couponRedemptions()->count())->toBe(1)
        ->and($lin->couponRedemptions()->count())->toBe(0);
});

it('reports whether a redeemer has redeemed a code', function (): void {
    Coupon::factory()->active()->fixed()->create(['code' => 'HELLO']);
    $customer = Customer::query()->create(['name' => 'Ada']);

    expect($customer->hasRedeemed('HELLO'))->toBeFalse();

    $customer->redeemCoupon('HELLO', Money::ofMinor(1000, 'USD'));

    expect($customer->hasRedeemed('HELLO'))->toBeTrue()
        ->and($customer->hasRedeemed('OTHER'))->toBeFalse();
});

it('reports no redemption when tracking is disabled', function (): void {
    config()->set('coupons.redeemer.track', false);

    Coupon::factory()->active()->fixed()->create(['code' => 'UNTRACKED']);
    $customer = Customer::query()->create(['name' => 'Ada']);

    $customer->redeemCoupon('UNTRACKED', Money::ofMinor(1000, 'USD'));

    expect($customer->hasRedeemed('UNTRACKED'))->toBeFalse();
});
