<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Coupons\Actions\RedeemCouponAction;
use RoundlyConsulting\Coupons\DataTransferObjects\RedeemCouponData;
use RoundlyConsulting\Coupons\Events\CouponRedeemed;
use RoundlyConsulting\Coupons\Exceptions\CouponAlreadyRedeemed;
use RoundlyConsulting\Coupons\Exceptions\CouponAtMaxUsage;
use RoundlyConsulting\Coupons\Exceptions\CouponExpired;
use RoundlyConsulting\Coupons\Exceptions\CouponNotFound;
use RoundlyConsulting\Coupons\Exceptions\CurrencyMismatch;
use RoundlyConsulting\Coupons\Exceptions\MinimumSpendNotMet;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\Models\CouponRedemption;
use RoundlyConsulting\Coupons\Tests\Fixtures\Customer;
use RoundlyConsulting\Coupons\ValueObjects\Money;

function redeem(Coupon|string $coupon, Money $price, ?Customer $redeemer = null): mixed
{
    return app(RedeemCouponAction::class)->execute(
        new RedeemCouponData(coupon: $coupon, price: $price, redeemer: $redeemer),
    );
}

it('redeems a percentage coupon by code and increments usage atomically', function (): void {
    $coupon = Coupon::factory()->percentage(20)->active()->create(['code' => 'SAVE20']);

    $result = redeem('SAVE20', new Money(5000, 'EUR'));

    expect($result->discount->getAmount())->toBe(1000)
        ->and($result->total->getAmount())->toBe(4000)
        ->and($result->coupon->usage)->toBe(1)
        ->and($result->freeShipping)->toBeFalse()
        ->and($coupon->fresh()->usage)->toBe(1);
});

it('redeems a fixed coupon by model', function (): void {
    $coupon = Coupon::factory()->fixed(750)->active()->create();

    $result = redeem($coupon, new Money(5000, 'EUR'));

    expect($result->discount->getAmount())->toBe(750)
        ->and($result->total->getAmount())->toBe(4250);
});

it('redeems a capped percentage coupon', function (): void {
    $coupon = Coupon::factory()->cappedPercentage(50, 300)->active()->create();

    expect(redeem($coupon, new Money(5000, 'EUR'))->discount->getAmount())->toBe(300);
});

it('redeems a free-shipping coupon and reports the intent', function (): void {
    $coupon = Coupon::factory()->freeShipping()->active()->create();

    $result = redeem($coupon, new Money(5000, 'EUR'));

    expect($result->discount->getAmount())->toBe(0)
        ->and($result->total->getAmount())->toBe(5000)
        ->and($result->freeShipping)->toBeTrue();
});

it('redeems a currency-locked coupon when the currency matches', function (): void {
    $coupon = Coupon::factory()->fixed(500)->forCurrency('EUR')->active()->create();

    expect(redeem($coupon, new Money(5000, 'EUR'))->total->getAmount())->toBe(4500);
});

it('dispatches CouponRedeemed once on success', function (): void {
    Event::fake();
    $coupon = Coupon::factory()->fixed(500)->active()->create();

    redeem($coupon, new Money(5000, 'EUR'));

    Event::assertDispatched(CouponRedeemed::class, 1);
});

it('records a redemption row for a redeemer', function (): void {
    $coupon = Coupon::factory()->fixed(500)->active()->create();
    $customer = Customer::query()->create(['name' => 'Ada']);

    redeem($coupon, new Money(5000, 'EUR'), $customer);

    expect(CouponRedemption::query()->count())->toBe(1)
        ->and($coupon->usageBy($customer))->toBe(1);
});

it('does not record a row when tracking is disabled', function (): void {
    config()->set('coupons.redeemer.track', false);
    $coupon = Coupon::factory()->fixed(500)->active()->create();
    $customer = Customer::query()->create(['name' => 'Ada']);

    redeem($coupon, new Money(5000, 'EUR'), $customer);

    expect(CouponRedemption::query()->count())->toBe(0)
        ->and($coupon->fresh()->usage)->toBe(1);
});

it('throws when the code is unknown', function (): void {
    redeem('NOPE', new Money(5000, 'EUR'));
})->throws(CouponNotFound::class);

it('throws when the coupon is inactive or expired', function (): void {
    $coupon = Coupon::factory()->fixed(500)->create();

    expect(fn () => redeem($coupon, new Money(5000, 'EUR')))->toThrow(CouponExpired::class)
        ->and($coupon->fresh()->usage)->toBe(0);
});

it('throws when the coupon is at its global max usage', function (): void {
    $coupon = Coupon::factory()->fixed(500)->active()->create(['usage' => 2, 'max_usage' => 2]);

    expect(fn () => redeem($coupon, new Money(5000, 'EUR')))->toThrow(CouponAtMaxUsage::class)
        ->and($coupon->fresh()->usage)->toBe(2);
});

it('throws when the redeemer has reached their cap', function (): void {
    $coupon = Coupon::factory()->fixed(500)->active()->create(['max_usage_per_redeemer' => 1]);
    $customer = Customer::query()->create(['name' => 'Ada']);

    redeem($coupon, new Money(5000, 'EUR'), $customer);

    expect(fn () => redeem($coupon, new Money(5000, 'EUR'), $customer))
        ->toThrow(CouponAlreadyRedeemed::class)
        ->and($coupon->fresh()->usage)->toBe(1);
});

it('allows a different redeemer past a per-redeemer cap', function (): void {
    $coupon = Coupon::factory()->fixed(500)->active()->create(['max_usage_per_redeemer' => 1]);
    $ada = Customer::query()->create(['name' => 'Ada']);
    $lin = Customer::query()->create(['name' => 'Lin']);

    redeem($coupon, new Money(5000, 'EUR'), $ada);
    redeem($coupon, new Money(5000, 'EUR'), $lin);

    expect($coupon->fresh()->usage)->toBe(2);
});

it('throws when the price is below the minimum spend', function (): void {
    $coupon = Coupon::factory()->fixed(500)->withMinimumSpend(10000)->active()->create();

    expect(fn () => redeem($coupon, new Money(5000, 'EUR')))->toThrow(MinimumSpendNotMet::class)
        ->and($coupon->fresh()->usage)->toBe(0);
});

it('throws when the currency does not match the lock', function (): void {
    $coupon = Coupon::factory()->fixed(500)->forCurrency('EUR')->active()->create();

    expect(fn () => redeem($coupon, new Money(5000, 'USD')))->toThrow(CurrencyMismatch::class)
        ->and($coupon->fresh()->usage)->toBe(0);
});

it('never lets concurrent redemptions exceed the global cap', function (): void {
    $coupon = Coupon::factory()->fixed(500)->active()->create(['max_usage' => 1]);

    redeem($coupon, new Money(5000, 'EUR'));

    expect(fn () => redeem($coupon, new Money(5000, 'EUR')))->toThrow(CouponAtMaxUsage::class)
        ->and($coupon->fresh()->usage)->toBe(1);
});

it('allows a guest redemption with no redeemer and skips the per-user cap', function (): void {
    $coupon = Coupon::factory()->fixed(500)->active()->create(['max_usage_per_redeemer' => 1]);

    redeem($coupon, new Money(5000, 'EUR'));
    redeem($coupon, new Money(5000, 'EUR'));

    expect($coupon->fresh()->usage)->toBe(2)
        ->and(CouponRedemption::query()->count())->toBe(0);
});

it('throws when the locked coupon no longer exists', function (): void {
    $coupon = Coupon::factory()->fixed(500)->active()->create(['code' => 'GONE']);
    Coupon::query()->whereKey($coupon->getKey())->forceDelete();

    expect(fn () => redeem($coupon, new Money(5000, 'EUR')))->toThrow(CouponNotFound::class);
});

it('redeems through the model helper', function (): void {
    $coupon = Coupon::factory()->fixed(500)->active()->create();
    $customer = Customer::query()->create(['name' => 'Ada']);

    $result = $coupon->redeemBy($customer, new Money(5000, 'EUR'));

    expect($result->total->getAmount())->toBe(4500)
        ->and($coupon->fresh()->usage)->toBe(1);
});
