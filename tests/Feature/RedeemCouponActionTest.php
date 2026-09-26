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
use RoundlyConsulting\Money\Money;

function redeem(Coupon|string $coupon, Money $price, ?Customer $redeemer = null): mixed
{
    return app(RedeemCouponAction::class)->execute(
        new RedeemCouponData(coupon: $coupon, price: $price, redeemer: $redeemer),
    );
}

it('redeems a percentage coupon by code and increments usage atomically', function (): void {
    $coupon = Coupon::factory()->percentage(2000)->active()->create(['code' => 'SAVE20']);

    $result = redeem('SAVE20', Money::ofMinor(5000, 'EUR'));

    expect($result->discount->minor())->toBe('1000')
        ->and($result->total->minor())->toBe('4000')
        ->and($result->coupon->usage)->toBe(1)
        ->and($result->freeShipping)->toBeFalse()
        ->and($coupon->fresh()->usage)->toBe(1);
});

it('redeems a fixed coupon by model', function (): void {
    $coupon = Coupon::factory()->fixed(750, 'EUR')->active()->create();

    $result = redeem($coupon, Money::ofMinor(5000, 'EUR'));

    expect($result->discount->minor())->toBe('750')
        ->and($result->total->minor())->toBe('4250');
});

it('redeems a capped percentage coupon', function (): void {
    $coupon = Coupon::factory()->cappedPercentage(5000, Money::ofMinor(300, 'EUR'))->active()->create();

    expect(redeem($coupon, Money::ofMinor(5000, 'EUR'))->discount->minor())->toBe('300');
});

it('redeems a free-shipping coupon and reports the intent', function (): void {
    $coupon = Coupon::factory()->freeShipping()->active()->create();

    $result = redeem($coupon, Money::ofMinor(5000, 'EUR'));

    expect($result->discount->minor())->toBe('0')
        ->and($result->total->minor())->toBe('5000')
        ->and($result->freeShipping)->toBeTrue();
});

it('redeems a currency-locked coupon when the currency matches', function (): void {
    $coupon = Coupon::factory()->fixed(500, 'EUR')->forCurrency('EUR')->active()->create();

    expect(redeem($coupon, Money::ofMinor(5000, 'EUR'))->total->minor())->toBe('4500');
});

it('dispatches CouponRedeemed once on success', function (): void {
    Event::fake();
    $coupon = Coupon::factory()->fixed(500, 'EUR')->active()->create();

    redeem($coupon, Money::ofMinor(5000, 'EUR'));

    Event::assertDispatched(CouponRedeemed::class, 1);
});

it('records a redemption row for a redeemer', function (): void {
    $coupon = Coupon::factory()->fixed(500, 'EUR')->active()->create();
    $customer = Customer::query()->create(['name' => 'Ada']);

    redeem($coupon, Money::ofMinor(5000, 'EUR'), $customer);

    expect(CouponRedemption::query()->count())->toBe(1)
        ->and($coupon->usageBy($customer))->toBe(1);
});

it('does not record a row when tracking is disabled', function (): void {
    config()->set('coupons.redeemer.track', false);
    $coupon = Coupon::factory()->fixed(500, 'EUR')->active()->create();
    $customer = Customer::query()->create(['name' => 'Ada']);

    redeem($coupon, Money::ofMinor(5000, 'EUR'), $customer);

    expect(CouponRedemption::query()->count())->toBe(0)
        ->and($coupon->fresh()->usage)->toBe(1);
});

it('throws when the code is unknown', function (): void {
    redeem('NOPE', Money::ofMinor(5000, 'EUR'));
})->throws(CouponNotFound::class);

it('throws when the coupon is inactive or expired', function (): void {
    $coupon = Coupon::factory()->fixed(500, 'EUR')->create();

    expect(fn () => redeem($coupon, Money::ofMinor(5000, 'EUR')))->toThrow(CouponExpired::class)
        ->and($coupon->fresh()->usage)->toBe(0);
});

it('throws when the coupon is at its global max usage', function (): void {
    $coupon = Coupon::factory()->fixed(500, 'EUR')->active()->create(['usage' => 2, 'max_usage' => 2]);

    expect(fn () => redeem($coupon, Money::ofMinor(5000, 'EUR')))->toThrow(CouponAtMaxUsage::class)
        ->and($coupon->fresh()->usage)->toBe(2);
});

it('throws when the redeemer has reached their cap', function (): void {
    $coupon = Coupon::factory()->fixed(500, 'EUR')->active()->create(['max_usage_per_redeemer' => 1]);
    $customer = Customer::query()->create(['name' => 'Ada']);

    redeem($coupon, Money::ofMinor(5000, 'EUR'), $customer);

    expect(fn () => redeem($coupon, Money::ofMinor(5000, 'EUR'), $customer))
        ->toThrow(CouponAlreadyRedeemed::class)
        ->and($coupon->fresh()->usage)->toBe(1);
});

it('allows a different redeemer past a per-redeemer cap', function (): void {
    $coupon = Coupon::factory()->fixed(500, 'EUR')->active()->create(['max_usage_per_redeemer' => 1]);
    $ada = Customer::query()->create(['name' => 'Ada']);
    $lin = Customer::query()->create(['name' => 'Lin']);

    redeem($coupon, Money::ofMinor(5000, 'EUR'), $ada);
    redeem($coupon, Money::ofMinor(5000, 'EUR'), $lin);

    expect($coupon->fresh()->usage)->toBe(2);
});

it('throws when the price is below the minimum spend', function (): void {
    $coupon = Coupon::factory()->fixed(500, 'EUR')->withMinimumSpend(Money::ofMinor(10000, 'EUR'))->active()->create();

    expect(fn () => redeem($coupon, Money::ofMinor(5000, 'EUR')))->toThrow(MinimumSpendNotMet::class)
        ->and($coupon->fresh()->usage)->toBe(0);
});

it('throws when the currency does not match the lock', function (): void {
    $coupon = Coupon::factory()->fixed(500, 'EUR')->forCurrency('EUR')->active()->create();

    expect(fn () => redeem($coupon, Money::ofMinor(5000, 'USD')))->toThrow(CurrencyMismatch::class)
        ->and($coupon->fresh()->usage)->toBe(0);
});

it('never lets concurrent redemptions exceed the global cap', function (): void {
    $coupon = Coupon::factory()->fixed(500, 'EUR')->active()->create(['max_usage' => 1]);

    redeem($coupon, Money::ofMinor(5000, 'EUR'));

    expect(fn () => redeem($coupon, Money::ofMinor(5000, 'EUR')))->toThrow(CouponAtMaxUsage::class)
        ->and($coupon->fresh()->usage)->toBe(1);
});

it('allows a guest redemption with no redeemer and skips the per-user cap', function (): void {
    $coupon = Coupon::factory()->fixed(500, 'EUR')->active()->create(['max_usage_per_redeemer' => 1]);

    redeem($coupon, Money::ofMinor(5000, 'EUR'));
    redeem($coupon, Money::ofMinor(5000, 'EUR'));

    expect($coupon->fresh()->usage)->toBe(2)
        ->and(CouponRedemption::query()->count())->toBe(0);
});

it('throws when the locked coupon no longer exists', function (): void {
    $coupon = Coupon::factory()->fixed(500, 'EUR')->active()->create(['code' => 'GONE']);
    Coupon::query()->whereKey($coupon->getKey())->forceDelete();

    expect(fn () => redeem($coupon, Money::ofMinor(5000, 'EUR')))->toThrow(CouponNotFound::class);
});

it('redeems through the model helper', function (): void {
    $coupon = Coupon::factory()->fixed(500, 'EUR')->active()->create();
    $customer = Customer::query()->create(['name' => 'Ada']);

    $result = $coupon->redeemBy($customer, Money::ofMinor(5000, 'EUR'));

    expect($result->total->minor())->toBe('4500')
        ->and($coupon->fresh()->usage)->toBe(1);
});

it('clamps a fixed discount to the price and records the clamped amount', function (): void {
    $coupon = Coupon::factory()->fixed(5000, 'EUR')->active()->create();
    $customer = Customer::query()->create(['name' => 'Ada']);

    $result = redeem($coupon, Money::ofMinor(1000, 'EUR'), $customer);

    $row = CouponRedemption::query()->sole();

    expect($result->discount->minor())->toBe('1000')
        ->and($result->total->isZero())->toBeTrue()
        ->and($row->discount()->equals(Money::ofMinor(1000, 'EUR')))->toBeTrue()
        ->and($row->currency)->toBe('EUR');
});

it('redeems a yen coupon in whole yen', function (): void {
    $coupon = Coupon::factory()->fixed(300, 'JPY')->active()->create();
    $customer = Customer::query()->create(['name' => 'Ada']);

    $result = redeem($coupon, Money::ofMinor(1200, 'JPY'), $customer);

    expect((string) $result->total)->toBe('900 JPY')
        ->and((string) CouponRedemption::query()->sole()->discount())->toBe('300 JPY');
});

it('redeems a fractional percentage coupon', function (): void {
    $coupon = Coupon::factory()->percentage(1250)->active()->create();

    $result = redeem($coupon, Money::ofMinor(999, 'EUR'));

    expect($result->discount->minor())->toBe('125')
        ->and($result->total->minor())->toBe('874');
});
