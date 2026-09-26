<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Coupons\DataTransferObjects\CreateCouponData;
use RoundlyConsulting\Coupons\Facades\Coupons;
use RoundlyConsulting\Coupons\Models\CouponRedemption;
use RoundlyConsulting\Coupons\Tests\Fixtures\UuidCustomer;
use RoundlyConsulting\Money\Money;

/**
 * `coupons.key_type = uuid` gives the redemption's morph id a uuid column — the model must
 * read it back as the key it is, not squash it through an integer cast.
 */
beforeEach(function (): void {
    config()->set('coupons.key_type', 'uuid');

    // Re-migrate the package tables under the uuid key type, FK-referencing table first.
    Schema::dropIfExists('coupon_redemptions');
    Schema::dropIfExists('coupons');

    foreach ([
        '2024_01_01_000000_create_coupons_table',
        '2024_01_01_000001_create_coupon_redemptions_table',
    ] as $migration) {
        (require __DIR__.'/../../database/migrations/'.$migration.'.php')->up();
    }

    Schema::create('uuid_customers', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name')->nullable();
    });
});

it('reads a uuid redeemer key back as the key it stored', function (): void {
    $customer = UuidCustomer::query()->create(['name' => 'Ada']);
    $coupon = Coupons::create(CreateCouponData::percentage(10, code: 'TEN'));
    $coupon->activate(now()->subMinute())->save();

    $customer->redeemCoupon($coupon, Money::ofMinor(1000, 'USD'));

    $redemption = CouponRedemption::query()->sole();

    expect($redemption->redeemer_id)->toBe($customer->getKey())
        ->and($redemption->redeemer)->toBeInstanceOf(UuidCustomer::class)
        ->and($redemption->redeemer?->is($customer))->toBeTrue()
        ->and($customer->couponRedemptions()->count())->toBe(1)
        ->and($customer->hasRedeemed('TEN'))->toBeTrue();
});
