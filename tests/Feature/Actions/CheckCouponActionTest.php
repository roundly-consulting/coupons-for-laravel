<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\Actions\CheckCouponAction;
use RoundlyConsulting\Coupons\Enums\RedemptionFailureReason;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\Tests\Fixtures\Customer;
use RoundlyConsulting\Money\Money;

beforeEach(function (): void {
    $this->check = app(CheckCouponAction::class);
});

it('returns null for a redeemable coupon, by code or model', function (): void {
    $coupon = Coupon::factory()->active()->fixed(500, 'EUR')->create(['code' => 'OK']);

    expect($this->check->execute('OK', Money::ofMinor(5000, 'EUR')))->toBeNull()
        ->and($this->check->execute($coupon))->toBeNull();
});

it('reports an unknown or empty code as not found', function (): void {
    expect($this->check->execute('NOPE'))->toBe(RedemptionFailureReason::NotFound)
        ->and($this->check->execute(''))->toBe(RedemptionFailureReason::NotFound);
});

it('reports the first failing reason in redemption order', function (): void {
    Coupon::factory()->active()->expired()->fixed(500, 'EUR')->create(['code' => 'OLD']);

    // A currency mismatch outranks the expiry, exactly as redemption would report it.
    expect($this->check->execute('OLD', Money::ofMinor(5000, 'USD')))->toBe(RedemptionFailureReason::CurrencyMismatch)
        ->and($this->check->execute('OLD', Money::ofMinor(5000, 'EUR')))->toBe(RedemptionFailureReason::Expired)
        ->and($this->check->execute('OLD'))->toBe(RedemptionFailureReason::Expired);
});

it('checks the per-redeemer cap only when given a redeemer', function (): void {
    $coupon = Coupon::factory()->active()->create(['code' => 'ONCE', 'max_usage_per_redeemer' => 1]);
    $customer = Customer::query()->create(['name' => 'Ada']);
    $coupon->redemptions()->create([
        'redeemer_type' => $customer->getMorphClass(),
        'redeemer_id' => $customer->getKey(),
        'amount_discounted' => Money::ofMinor(100, 'USD'),
    ]);

    expect($this->check->execute('ONCE', redeemer: $customer))->toBe(RedemptionFailureReason::AlreadyRedeemed)
        ->and($this->check->execute('ONCE'))->toBeNull();
});

it('never writes', function (): void {
    $coupon = Coupon::factory()->active()->fixed(500, 'EUR')->create(['code' => 'OK']);

    $this->check->execute('OK', Money::ofMinor(5000, 'EUR'));

    expect($coupon->fresh()->usage)->toBe(0);
});
