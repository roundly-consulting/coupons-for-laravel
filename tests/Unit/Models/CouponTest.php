<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\ValueObjects\Money;

it('casts type to the discount enum', function (): void {
    $coupon = Coupon::factory()->make();

    expect($coupon->type)->toBeInstanceOf(DiscountType::class);
});

it('casts meta to a collection', function (): void {
    $coupon = Coupon::factory()->make([
        'meta' => collect(['yes']),
    ]);

    expect($coupon->meta)->toBeInstanceOf(Collection::class)
        ->toArray()->toBe(['yes']);
});

it('checks whether the coupon is active', function (): void {
    $coupon = Coupon::factory()->active()->make();

    expect($coupon)
        ->activated_at->toBeInstanceOf(Carbon::class)
        ->isActive()->toBeTrue();
});

it('checks whether the coupon is expired', function (): void {
    $coupon = Coupon::factory()->expired()->make();

    expect($coupon)
        ->expires_at->toBeInstanceOf(Carbon::class)
        ->isExpired()->toBeTrue();
});

it('is not active or expired when the dates are null', function (): void {
    $coupon = Coupon::factory()->make();

    expect($coupon)
        ->isActive()->toBeFalse()
        ->isExpired()->toBeFalse();
});

it('sets the date of activation', function (): void {
    $coupon = Coupon::factory()->make();

    expect($coupon->activated_at)->toBeNull();

    $coupon->activate(CarbonImmutable::createFromFormat('Y-m-d H:i', '2023-10-17 10:00'));
    expect($coupon->activated_at)->format('Y-m-d H:i')->toBe('2023-10-17 10:00');

    Carbon::setTestNow('2023-10-18 09:00:00');
    $coupon->activate();
    expect($coupon->activated_at)->format('Y-m-d H:i')->toBe('2023-10-18 09:00');
    Carbon::setTestNow();
});

it('sets the date of expiration', function (): void {
    $coupon = Coupon::factory()->make();

    expect($coupon->expires_at)->toBeNull();

    $coupon->expire(CarbonImmutable::createFromFormat('Y-m-d H:i', '2023-10-17 10:00'));
    expect($coupon->expires_at)->format('Y-m-d H:i')->toBe('2023-10-17 10:00');

    Carbon::setTestNow('2023-10-18 09:00:00');
    $coupon->expire();
    expect($coupon->expires_at)->format('Y-m-d H:i')->toBe('2023-10-18 09:00');
    Carbon::setTestNow();
});

it('sets the max usage of the coupon', function (): void {
    $coupon = Coupon::factory()->make(['max_usage' => 0]);

    $coupon->setMaxUsageTo(100);

    expect($coupon->max_usage)->toBe(100);
});

it('treats a zero max usage as unlimited', function (): void {
    $coupon = Coupon::factory()->make([
        'usage' => 5,
        'max_usage' => 0,
    ]);

    expect($coupon->isAtMaximumUsage())->toBeFalse();
});

it('checks whether the coupon has been used at least once', function (): void {
    $coupon = Coupon::factory()->create(['usage' => 0]);

    expect($coupon->hasBeenUsedAtLeastOnce())->toBeFalse();

    $coupon->increment('usage');

    expect($coupon->hasBeenUsedAtLeastOnce())->toBeTrue();
});

it('checks whether the coupon has been used the max number of times', function (): void {
    $coupon = Coupon::factory()->make([
        'usage' => 0,
        'max_usage' => 1,
    ]);

    expect($coupon->isAtMaximumUsage())->toBeFalse();

    $coupon->usage = 1;

    expect($coupon->isAtMaximumUsage())->toBeTrue();
});

it('checks whether the coupon can be applied', function (): void {
    $coupon = Coupon::factory()->make([
        'usage' => 1,
        'max_usage' => 2,
    ]);

    expect($coupon->canBeApplied())->toBeFalse();

    $coupon->activate();
    expect($coupon->canBeApplied())->toBeTrue();

    $coupon->setMaxUsageTo(1);
    expect($coupon->canBeApplied())->toBeFalse();

    $coupon->setMaxUsageTo(2);
    $coupon->expire();
    expect($coupon->canBeApplied())->toBeFalse();
});

it('applies a fixed discount to a price', function (): void {
    $coupon = Coupon::factory()->fixed(50)->make();

    $result = $coupon->apply(new Money(1000, 'EUR'));

    expect($result)->toBeInstanceOf(Money::class)
        ->getAmount()->toBe(950)
        ->getCurrency()->toBe('EUR');
});

it('applies a percentage discount to a price', function (): void {
    $coupon = Coupon::factory()->percentage(25)->make();

    $result = $coupon->apply(new Money(1000, 'EUR'));

    expect($result->getAmount())->toBe(750);
});

it('soft deletes coupons', function (): void {
    $coupon = Coupon::factory()->create();

    $coupon->delete();

    expect(Coupon::query()->count())->toBe(0)
        ->and(Coupon::withTrashed()->count())->toBe(1);
});
