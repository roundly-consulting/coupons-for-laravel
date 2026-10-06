<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Coupons\Facades\Coupons;
use RoundlyConsulting\Coupons\Tests\Fixtures\OtherConnectionCustomer;
use RoundlyConsulting\Money\Money;

/**
 * A redeemer model on its own connection, coupons on the default one. The redemption row is
 * written beside its coupon, so the redeemer's side of the relation has to read there too —
 * it used to inherit the redeemer's connection, where `coupon_redemptions` does not exist.
 */
beforeEach(function (): void {
    config()->set('database.connections.users_other', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);

    $this->artisan('migrate', [
        '--database' => 'users_other',
        '--path' => realpath(__DIR__.'/../database/migrations'),
        '--realpath' => true,
    ])->assertSuccessful();
});

it('reads a redeemer on another connection from the coupon connection', function (): void {
    Coupons::generate(DiscountType::Percentage, 1000, code: 'HOME')->activate()->save();
    $customer = OtherConnectionCustomer::query()->create(['name' => 'Ada']);

    $customer->redeemCoupon('HOME', Money::ofMinor(5000, 'EUR'));

    expect($customer->hasRedeemed('HOME'))->toBeTrue()
        ->and($customer->couponRedemptions()->count())->toBe(1)
        ->and($customer->couponRedemptions()->sole()->redeemer?->is($customer))->toBeTrue()
        ->and(DB::table('coupon_redemptions')->count())->toBe(1);
});
