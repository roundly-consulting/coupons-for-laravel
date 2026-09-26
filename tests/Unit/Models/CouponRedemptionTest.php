<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\Models\CouponRedemption;
use RoundlyConsulting\Coupons\Tests\Fixtures\Customer;
use RoundlyConsulting\Money\Money;

it('belongs to a coupon', function (): void {
    $coupon = Coupon::factory()->create();
    $redemption = CouponRedemption::factory()->create(['coupon_id' => $coupon->id]);

    expect($redemption->coupon)->toBeInstanceOf(Coupon::class)
        ->id->toBe($coupon->id);
});

it('morphs to a redeemer', function (): void {
    $customer = Customer::query()->create(['name' => 'Ada']);

    $redemption = CouponRedemption::factory()->create([
        'redeemer_type' => $customer->getMorphClass(),
        'redeemer_id' => $customer->getKey(),
    ]);

    expect($redemption->redeemer)->toBeInstanceOf(Customer::class)
        ->id->toBe($customer->id);
});

it('exposes the discount as money', function (): void {
    $redemption = CouponRedemption::factory()->create([
        'currency' => 'EUR',
        'amount_discounted' => Money::ofMinor(750, 'EUR'),
    ]);

    $fresh = $redemption->fresh();

    expect($fresh?->discount())->toBeInstanceOf(Money::class)
        ->and($fresh?->discount()->minor())->toBe('750')
        ->and($fresh?->discount()->currency()->code)->toBe('EUR');
});

it('writes the redemption currency from the discounted amount', function (): void {
    $redemption = CouponRedemption::factory()->create([
        'currency' => null,
        'amount_discounted' => Money::ofMinor(500, 'JPY'),
    ]);

    expect($redemption->fresh()?->currency)->toBe('JPY')
        ->and((string) $redemption->fresh()?->discount())->toBe('500 JPY');
});

it('soft deletes redemptions', function (): void {
    $redemption = CouponRedemption::factory()->create();

    $redemption->delete();

    expect(CouponRedemption::query()->count())->toBe(0)
        ->and(CouponRedemption::withTrashed()->count())->toBe(1);
});

it('is cascade deleted with its coupon', function (): void {
    $coupon = Coupon::factory()->create();
    CouponRedemption::factory()->create(['coupon_id' => $coupon->id]);

    $coupon->forceDelete();

    expect(CouponRedemption::withTrashed()->count())->toBe(0);
});
