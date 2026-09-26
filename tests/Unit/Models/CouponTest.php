<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Coupons\Exceptions\InvalidCouponDefinition;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\Models\CouponRedemption;
use RoundlyConsulting\Coupons\Tests\Fixtures\Customer;
use RoundlyConsulting\Money\Currency;
use RoundlyConsulting\Money\Discounts\Discount;
use RoundlyConsulting\Money\Enums\DiscountTarget;
use RoundlyConsulting\Money\Exceptions\CurrencyMismatch;
use RoundlyConsulting\Money\Money;

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
    $coupon = Coupon::factory()->fixed(50, 'EUR')->make();

    $result = $coupon->apply(Money::ofMinor(1000, 'EUR'));

    expect($result)->toBeInstanceOf(Money::class)
        ->minor()->toBe('950')
        ->currency()->code->toBe('EUR');
});

it('applies a percentage discount to a price', function (): void {
    $coupon = Coupon::factory()->percentage(2500)->make();

    $result = $coupon->apply(Money::ofMinor(1000, 'EUR'));

    expect($result->minor())->toBe('750');
});

it('returns the discount amount respecting the cap', function (): void {
    $coupon = Coupon::factory()->cappedPercentage(5000, Money::ofMinor(300, 'EUR'))->make();

    expect($coupon->discountFor(Money::ofMinor(1000, 'EUR'))->minor())->toBe('300')
        ->and($coupon->apply(Money::ofMinor(1000, 'EUR'))->minor())->toBe('700');
});

it('reports a free-shipping coupon', function (): void {
    $coupon = Coupon::factory()->freeShipping()->make();

    expect($coupon->isFreeShipping())->toBeTrue()
        ->and($coupon->appliesToShipping())->toBeTrue()
        ->and($coupon->discountFor(Money::ofMinor(1000, 'EUR'))->minor())->toBe('0');

    expect(Coupon::factory()->fixed()->make()->isFreeShipping())->toBeFalse();
});

it('applies to any currency when not locked', function (): void {
    $coupon = Coupon::factory()->percentage()->make();

    expect($coupon->appliesToCurrency(Money::ofMinor(1000, 'EUR')))->toBeTrue()
        ->and($coupon->appliesToCurrency(Money::ofMinor(1000, 'USD')))->toBeTrue();
});

it('only applies to its locked currency', function (): void {
    $coupon = Coupon::factory()->fixed()->forCurrency('EUR')->make();

    expect($coupon->appliesToCurrency(Money::ofMinor(1000, 'EUR')))->toBeTrue()
        ->and($coupon->appliesToCurrency(Money::ofMinor(1000, 'USD')))->toBeFalse();
});

it('throws when applied to a mismatched currency', function (): void {
    Coupon::factory()->fixed()->forCurrency('EUR')->make()->apply(Money::ofMinor(1000, 'USD'));
})->throws(CurrencyMismatch::class);

it('checks the minimum spend', function (): void {
    $unconstrained = Coupon::factory()->fixed(100, 'EUR')->make(['minimum_spend' => null]);
    $constrained = Coupon::factory()->fixed(100, 'EUR')->withMinimumSpend(Money::ofMinor(1000, 'EUR'))->make();

    expect($unconstrained->meetsMinimumSpend(Money::ofMinor(1, 'EUR')))->toBeTrue()
        ->and($constrained->meetsMinimumSpend(Money::ofMinor(999, 'EUR')))->toBeFalse()
        ->and($constrained->meetsMinimumSpend(Money::ofMinor(1000, 'EUR')))->toBeTrue()
        ->and($constrained->meetsMinimumSpend(Money::ofMinor(2000, 'EUR')))->toBeTrue();
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

    expect($clean->isRedeemableBy($customer, Money::ofMinor(1000, 'USD')))->toBeTrue()
        ->and($clean->isRedeemableBy())->toBeTrue();

    $expired = Coupon::factory()->active()->expired()->create();
    expect($expired->isRedeemableBy())->toBeFalse();

    $atMax = Coupon::factory()->active()->create(['max_usage' => 1, 'usage' => 1]);
    expect($atMax->isRedeemableBy())->toBeFalse();

    $locked = Coupon::factory()->active()->fixed()->forCurrency('EUR')->create();
    expect($locked->isRedeemableBy(null, Money::ofMinor(1000, 'USD')))->toBeFalse();

    $minimum = Coupon::factory()->active()->fixed()->withMinimumSpend(Money::ofMinor(2000, 'USD'))->create();
    expect($minimum->isRedeemableBy(null, Money::ofMinor(1000, 'USD')))->toBeFalse();

    $perRedeemer = Coupon::factory()->active()->create(['max_usage_per_redeemer' => 1]);
    $perRedeemer->redemptions()->create([
        'redeemer_type' => $customer->getMorphClass(),
        'redeemer_id' => $customer->getKey(),
        'amount_discounted' => Money::ofMinor(100, 'USD'),
    ]);
    expect($perRedeemer->isRedeemableBy($customer))->toBeFalse();
});

it('previews a discount without throwing on a currency mismatch', function (): void {
    $fixed = Coupon::factory()->fixed(250)->make();
    expect($fixed->previewDiscount(Money::ofMinor(1000, 'USD'))->minor())->toBe('250');

    $capped = Coupon::factory()->cappedPercentage(5000, Money::ofMinor(300, 'USD'))->make();
    expect($capped->previewDiscount(Money::ofMinor(1000, 'USD'))->minor())->toBe('300');

    $freeShipping = Coupon::factory()->freeShipping()->make();
    expect($freeShipping->previewDiscount(Money::ofMinor(1000, 'USD'))->minor())->toBe('0');

    $locked = Coupon::factory()->fixed(250)->forCurrency('EUR')->make();
    $preview = $locked->previewDiscount(Money::ofMinor(1000, 'USD'));
    expect($preview->minor())->toBe('0')
        ->and($preview->currency()->code)->toBe('USD');
});

it('casts the currency lock, minimum spend and cap to money types', function (): void {
    $coupon = Coupon::factory()
        ->cappedPercentage(1000, Money::ofMinor(500, 'EUR'))
        ->withMinimumSpend(Money::ofMinor(2000, 'EUR'))
        ->create();

    $fresh = $coupon->fresh();

    expect($fresh?->currency)->toBeInstanceOf(Currency::class)
        ->and($fresh?->currency?->code)->toBe('EUR')
        ->and($fresh?->minimum_spend?->equals(Money::ofMinor(2000, 'EUR')))->toBeTrue()
        ->and($fresh?->max_discount?->equals(Money::ofMinor(500, 'EUR')))->toBeTrue();
});

it('returns string minor amounts from discountFor', function (): void {
    $discount = Coupon::factory()->percentage(1250)->make()->discountFor(Money::ofMinor(999, 'EUR'));

    // 12.5 % of 999 = 124.875 → 125 (half away from zero).
    expect($discount->minor())->toBe('125')
        ->and($discount->currency()->code)->toBe('EUR');
});

it('discounts a fixed amount in a zero-exponent and a three-exponent currency', function (): void {
    $yen = Coupon::factory()->fixed(500, 'JPY')->make();
    $dinar = Coupon::factory()->fixed(1500, 'BHD')->make();

    expect($yen->apply(Money::ofMinor(1200, 'JPY'))->minor())->toBe('700')
        ->and((string) $yen->discountFor(Money::ofMinor(1200, 'JPY')))->toBe('500 JPY')
        ->and((string) $dinar->discountFor(Money::ofMinor(10000, 'BHD')))->toBe('1.500 BHD');
});

it('never discounts more than the price', function (): void {
    $coupon = Coupon::factory()->fixed(5000, 'EUR')->make();

    expect($coupon->discountFor(Money::ofMinor(1000, 'EUR'))->minor())->toBe('1000')
        ->and($coupon->apply(Money::ofMinor(1000, 'EUR'))->minor())->toBe('0');
});

it('caps a percentage below and leaves it alone above the discount', function (): void {
    $low = Coupon::factory()->cappedPercentage(5000, Money::ofMinor(100, 'EUR'))->make();
    $high = Coupon::factory()->cappedPercentage(5000, Money::ofMinor(9000, 'EUR'))->make();

    expect($low->discountFor(Money::ofMinor(1000, 'EUR'))->minor())->toBe('100')
        ->and($high->discountFor(Money::ofMinor(1000, 'EUR'))->minor())->toBe('500');
});

it('discounts nothing for a zero-percent coupon', function (): void {
    expect(Coupon::factory()->percentage(0)->make()->discountFor(Money::ofMinor(1000, 'EUR'))->isZero())->toBeTrue();
});

it('leaves the goods price unchanged when applying a free-shipping coupon', function (): void {
    $price = Money::ofMinor(1000, 'EUR');

    expect(Coupon::factory()->freeShipping()->make()->apply($price)->equals($price))->toBeTrue();
});

it('throws a money currency mismatch from discountFor on a locked coupon', function (): void {
    Coupon::factory()->percentage(1000)->forCurrency('EUR')->make()->discountFor(Money::ofMinor(1000, 'USD'));
})->throws(CurrencyMismatch::class);

it('refuses a fixed coupon row that has no currency', function (): void {
    Coupon::query()->insert([
        'code' => 'RAWFIX',
        'type' => DiscountType::Fixed->value,
        'value' => 500,
        'usage' => 0,
        'max_usage' => 0,
        'max_usage_per_redeemer' => 0,
    ]);

    Coupon::query()->where('code', 'RAWFIX')->firstOrFail()->discountFor(Money::ofMinor(1000, 'EUR'));
})->throws(InvalidCouponDefinition::class, 'must be locked to a currency');

it('refuses a cap in another currency than the coupon lock', function (): void {
    $coupon = Coupon::factory()->percentage(1000)->forCurrency('EUR')->make();

    $coupon->max_discount = Money::ofMinor(500, 'USD');
})->throws(CurrencyMismatch::class);

it('exposes the coupon as a labelled money discount', function (): void {
    $fixed = Coupon::factory()->fixed(300, 'EUR')->make(['code' => 'SAVE3'])->discount(Currency::of('USD'));
    $percent = Coupon::factory()->cappedPercentage(1250, Money::ofMinor(400, 'EUR'))->make(['code' => 'TWELVE'])->discount(Currency::of('EUR'));
    $shipping = Coupon::factory()->freeShipping()->make(['code' => 'SHIP'])->discount(Currency::of('EUR'));

    expect($fixed)->toBeInstanceOf(Discount::class)
        ->and($fixed->label())->toBe('SAVE3')
        ->and($fixed->fixedAmount()?->equals(Money::ofMinor(300, 'EUR')))->toBeTrue()
        ->and($percent->percent()?->value())->toBe('12.5')
        ->and($percent->cap()?->equals(Money::ofMinor(400, 'EUR')))->toBeTrue()
        ->and($shipping->target())->toBe(DiscountTarget::Shipping)
        ->and((new Coupon(['type' => DiscountType::Percentage, 'value' => 1000]))->discount(Currency::of('EUR'))->label())->toBeNull();
});

it('soft deletes coupons', function (): void {
    $coupon = Coupon::factory()->create();

    $coupon->delete();

    expect(Coupon::query()->count())->toBe(0)
        ->and(Coupon::withTrashed()->count())->toBe(1);
});
