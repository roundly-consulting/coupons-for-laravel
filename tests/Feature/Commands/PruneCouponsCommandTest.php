<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\Facades\Coupons;
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

// Regression: the query honoured the soft-delete scope, so `--force` never purged the
// coupons an earlier soft prune had trashed — they stayed in the table for good.
it('hard-deletes coupons an earlier soft prune trashed', function (): void {
    $old = Coupon::factory()->create(['expires_at' => now()->subDays(40)]);
    $old->delete();

    $this->artisan('coupons:prune', ['--days' => 30, '--force' => true])
        ->expectsOutputToContain('Pruned 1 coupon(s).')
        ->assertSuccessful();

    expect(Coupon::withTrashed()->whereKey($old->id)->exists())->toBeFalse();
});

// Regression: a negative window put the threshold in the future, so `--days=-5` deleted
// coupons that were still live.
it('refuses a negative window instead of pruning live coupons', function (): void {
    $live = Coupon::factory()->create(['expires_at' => now()->addDays(3)]);

    $this->artisan('coupons:prune', ['--days' => -5])
        ->expectsOutputToContain('zero days or more')
        ->assertFailed();

    expect(Coupon::query()->whereKey($live->id)->exists())->toBeTrue();
});

it('runs through the manager, so the fake records it', function (): void {
    $fake = Coupons::fake();

    $this->artisan('coupons:prune', ['--days' => 7, '--force' => true])
        ->expectsOutputToContain('Pruned 0 coupon(s).')
        ->assertSuccessful();

    $fake->assertPruned(days: 7, force: true);
});

// Regression: `(int) 'thirty'` is 0, so a typo'd window pruned every expired coupon — one that
// expired an hour ago included — and reported success.
it('refuses a window that is not a whole number of days', function (string $days): void {
    $recent = Coupon::factory()->create(['expires_at' => now()->subHour()]);

    $this->artisan('coupons:prune', ['--days' => $days])
        ->expectsOutputToContain('The --days option must be a whole number of days')
        ->assertFailed();

    expect(Coupon::query()->whereKey($recent->id)->exists())->toBeTrue();
})->with(['a word' => 'thirty', 'a fraction' => '1.5', 'a unit' => '30d', 'empty' => '']);

it('accepts a zero window', function (): void {
    $recent = Coupon::factory()->create(['expires_at' => now()->subHour()]);

    $this->artisan('coupons:prune', ['--days' => '0'])
        ->expectsOutputToContain('Pruned 1 coupon(s).')
        ->assertSuccessful();

    expect(Coupon::query()->whereKey($recent->id)->exists())->toBeFalse();
});
