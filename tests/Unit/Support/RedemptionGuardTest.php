<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\Enums\RedemptionFailureReason;
use RoundlyConsulting\Coupons\Exceptions\CouponAlreadyRedeemed;
use RoundlyConsulting\Coupons\Exceptions\CouponAtMaxUsage;
use RoundlyConsulting\Coupons\Exceptions\CouponExpired;
use RoundlyConsulting\Coupons\Exceptions\CouponNotFound;
use RoundlyConsulting\Coupons\Exceptions\CurrencyMismatch;
use RoundlyConsulting\Coupons\Exceptions\MinimumSpendNotMet;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\Support\RedemptionGuard;
use RoundlyConsulting\Coupons\Tests\Fixtures\Customer;
use RoundlyConsulting\Money\Money;

beforeEach(function (): void {
    $this->guard = app(RedemptionGuard::class);
});

it('returns null for a redeemable coupon with and without redeemer and price', function (): void {
    $coupon = Coupon::factory()->active()->fixed()->create();
    $customer = Customer::query()->create(['name' => 'Ada']);
    $price = Money::ofMinor(1000, 'USD');

    expect($this->guard->firstFailure($coupon, $price, $customer))->toBeNull()
        ->and($this->guard->firstFailure($coupon, $price, null))->toBeNull()
        ->and($this->guard->firstFailure($coupon, null, $customer))->toBeNull()
        ->and($this->guard->firstFailure($coupon, null, null))->toBeNull();
});

it('returns currency mismatch when the coupon is locked to another currency', function (): void {
    $coupon = Coupon::factory()->active()->fixed()->forCurrency('EUR')->create();

    expect($this->guard->firstFailure($coupon, Money::ofMinor(1000, 'USD'), null))
        ->toBe(RedemptionFailureReason::CurrencyMismatch);
});

it('returns minimum spend not met when the price is below the threshold', function (): void {
    $coupon = Coupon::factory()->active()->fixed()->withMinimumSpend(Money::ofMinor(2000, 'USD'))->create();

    expect($this->guard->firstFailure($coupon, Money::ofMinor(1000, 'USD'), null))
        ->toBe(RedemptionFailureReason::MinimumSpendNotMet);
});

it('returns expired for an inactive or expired coupon', function (): void {
    $expired = Coupon::factory()->active()->expired()->create();
    $inactive = Coupon::factory()->create(['activated_at' => null]);

    expect($this->guard->firstFailure($expired, Money::ofMinor(1000, 'USD'), null))
        ->toBe(RedemptionFailureReason::Expired)
        ->and($this->guard->firstFailure($inactive, Money::ofMinor(1000, 'USD'), null))
        ->toBe(RedemptionFailureReason::Expired);
});

it('returns at max usage when the global cap is reached', function (): void {
    $coupon = Coupon::factory()->active()->create(['max_usage' => 2, 'usage' => 2]);

    expect($this->guard->firstFailure($coupon, Money::ofMinor(1000, 'USD'), null))
        ->toBe(RedemptionFailureReason::AtMaxUsage);
});

it('returns already redeemed when the per-redeemer cap is reached', function (): void {
    $coupon = Coupon::factory()->active()->create(['max_usage_per_redeemer' => 1]);
    $customer = Customer::query()->create(['name' => 'Ada']);

    $coupon->redemptions()->create([
        'redeemer_type' => $customer->getMorphClass(),
        'redeemer_id' => $customer->getKey(),
        'currency' => 'USD',
        'amount_discounted' => Money::ofMinor(100, 'USD'),
    ]);

    expect($this->guard->firstFailure($coupon, Money::ofMinor(1000, 'USD'), $customer))
        ->toBe(RedemptionFailureReason::AlreadyRedeemed);
});

it('honours precedence, returning the earlier reason when two checks fail', function (): void {
    // Currency mismatch (1st) and expired (3rd) both fail; currency wins.
    $coupon = Coupon::factory()->expired()->forCurrency('EUR')->create();

    expect($this->guard->firstFailure($coupon, Money::ofMinor(1000, 'USD'), null))
        ->toBe(RedemptionFailureReason::CurrencyMismatch);
});

it('skips currency and minimum spend checks when price is null', function (): void {
    $coupon = Coupon::factory()->active()->fixed()->forCurrency('EUR')->withMinimumSpend(Money::ofMinor(9999, 'EUR'))->create();

    expect($this->guard->firstFailure($coupon, null, null))->toBeNull();
});

it('still catches time and usage failures when price is null', function (): void {
    $expired = Coupon::factory()->active()->expired()->create();
    $exhausted = Coupon::factory()->active()->create(['max_usage' => 1, 'usage' => 1]);

    expect($this->guard->firstFailure($expired, null, null))
        ->toBe(RedemptionFailureReason::Expired)
        ->and($this->guard->firstFailure($exhausted, null, null))
        ->toBe(RedemptionFailureReason::AtMaxUsage);
});

it('throws the exact mapped exception for each reason', function (): void {
    $coupon = Coupon::factory()->active()->fixed()->create();
    $price = Money::ofMinor(1000, 'USD');

    expect(fn () => $this->guard->throwFor($coupon, RedemptionFailureReason::CurrencyMismatch, $price))
        ->toThrow(CurrencyMismatch::class)
        ->and(fn () => $this->guard->throwFor($coupon, RedemptionFailureReason::MinimumSpendNotMet, $price))
        ->toThrow(MinimumSpendNotMet::class)
        ->and(fn () => $this->guard->throwFor($coupon, RedemptionFailureReason::Expired, $price))
        ->toThrow(CouponExpired::class)
        ->and(fn () => $this->guard->throwFor($coupon, RedemptionFailureReason::AtMaxUsage, $price))
        ->toThrow(CouponAtMaxUsage::class)
        ->and(fn () => $this->guard->throwFor($coupon, RedemptionFailureReason::AlreadyRedeemed, $price))
        ->toThrow(CouponAlreadyRedeemed::class);
});

it('reports a soft-deleted coupon as not found before any other reason', function (): void {
    $coupon = Coupon::factory()->active()->expired()->fixed(500, 'EUR')->create(['code' => 'GONE']);
    $coupon->delete();

    expect($this->guard->firstFailure($coupon, Money::ofMinor(1000, 'USD'), null))->toBe(RedemptionFailureReason::NotFound)
        ->and(fn () => $this->guard->throwFor($coupon, RedemptionFailureReason::NotFound, Money::ofMinor(1000, 'USD')))
        ->toThrow(CouponNotFound::class, 'No coupon found for code [GONE].');
});

it('renders the minimum spend as exponent-correct money in the exception message', function (): void {
    $coupon = Coupon::factory()->active()->withMinimumSpend(Money::ofMinor(5000, 'EUR'))->create(['code' => 'FIFTY']);
    $yen = Coupon::factory()->active()->withMinimumSpend(Money::ofMinor(5000, 'JPY'))->create(['code' => 'YEN']);

    expect(fn () => $this->guard->throwFor($coupon, RedemptionFailureReason::MinimumSpendNotMet, Money::ofMinor(100, 'EUR')))
        ->toThrow(MinimumSpendNotMet::class, 'Coupon [FIFTY] requires a minimum spend of 50.00 EUR.')
        ->and(fn () => $this->guard->throwFor($yen, RedemptionFailureReason::MinimumSpendNotMet, Money::ofMinor(100, 'JPY')))
        ->toThrow(MinimumSpendNotMet::class, 'requires a minimum spend of 5000 JPY.');
});

it('names both currencies in the currency-mismatch exception', function (): void {
    $coupon = Coupon::factory()->active()->percentage(1000)->forCurrency('EUR')->create(['code' => 'EURO']);

    expect(fn () => $this->guard->throwFor($coupon, RedemptionFailureReason::CurrencyMismatch, Money::ofMinor(100, 'USD')))
        ->toThrow(CurrencyMismatch::class, 'Coupon [EURO] is locked to EUR and cannot apply to USD.');
});
