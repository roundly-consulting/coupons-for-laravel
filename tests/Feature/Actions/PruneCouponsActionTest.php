<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\Actions\PruneCouponsAction;
use RoundlyConsulting\Coupons\Models\Coupon;

it('soft deletes coupons expired beyond the window and returns the count', function (): void {
    $old = Coupon::factory()->create(['expires_at' => now()->subDays(40)]);
    $recent = Coupon::factory()->create(['expires_at' => now()->subDays(5)]);
    $live = Coupon::factory()->create(['expires_at' => null]);

    expect(app(PruneCouponsAction::class)->execute(30))->toBe(1)
        ->and(Coupon::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(Coupon::withTrashed()->whereKey($old->id)->exists())->toBeTrue()
        ->and(Coupon::query()->whereKey($recent->id)->exists())->toBeTrue()
        ->and(Coupon::query()->whereKey($live->id)->exists())->toBeTrue();
});

it('force deletes, trashed coupons included', function (): void {
    $old = Coupon::factory()->create(['expires_at' => now()->subDays(40)]);
    $trashed = Coupon::factory()->create(['expires_at' => now()->subDays(50)]);
    $trashed->delete();

    expect(app(PruneCouponsAction::class)->execute(30, force: true))->toBe(2)
        ->and(Coupon::withTrashed()->whereKey([$old->id, $trashed->id])->count())->toBe(0);
});

it('prunes every expired coupon with a zero window', function (): void {
    Coupon::factory()->create(['expires_at' => now()->subMinute()]);
    $live = Coupon::factory()->create(['expires_at' => now()->addMinute()]);

    expect(app(PruneCouponsAction::class)->execute(0))->toBe(1)
        ->and(Coupon::query()->whereKey($live->id)->exists())->toBeTrue();
});

it('refuses a negative window', function (): void {
    app(PruneCouponsAction::class)->execute(-1);
})->throws(InvalidArgumentException::class, 'zero days or more, -1 given');
