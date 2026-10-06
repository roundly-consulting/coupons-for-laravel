<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Coupons\Actions\CreateCouponAction;
use RoundlyConsulting\Coupons\DataTransferObjects\CreateCouponData;
use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Coupons\Enums\RedemptionFailureReason;
use RoundlyConsulting\Coupons\Exceptions\CouponExpired;
use RoundlyConsulting\Coupons\Facades\Coupons;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Money\Money;

it('creates, persists, and applies a coupon end to end', function (): void {
    $coupon = app(CreateCouponAction::class)->execute(
        new CreateCouponData(type: DiscountType::Percentage, value: 2000, code: 'SAVE20'),
    );

    $coupon->activate()->save();

    $fresh = Coupon::query()->where('code', 'SAVE20')->firstOrFail();

    expect($fresh->canBeApplied())->toBeTrue()
        ->and($fresh->apply(Money::ofMinor(5000, 'EUR'))->minor())->toBe('4000');
});

it('exposes the config handle with a default model', function (): void {
    expect(config('coupons.model'))->toBe(Coupon::class);
});

// Regression: isActive()/isExpired() compared strictly (`< now`) while the scopes use `<= now`.
// At the stored instant — a frozen clock, or the instance a revoke just wrote — redeemable()
// listed a coupon redemption refused as expired, and a just-revoked coupon still redeemed.
it('agrees with the scopes at the exact activation and expiry instant', function (): void {
    Carbon::setTestNow('2026-01-01 12:00:00');
    $cart = Money::ofMinor(5000, 'EUR');

    Coupons::generate(DiscountType::Percentage, 1000, 'INSTANT')->activate()->save();

    expect(Coupons::redeemable()->pluck('code')->all())->toBe(['INSTANT'])
        ->and(Coupons::redeem('INSTANT', $cart)->discount->minor())->toBe('500');

    $revoked = Coupons::revoke('INSTANT');

    expect(Coupon::query()->expired()->pluck('code')->all())->toBe(['INSTANT'])
        ->and(Coupons::check($revoked))->toBe(RedemptionFailureReason::Expired)
        ->and(fn () => Coupons::redeem('INSTANT', $cart))->toThrow(CouponExpired::class);

    Carbon::setTestNow();
});
