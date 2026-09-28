<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Coupons\Actions\ExpireCouponsAction;
use RoundlyConsulting\Coupons\Events\CouponRevoked;
use RoundlyConsulting\Coupons\Models\Coupon;

it('revokes every live coupon and returns the count', function (): void {
    Event::fake([CouponRevoked::class]);
    $live = Coupon::factory()->active()->create(['code' => 'LIVE']);
    $later = Coupon::factory()->active()->create(['code' => 'LATER', 'expires_at' => now()->addWeek()]);
    Coupon::factory()->active()->expired()->create(['code' => 'OLD']);

    expect(app(ExpireCouponsAction::class)->execute())->toBe(2)
        ->and($live->fresh()->isExpired())->toBeTrue()
        ->and($later->fresh()->isExpired())->toBeTrue();

    Event::assertDispatchedTimes(CouponRevoked::class, 2);
});

it('narrows the sweep to one code', function (): void {
    $a = Coupon::factory()->active()->create(['code' => 'A']);
    $b = Coupon::factory()->active()->create(['code' => 'B']);

    expect(app(ExpireCouponsAction::class)->execute('A'))->toBe(1)
        ->and(app(ExpireCouponsAction::class)->execute('MISSING'))->toBe(0)
        ->and($a->fresh()->isExpired())->toBeTrue()
        ->and($b->fresh()->isExpired())->toBeFalse();
});

it('pages past one chunk without skipping coupons', function (): void {
    Coupon::factory()->count(150)->active()->create();

    expect(app(ExpireCouponsAction::class)->execute())->toBe(150)
        ->and(Coupon::query()->redeemable()->count())->toBe(0);
});
