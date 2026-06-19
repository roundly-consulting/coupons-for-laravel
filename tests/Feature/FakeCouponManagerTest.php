<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Coupons\Facades\Coupons;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\Testing\FakeCouponManager;
use RoundlyConsulting\Coupons\ValueObjects\Money;

it('swaps the binding and records without touching the database', function (): void {
    $fake = Coupons::fake();

    expect($fake)->toBeInstanceOf(FakeCouponManager::class);

    Coupons::generate(DiscountType::Fixed, 500, 'FAKED');
    Coupons::redeem('FAKED', new Money(5000, 'EUR'));

    expect(Coupon::query()->count())->toBe(0);

    $fake->assertCreated();
    $fake->assertRedeemed();
    $fake->assertCreated(fn (Coupon $c): bool => $c->code === 'FAKED');
    $fake->assertRedeemed(fn ($result): bool => $result->coupon->code === 'FAKED');
});

it('asserts nothing was redeemed', function (): void {
    Coupons::fake()->assertNothingRedeemed();
});

it('fails when asserting a redemption that did not happen', function (): void {
    Coupons::fake()->assertRedeemed();
})->throws(AssertionFailedError::class);

it('generates an auto code on the fake', function (): void {
    $fake = Coupons::fake();

    $first = Coupons::generate(DiscountType::Fixed, 500);

    expect($first->code)->toBe('FAKE-1');
    $fake->assertCreated();
});

it('fails when asserting a created coupon that did not match', function (): void {
    $fake = Coupons::fake();
    Coupons::generate(DiscountType::Fixed, 500, 'A');

    $fake->assertCreated(fn (Coupon $c): bool => $c->code === 'B');
})->throws(AssertionFailedError::class);
