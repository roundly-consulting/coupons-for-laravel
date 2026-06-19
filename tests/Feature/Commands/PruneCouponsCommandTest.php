<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\Models\Coupon;

it('soft deletes coupons expired beyond the window', function (): void {
    $old = Coupon::factory()->create(['code' => 'OLD', 'expires_at' => now()->subDays(40)]);
    $recent = Coupon::factory()->create(['code' => 'RECENT', 'expires_at' => now()->subDays(5)]);

    $this->artisan('coupons:prune', ['--days' => 30])
        ->expectsOutputToContain('Pruned 1 coupon(s).')
        ->assertSuccessful();

    expect(Coupon::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(Coupon::withTrashed()->whereKey($old->id)->exists())->toBeTrue()
        ->and(Coupon::query()->whereKey($recent->id)->exists())->toBeTrue();
});

it('force deletes when asked', function (): void {
    $old = Coupon::factory()->create(['expires_at' => now()->subDays(40)]);

    $this->artisan('coupons:prune', ['--days' => 30, '--force' => true])
        ->assertSuccessful();

    expect(Coupon::withTrashed()->whereKey($old->id)->exists())->toBeFalse();
});

it('prunes nothing when no coupon is old enough', function (): void {
    Coupon::factory()->create(['expires_at' => now()->subDays(5)]);

    $this->artisan('coupons:prune', ['--days' => 30])
        ->expectsOutputToContain('Pruned 0 coupon(s).')
        ->assertSuccessful();
});
