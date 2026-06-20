<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Coupons\Enums\RedemptionFailureReason;
use RoundlyConsulting\Coupons\Events\CouponExhausted;
use RoundlyConsulting\Coupons\Events\CouponRedeemed;
use RoundlyConsulting\Coupons\Events\CouponRedemptionFailed;
use RoundlyConsulting\Coupons\Exceptions\CouponException;
use RoundlyConsulting\Coupons\Facades\Coupons;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\Tests\Fixtures\Customer;
use RoundlyConsulting\Coupons\ValueObjects\Money;

function attemptRedeem(string $code, Money $price, ?Customer $redeemer = null): void
{
    try {
        Coupons::redeem($code, $price, $redeemer);
    } catch (CouponException) {
        // Swallow so the test can assert the dispatched failure event.
    }
}

it('dispatches a failure event for each rejected reason', function (RedemptionFailureReason $reason, Closure $make): void {
    Event::fake();

    $coupon = $make();
    $customer = Customer::query()->create(['name' => 'Ada']);
    $code = $coupon?->code ?? 'GHOST';

    attemptRedeem($code, new Money(1000, 'USD'), $customer);

    Event::assertDispatched(
        CouponRedemptionFailed::class,
        fn (CouponRedemptionFailed $event): bool => $event->reason === $reason
            && $event->code === $code
            && $event->redeemer?->is($customer) === true,
    );
})->with([
    'not found' => [RedemptionFailureReason::NotFound, fn () => null],
    'expired' => [RedemptionFailureReason::Expired, fn () => Coupon::factory()->active()->expired()->create()],
    'at max usage' => [RedemptionFailureReason::AtMaxUsage, fn () => Coupon::factory()->active()->create(['max_usage' => 1, 'usage' => 1])],
    'currency mismatch' => [RedemptionFailureReason::CurrencyMismatch, fn () => Coupon::factory()->active()->fixed()->forCurrency('EUR')->create()],
    'minimum spend' => [RedemptionFailureReason::MinimumSpendNotMet, fn () => Coupon::factory()->active()->fixed()->withMinimumSpend(5000)->create()],
]);

it('dispatches already-redeemed failure for a capped redeemer', function (): void {
    $coupon = Coupon::factory()->active()->fixed()->create(['max_usage_per_redeemer' => 1]);
    $customer = Customer::query()->create(['name' => 'Ada']);
    Coupons::redeem($coupon->code, new Money(1000, 'USD'), $customer);

    Event::fake();
    attemptRedeem($coupon->code, new Money(1000, 'USD'), $customer);

    Event::assertDispatched(
        CouponRedemptionFailed::class,
        fn (CouponRedemptionFailed $event): bool => $event->reason === RedemptionFailureReason::AlreadyRedeemed,
    );
});

it('still throws after dispatching the failure event', function (): void {
    Event::fake();
    Coupon::factory()->active()->expired()->create(['code' => 'OLD']);

    Coupons::redeem('OLD', new Money(1000, 'USD'));
})->throws(CouponException::class);

it('dispatches exhausted exactly once on the cap-reaching redemption', function (): void {
    Event::fake();
    $coupon = Coupon::factory()->active()->fixed()->create(['max_usage' => 2]);

    Coupons::redeem($coupon->code, new Money(1000, 'USD'));
    Event::assertNotDispatched(CouponExhausted::class);

    Coupons::redeem($coupon->code, new Money(1000, 'USD'));
    Event::assertDispatched(CouponExhausted::class, 1);
});

it('does not dispatch exhausted for an unlimited coupon', function (): void {
    Event::fake();
    $coupon = Coupon::factory()->active()->fixed()->create(['max_usage' => 0]);

    Coupons::redeem($coupon->code, new Money(1000, 'USD'));

    Event::assertNotDispatched(CouponExhausted::class);
});

it('does not dispatch a second exhausted on the rejected follow-up', function (): void {
    $coupon = Coupon::factory()->active()->fixed()->create(['max_usage' => 1]);
    Coupons::redeem($coupon->code, new Money(1000, 'USD'));

    Event::fake();
    attemptRedeem($coupon->code, new Money(1000, 'USD'));

    Event::assertNotDispatched(CouponExhausted::class);
    Event::assertDispatched(
        CouponRedemptionFailed::class,
        fn (CouponRedemptionFailed $event): bool => $event->reason === RedemptionFailureReason::AtMaxUsage,
    );
});

it('dispatches redeemed before exhausted on the final use', function (): void {
    $dispatched = [];
    Event::listen(CouponRedeemed::class, function () use (&$dispatched): void {
        $dispatched[] = CouponRedeemed::class;
    });
    Event::listen(CouponExhausted::class, function () use (&$dispatched): void {
        $dispatched[] = CouponExhausted::class;
    });

    $coupon = Coupon::factory()->active()->fixed()->create(['max_usage' => 1]);
    Coupons::redeem($coupon->code, new Money(1000, 'USD'));

    expect($dispatched)->toBe([CouponRedeemed::class, CouponExhausted::class]);
});
