<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\Coupons\DataTransferObjects\CreateCouponData;
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

it('asserts a redemption by code', function (): void {
    $fake = Coupons::fake();
    Coupons::redeem('SAVE', new Money(1000, 'USD'));

    $fake->assertRedeemed('SAVE');
    $fake->assertRedeemed('SAVE', fn ($result): bool => $result->coupon->code === 'SAVE');
});

it('fails asserting a redemption for the wrong code', function (): void {
    $fake = Coupons::fake();
    Coupons::redeem('SAVE', new Money(1000, 'USD'));

    $fake->assertRedeemed('OTHER');
})->throws(AssertionFailedError::class);

it('asserts a coupon was not redeemed', function (): void {
    $fake = Coupons::fake();
    Coupons::redeem('SAVE', new Money(1000, 'USD'));

    $fake->assertNotRedeemed('OTHER');
});

it('fails asserting not-redeemed when it was redeemed', function (): void {
    $fake = Coupons::fake();
    Coupons::redeem('SAVE', new Money(1000, 'USD'));

    $fake->assertNotRedeemed('SAVE');
})->throws(AssertionFailedError::class);

it('asserts a redemption failed with and without a reason', function (): void {
    $fake = Coupons::fake();
    $expired = Coupon::factory()->active()->expired()->make(['code' => 'OLD']);

    Coupons::redeem($expired, new Money(1000, 'USD'));

    $fake->assertRedemptionFailed('OLD');
    $fake->assertRedemptionFailed('OLD', 'expired');
});

it('fails asserting a redemption failure that did not happen', function (): void {
    $fake = Coupons::fake();
    Coupons::redeem('SAVE', new Money(1000, 'USD'));

    $fake->assertRedemptionFailed('SAVE');
})->throws(AssertionFailedError::class);

it('does not match a failure recorded for a different code', function (): void {
    $fake = Coupons::fake();
    $expired = Coupon::factory()->active()->expired()->make(['code' => 'OLD']);
    Coupons::redeem($expired, new Money(1000, 'USD'));

    $fake->assertRedemptionFailed('OLD');
    $fake->assertRedemptionFailed('SOMETHING-ELSE');
})->throws(AssertionFailedError::class);

it('supports the legacy callback-only assertRedeemed signature', function (): void {
    $fake = Coupons::fake();
    Coupons::redeem('SAVE', new Money(1000, 'USD'));

    $fake->assertRedeemed(fn ($result): bool => $result->coupon->code === 'SAVE');
});

it('creates quietly on the fake', function (): void {
    $fake = Coupons::fake();

    $coupon = Coupons::createQuietly(new CreateCouponData(DiscountType::Fixed, 100, 'Q'));

    expect($coupon->code)->toBe('Q');
    $fake->assertCreated(fn (Coupon $c): bool => $c->code === 'Q');
});

it('reports existence and an empty redeemable query on the fake', function (): void {
    Coupons::fake();
    Coupons::generate(DiscountType::Fixed, 100, 'EXISTS');

    expect(Coupons::exists('EXISTS'))->toBeTrue()
        ->and(Coupons::exists('NOPE'))->toBeFalse()
        ->and(Coupons::redeemable()->count())->toBe(0);
});

it('revokes a coupon on the fake', function (): void {
    Coupons::fake();

    expect(Coupons::revoke('KILL')->isExpired())->toBeTrue();
});
