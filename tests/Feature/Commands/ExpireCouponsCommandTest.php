<?php

declare(strict_types=1);

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
