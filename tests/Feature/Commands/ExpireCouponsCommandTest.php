<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Coupons\Events\CouponRevoked;
use RoundlyConsulting\Coupons\Facades\Coupons;
use RoundlyConsulting\Coupons\Models\Coupon;

it('expires all active coupons', function (): void {
    $a = Coupon::factory()->active()->create(['code' => 'A']);
    $b = Coupon::factory()->active()->create(['code' => 'B']);

    $this->artisan('coupons:expire')
        ->expectsOutputToContain('Expired 2 coupon(s).')
        ->assertSuccessful();

    expect($a->fresh()->isExpired())->toBeTrue()
        ->and($b->fresh()->isExpired())->toBeTrue();
});

it('expires only the given code', function (): void {
    $a = Coupon::factory()->active()->create(['code' => 'A']);
    $b = Coupon::factory()->active()->create(['code' => 'B']);

    $this->artisan('coupons:expire', ['--code' => 'A'])
        ->expectsOutputToContain('Expired 1 coupon(s).')
        ->assertSuccessful();

    expect($a->fresh()->isExpired())->toBeTrue()
        ->and($b->fresh()->isExpired())->toBeFalse();
});

it('leaves already-expired coupons untouched', function (): void {
    Coupon::factory()->active()->expired()->create(['code' => 'OLD']);

    $this->artisan('coupons:expire')
        ->expectsOutputToContain('Expired 0 coupon(s).')
        ->assertSuccessful();
});

/**
 * The command is the bulk form of `Coupons::revoke()`, so listeners (audit logs, cache
 * busting, notifications) must hear about every coupon it kills — it used to be a raw
 * bulk UPDATE that fired nothing.
 */
it('fires CouponRevoked for every coupon it expires, like a manual revoke', function (): void {
    Event::fake([CouponRevoked::class]);

    $a = Coupon::factory()->active()->create(['code' => 'A']);
    $b = Coupon::factory()->active()->create(['code' => 'B']);
    Coupon::factory()->active()->expired()->create(['code' => 'OLD']);

    $this->artisan('coupons:expire')->assertSuccessful();

    Event::assertDispatchedTimes(CouponRevoked::class, 2);
    Event::assertDispatched(CouponRevoked::class, fn (CouponRevoked $e): bool => $e->coupon->is($a) && $e->coupon->isExpired());
    Event::assertDispatched(CouponRevoked::class, fn (CouponRevoked $e): bool => $e->coupon->is($b) && $e->coupon->isExpired());
});

it('fires CouponRevoked only for the coupon named by --code', function (): void {
    Event::fake([CouponRevoked::class]);

    $a = Coupon::factory()->active()->create(['code' => 'A']);
    Coupon::factory()->active()->create(['code' => 'B']);

    $this->artisan('coupons:expire', ['--code' => 'A'])->assertSuccessful();

    Event::assertDispatchedTimes(CouponRevoked::class, 1);
    Event::assertDispatched(CouponRevoked::class, fn (CouponRevoked $e): bool => $e->coupon->is($a));
});

it('fires nothing when there is nothing left to expire', function (): void {
    Event::fake([CouponRevoked::class]);

    Coupon::factory()->active()->expired()->create(['code' => 'OLD']);

    $this->artisan('coupons:expire')->assertSuccessful();

    Event::assertNotDispatched(CouponRevoked::class);
});

it('expires coupons scheduled to expire later', function (): void {
    $later = Coupon::factory()->active()->create(['code' => 'LATER', 'expires_at' => now()->addWeek()]);

    $this->artisan('coupons:expire')
        ->expectsOutputToContain('Expired 1 coupon(s).')
        ->assertSuccessful();

    expect($later->fresh()->isExpired())->toBeTrue();
});

it('runs through the manager, so the fake records it', function (): void {
    $fake = Coupons::fake();

    $this->artisan('coupons:expire', ['--code' => 'A'])->assertSuccessful();

    $fake->assertExpiredAll();
});

// Regression: a blank --code was read as "no code", so `coupons:expire --code="$CODE"` with an
// empty variable revoked every live coupon and overwrote each one's real expiry. Only an
// absent --code means "all".
it('refuses a blank --code and expires nothing', function (string $command, array $parameters): void {
    Event::fake([CouponRevoked::class]);

    $expiry = now()->addMonth()->startOfSecond();
    $a = Coupon::factory()->active()->create(['code' => 'A', 'expires_at' => $expiry]);
    $b = Coupon::factory()->active()->create(['code' => 'B']);

    $this->artisan($command, $parameters)
        ->expectsOutputToContain('--code')
        ->assertFailed();

    expect($a->fresh()->expires_at?->equalTo($expiry))->toBeTrue()
        ->and($b->fresh()->expires_at)->toBeNull();

    Event::assertNotDispatched(CouponRevoked::class);
})->with([
    'empty value' => ['coupons:expire', ['--code' => '']],
    'whitespace value' => ['coupons:expire', ['--code' => '   ']],
    'empty value on the command line' => ['coupons:expire --code=', []],
    'bare flag on the command line' => ['coupons:expire --code', []],
]);
