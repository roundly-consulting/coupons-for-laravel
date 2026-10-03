<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\Exceptions\CouponAlreadyRedeemed;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\Models\CouponRedemption;
use RoundlyConsulting\Coupons\Tests\Fixtures\Customer;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

it('enforces a one-per-customer cap', function (): void {
    $coupon = Coupon::factory()->fixed(500, 'EUR')->active()->create(['max_usage_per_redeemer' => 1]);
    $ada = Customer::query()->create(['name' => 'Ada']);
    $lin = Customer::query()->create(['name' => 'Lin']);

    $coupon->redeemBy($ada, Money::ofMinor(5000, 'EUR'));

    expect(fn () => $coupon->redeemBy($ada, Money::ofMinor(5000, 'EUR')))
        ->toThrow(CouponAlreadyRedeemed::class);

    $coupon->redeemBy($lin, Money::ofMinor(5000, 'EUR'));

    expect($coupon->usageBy($ada))->toBe(1)
        ->and($coupon->usageBy($lin))->toBe(1)
        ->and($coupon->fresh()->usage)->toBe(2);
});

it('removes redemptions when the coupon is deleted', function (): void {
    $coupon = Coupon::factory()->fixed(500, 'EUR')->active()->create();
    $coupon->redeemBy(Customer::query()->create(['name' => 'Ada']), Money::ofMinor(5000, 'EUR'));

    $coupon->forceDelete();

    expect(CouponRedemption::withTrashed()->count())->toBe(0);
});

// Regression: `coupons.redeemer.track` had to be the exact bool `true`. The env yields strings,
// so COUPONS_TRACK_REDEEMERS=1 silently switched tracking off — and "one per customer" with it.
it('reads redeemer tracking from env-style values', function (mixed $track, bool $tracked): void {
    config()->set('coupons.redeemer.track', $track);
    $coupon = Coupon::factory()->fixed(500, 'EUR')->active()->create(['code' => 'ONCE', 'max_usage_per_redeemer' => 1]);
    $ada = Customer::query()->create(['name' => 'Ada']);

    $coupon->redeemBy($ada, Money::ofMinor(5000, 'EUR'));

    expect($coupon->redemptions()->count())->toBe($tracked ? 1 : 0)
        ->and($coupon->remainingUsageFor($ada))->toBe($tracked ? 0 : null);

    if ($tracked) {
        expect(fn () => $coupon->redeemBy($ada, Money::ofMinor(5000, 'EUR')))->toThrow(CouponAlreadyRedeemed::class);
    }

    $this->artisan('about', ['--only' => 'coupons'])
        ->expectsOutputToContain($tracked ? 'ON' : 'OFF')
        ->assertSuccessful();
})->with([
    'string 1' => ['1', true],
    'string true' => ['true', true],
    'string on' => ['on', true],
    'string yes' => ['yes', true],
    'int 1' => [1, true],
    'bool true' => [true, true],
    'string 0' => ['0', false],
    'string false' => ['false', false],
    'string off' => ['off', false],
    'int 0' => [0, false],
    'bool false' => [false, false],
]);

it('refuses a mistyped redeemer tracking switch (strict config)', function (): void {
    config()->set('coupons.redeemer.track', 'disabled');
    $coupon = Coupon::factory()->fixed(500, 'EUR')->active()->create(['code' => 'ONCE', 'max_usage_per_redeemer' => 1]);

    expect(fn () => $coupon->redeemBy(Customer::query()->create(['name' => 'Ada']), Money::ofMinor(5000, 'EUR')))
        ->toThrow(
            InvalidConfigurationException::class,
            'Configuration value [coupons.redeemer.track] must be a boolean (true/false, 1/0, on/off or yes/no), [disabled] given.',
        )
        ->and($coupon->redemptions()->count())->toBe(0);
});
