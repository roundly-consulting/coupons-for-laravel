<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Coupons\Exceptions\InvalidMoney;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\Models\CouponRedemption;
use RoundlyConsulting\Coupons\Tests\Fixtures\Customer;
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

it('returns the discount amount respecting the cap', function (): void {
    $coupon = Coupon::factory()->cappedPercentage(50, 300)->make();

    expect($coupon->discountFor(new Money(1000, 'EUR'))->getAmount())->toBe(300)
        ->and($coupon->apply(new Money(1000, 'EUR'))->getAmount())->toBe(700);
});

it('reports a free-shipping coupon', function (): void {
    $coupon = Coupon::factory()->freeShipping()->make();

    expect($coupon->isFreeShipping())->toBeTrue()
        ->and($coupon->appliesToShipping())->toBeTrue()
        ->and($coupon->discountFor(new Money(1000, 'EUR'))->getAmount())->toBe(0);

    expect(Coupon::factory()->fixed()->make()->isFreeShipping())->toBeFalse();
});

it('applies to any currency when not locked', function (): void {
    $coupon = Coupon::factory()->fixed()->make(['currency' => null]);

    expect($coupon->appliesToCurrency(new Money(1000, 'EUR')))->toBeTrue()
        ->and($coupon->appliesToCurrency(new Money(1000, 'USD')))->toBeTrue();
});

it('only applies to its locked currency', function (): void {
    $coupon = Coupon::factory()->fixed()->forCurrency('EUR')->make();

    expect($coupon->appliesToCurrency(new Money(1000, 'EUR')))->toBeTrue()
        ->and($coupon->appliesToCurrency(new Money(1000, 'USD')))->toBeFalse();
});

it('throws when applied to a mismatched currency', function (): void {
    Coupon::factory()->fixed()->forCurrency('EUR')->make()->apply(new Money(1000, 'USD'));
})->throws(InvalidMoney::class);

it('checks the minimum spend', function (): void {
    $unconstrained = Coupon::factory()->fixed()->make(['minimum_spend' => null]);
    $constrained = Coupon::factory()->fixed()->withMinimumSpend(1000)->make();

    expect($unconstrained->meetsMinimumSpend(new Money(1, 'EUR')))->toBeTrue()
        ->and($constrained->meetsMinimumSpend(new Money(999, 'EUR')))->toBeFalse()
        ->and($constrained->meetsMinimumSpend(new Money(1000, 'EUR')))->toBeTrue()
        ->and($constrained->meetsMinimumSpend(new Money(2000, 'EUR')))->toBeTrue();
});

it('counts usage by a specific redeemer', function (): void {
    $coupon = Coupon::factory()->create();
    $customer = Customer::query()->create(['name' => 'Ada']);
    $other = Customer::query()->create(['name' => 'Lin']);

    CouponRedemption::factory()->count(2)->create([
        'coupon_id' => $coupon->id,
        'redeemer_type' => $customer->getMorphClass(),
        'redeemer_id' => $customer->getKey(),
    ]);

    expect($coupon->usageBy($customer))->toBe(2)
        ->and($coupon->usageBy($other))->toBe(0);
});

it('checks the per-redeemer cap', function (): void {
    $coupon = Coupon::factory()->create(['max_usage_per_redeemer' => 1]);
    $customer = Customer::query()->create(['name' => 'Ada']);

    expect($coupon->isAtMaximumUsageFor($customer))->toBeFalse();

    CouponRedemption::factory()->create([
        'coupon_id' => $coupon->id,
        'redeemer_type' => $customer->getMorphClass(),
        'redeemer_id' => $customer->getKey(),
    ]);

    expect($coupon->isAtMaximumUsageFor($customer))->toBeTrue();
});

it('treats a zero per-redeemer cap as unlimited', function (): void {
    $coupon = Coupon::factory()->create(['max_usage_per_redeemer' => 0]);
    $customer = Customer::query()->create(['name' => 'Ada']);

    CouponRedemption::factory()->create([
        'coupon_id' => $coupon->id,
        'redeemer_type' => $customer->getMorphClass(),
        'redeemer_id' => $customer->getKey(),
    ]);

    expect($coupon->isAtMaximumUsageFor($customer))->toBeFalse();
});

it('reports remaining global usage, treating a zero cap as unlimited', function (): void {
    expect(Coupon::factory()->make(['max_usage' => 0, 'usage' => 5])->remainingUsage())->toBeNull()
        ->and(Coupon::factory()->make(['max_usage' => 10, 'usage' => 7])->remainingUsage())->toBe(3)
        ->and(Coupon::factory()->make(['max_usage' => 10, 'usage' => 12])->remainingUsage())->toBe(0);
});

it('reports remaining usage for a redeemer', function (): void {
    $coupon = Coupon::factory()->create(['max_usage_per_redeemer' => 3]);
    $customer = Customer::query()->create(['name' => 'Ada']);

    expect($coupon->remainingUsageFor($customer))->toBe(3);

    CouponRedemption::factory()->create([
        'coupon_id' => $coupon->id,
        'redeemer_type' => $customer->getMorphClass(),
        'redeemer_id' => $customer->getKey(),
    ]);

    expect($coupon->remainingUsageFor($customer))->toBe(2);
});

it('returns null remaining usage for a redeemer when the cap is unlimited', function (): void {
    $coupon = Coupon::factory()->create(['max_usage_per_redeemer' => 0]);
    $customer = Customer::query()->create(['name' => 'Ada']);

    expect($coupon->remainingUsageFor($customer))->toBeNull();
});

it('returns null remaining usage for a redeemer when tracking is off', function (): void {
    config()->set('coupons.redeemer.track', false);

    $coupon = Coupon::factory()->create(['max_usage_per_redeemer' => 3]);
    $customer = Customer::query()->create(['name' => 'Ada']);

    expect($coupon->remainingUsageFor($customer))->toBeNull();
});

it('reports the usage percentage, clamped and rounded', function (): void {
    expect(Coupon::factory()->make(['max_usage' => 0, 'usage' => 3])->usagePercentage())->toBeNull()
        ->and(Coupon::factory()->make(['max_usage' => 10, 'usage' => 7])->usagePercentage())->toBe(70.0)
        ->and(Coupon::factory()->make(['max_usage' => 3, 'usage' => 1])->usagePercentage())->toBe(33.33)
        ->and(Coupon::factory()->make(['max_usage' => 10, 'usage' => 15])->usagePercentage())->toBe(100.0);
});

it('reports whether a redeemer can redeem without throwing', function (): void {
    $clean = Coupon::factory()->active()->fixed()->create();
    $customer = Customer::query()->create(['name' => 'Ada']);

    expect($clean->isRedeemableBy($customer, new Money(1000, 'USD')))->toBeTrue()
        ->and($clean->isRedeemableBy())->toBeTrue();

    $expired = Coupon::factory()->active()->expired()->create();
    expect($expired->isRedeemableBy())->toBeFalse();

    $atMax = Coupon::factory()->active()->create(['max_usage' => 1, 'usage' => 1]);
    expect($atMax->isRedeemableBy())->toBeFalse();

    $locked = Coupon::factory()->active()->fixed()->forCurrency('EUR')->create();
    expect($locked->isRedeemableBy(null, new Money(1000, 'USD')))->toBeFalse();

    $minimum = Coupon::factory()->active()->fixed()->withMinimumSpend(2000)->create();
    expect($minimum->isRedeemableBy(null, new Money(1000, 'USD')))->toBeFalse();

    $perRedeemer = Coupon::factory()->active()->create(['max_usage_per_redeemer' => 1]);
    $perRedeemer->redemptions()->create([
        'redeemer_type' => $customer->getMorphClass(),
        'redeemer_id' => $customer->getKey(),
        'amount_discounted' => 100,
        'currency' => 'USD',
    ]);
    expect($perRedeemer->isRedeemableBy($customer))->toBeFalse();
});

it('previews a discount without throwing on a currency mismatch', function (): void {
    $fixed = Coupon::factory()->fixed(250)->make();
    expect($fixed->previewDiscount(new Money(1000, 'USD'))->getAmount())->toBe(250);

    $capped = Coupon::factory()->cappedPercentage(50, 300)->make();
    expect($capped->previewDiscount(new Money(1000, 'USD'))->getAmount())->toBe(300);

    $freeShipping = Coupon::factory()->freeShipping()->make();
    expect($freeShipping->previewDiscount(new Money(1000, 'USD'))->getAmount())->toBe(0);

    $locked = Coupon::factory()->fixed(250)->forCurrency('EUR')->make();
    $preview = $locked->previewDiscount(new Money(1000, 'USD'));
    expect($preview->getAmount())->toBe(0)
        ->and($preview->getCurrency())->toBe('USD');
});

it('soft deletes coupons', function (): void {
    $coupon = Coupon::factory()->create();

    $coupon->delete();

    expect(Coupon::query()->count())->toBe(0)
        ->and(Coupon::withTrashed()->count())->toBe(1);
});
