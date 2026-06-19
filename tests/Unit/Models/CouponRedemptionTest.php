<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\Models\CouponRedemption;
use RoundlyConsulting\Coupons\Tests\Fixtures\Customer;
use RoundlyConsulting\Coupons\ValueObjects\Money;

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
        'amount_discounted' => 750,
        'currency' => 'eur',
    ]);

    expect($redemption->discount())->toBeInstanceOf(Money::class)
        ->getAmount()->toBe(750)
        ->getCurrency()->toBe('EUR');
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
