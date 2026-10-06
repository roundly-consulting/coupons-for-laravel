<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\Coupons\CouponManager;
use RoundlyConsulting\Coupons\DataTransferObjects\CreateCouponData;
use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Coupons\Enums\RedemptionFailureReason;
use RoundlyConsulting\Coupons\Exceptions\CouponAlreadyRedeemed;
use RoundlyConsulting\Coupons\Exceptions\CouponAtMaxUsage;
use RoundlyConsulting\Coupons\Exceptions\InvalidCouponDefinition;
use RoundlyConsulting\Coupons\Facades\Coupons;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\Rules\Redeemable;
use RoundlyConsulting\Coupons\Testing\CouponsFake;
use RoundlyConsulting\Coupons\Tests\Fixtures\Customer;
use RoundlyConsulting\Money\Currency;
use RoundlyConsulting\Money\Money;

it('swaps the binding and records without touching the database', function (): void {
    $fake = Coupons::fake();

    expect($fake)->toBeInstanceOf(CouponsFake::class);

    Coupons::generate(DiscountType::Fixed, 500, 'FAKED', currency: 'EUR');
    Coupons::redeem('FAKED', Money::ofMinor(5000, 'EUR'));

    expect(Coupon::query()->count())->toBe(0);

    $fake->assertCreated();
    $fake->assertRedeemed();
    $fake->assertCreated(fn (Coupon $c): bool => $c->code === 'FAKED');
    $fake->assertRedeemed(callback: fn ($result): bool => $result->coupon->code === 'FAKED');
});

it('asserts nothing was redeemed', function (): void {
    Coupons::fake()->assertNothingRedeemed();
});

it('fails when asserting a redemption that did not happen', function (): void {
    Coupons::fake()->assertRedeemed();
})->throws(AssertionFailedError::class);

it('generates an auto code on the fake', function (): void {
    $fake = Coupons::fake();

    $first = Coupons::generate(DiscountType::Fixed, 500, currency: 'EUR');

    expect($first->code)->toBe('FAKE-1');
    $fake->assertCreated();
});

it('fails when asserting a created coupon that did not match', function (): void {
    $fake = Coupons::fake();
    Coupons::generate(DiscountType::Fixed, 500, 'A', currency: 'EUR');

    $fake->assertCreated(fn (Coupon $c): bool => $c->code === 'B');
})->throws(AssertionFailedError::class);

it('asserts a redemption by code', function (): void {
    $fake = Coupons::fake();
    Coupons::redeem('SAVE', Money::ofMinor(1000, 'USD'));

    $fake->assertRedeemed('SAVE');
    $fake->assertRedeemed('SAVE', fn ($result): bool => $result->coupon->code === 'SAVE');
});

it('fails asserting a redemption for the wrong code', function (): void {
    $fake = Coupons::fake();
    Coupons::redeem('SAVE', Money::ofMinor(1000, 'USD'));

    $fake->assertRedeemed('OTHER');
})->throws(AssertionFailedError::class);

it('asserts a coupon was not redeemed', function (): void {
    $fake = Coupons::fake();
    Coupons::redeem('SAVE', Money::ofMinor(1000, 'USD'));

    $fake->assertNotRedeemed('OTHER');
});

it('fails asserting not-redeemed when it was redeemed', function (): void {
    $fake = Coupons::fake();
    Coupons::redeem('SAVE', Money::ofMinor(1000, 'USD'));

    $fake->assertNotRedeemed('SAVE');
})->throws(AssertionFailedError::class);

it('asserts a redemption failed with and without a reason', function (): void {
    $fake = Coupons::fake();
    $expired = Coupon::factory()->active()->expired()->make(['code' => 'OLD']);

    Coupons::redeem($expired, Money::ofMinor(1000, 'USD'));

    $fake->assertRedemptionFailed('OLD');
    $fake->assertRedemptionFailed('OLD', 'expired');
});

it('fails asserting a redemption failure that did not happen', function (): void {
    $fake = Coupons::fake();
    Coupons::redeem('SAVE', Money::ofMinor(1000, 'USD'));

    $fake->assertRedemptionFailed('SAVE');
})->throws(AssertionFailedError::class);

it('does not match a failure recorded for a different code', function (): void {
    $fake = Coupons::fake();
    $expired = Coupon::factory()->active()->expired()->make(['code' => 'OLD']);
    Coupons::redeem($expired, Money::ofMinor(1000, 'USD'));

    $fake->assertRedemptionFailed('OLD');
    $fake->assertRedemptionFailed('SOMETHING-ELSE');
})->throws(AssertionFailedError::class);

it('asserts a redemption by callback only', function (): void {
    $fake = Coupons::fake();
    Coupons::redeem('SAVE', Money::ofMinor(1000, 'USD'));

    $fake->assertRedeemed(callback: fn ($result): bool => $result->coupon->code === 'SAVE');
});

it('creates quietly on the fake', function (): void {
    $fake = Coupons::fake();

    $coupon = Coupons::createQuietly(CreateCouponData::fixed(Money::ofMinor(100, 'EUR'), 'Q'));

    expect($coupon->code)->toBe('Q');
    $fake->assertCreated(fn (Coupon $c): bool => $c->code === 'Q');
});

it('reports existence and an empty redeemable query on the fake', function (): void {
    Coupons::fake();
    Coupons::generate(DiscountType::Fixed, 100, 'EXISTS', currency: 'EUR');

    expect(Coupons::exists('EXISTS'))->toBeTrue()
        ->and(Coupons::exists('NOPE'))->toBeFalse()
        ->and(Coupons::redeemable()->count())->toBe(0);
});

it('revokes a coupon on the fake', function (): void {
    Coupons::fake();

    expect(Coupons::revoke('KILL')->isExpired())->toBeTrue();
});

it('keeps the currency lock, minimum spend and cap on faked coupons', function (): void {
    $fake = Coupons::fake();

    $coupon = Coupons::create(CreateCouponData::percentage(10, 'CAPPED', Money::ofMinor(500, 'EUR'), minimumSpend: Money::ofMinor(2000, 'EUR')));
    $generated = Coupons::generate(DiscountType::Fixed, 300, 'GEN', currency: Currency::of('JPY'));

    expect($coupon->currency?->code)->toBe('EUR')
        ->and($coupon->max_discount?->minor())->toBe('500')
        ->and($coupon->minimum_spend?->minor())->toBe('2000')
        ->and($generated->currency?->code)->toBe('JPY');

    Coupons::redeem($coupon, Money::ofMinor(1000, 'USD'));

    $fake->assertRedemptionFailed('CAPPED', 'currency_mismatch');
});

it('installs itself behind constructor-injected managers', function (): void {
    $fake = Coupons::fake();

    app(CouponManager::class)->redeem('INJECTED', Money::ofMinor(1000, 'EUR'));

    expect(app(CouponManager::class))->toBe($fake);
    $fake->assertRedeemed('INJECTED');
});

// Regression: Coupon::redeemBy() called RedeemCouponAction directly, so the fake never
// saw it (and the real action went looking for the unsaved coupon in the database).
it('records a redemption made through the model', function (): void {
    $fake = Coupons::fake();
    $coupon = Coupon::factory()->fixed(500, 'EUR')->make(['code' => 'MODEL']);

    $coupon->redeemBy(null, Money::ofMinor(5000, 'EUR'));

    $fake->assertRedeemed('MODEL');
    expect(Coupon::query()->count())->toBe(0);
});

// Regression: HasCoupons::redeemCoupon() bypassed the fake the same way.
it('records a redemption made through the redeemer trait', function (): void {
    $fake = Coupons::fake();
    $customer = Customer::query()->create(['name' => 'Ada']);

    $customer->redeemCoupon('TRAIT', Money::ofMinor(5000, 'EUR'));

    $fake->assertRedeemed('TRAIT', fn ($result): bool => $result->redeemer === $customer);
});

it('fails asserting nothing redeemed after a trait redemption', function (): void {
    $fake = Coupons::fake();
    Customer::query()->create(['name' => 'Ada'])->redeemCoupon('TRAIT', Money::ofMinor(5000, 'EUR'));

    $fake->assertNothingRedeemed();
})->throws(AssertionFailedError::class);

// Regression: a refused redemption landed in the redeemed list too, so assertRedeemed()
// passed for a coupon the real manager would have rejected.
it('does not count a refused redemption as redeemed', function (): void {
    $fake = Coupons::fake();
    $expired = Coupon::factory()->active()->expired()->make(['code' => 'OLD']);

    Coupons::redeem($expired, Money::ofMinor(1000, 'USD'));

    $fake->assertRedemptionFailed('OLD', RedemptionFailureReason::Expired);
    $fake->assertNotRedeemed('OLD');
    $fake->assertNothingRedeemed();
});

it('answers check() the way its redeem() would record it', function (): void {
    Coupons::fake();
    Coupons::create(CreateCouponData::fixed(Money::ofMinor(500, 'EUR'), 'EUR5'));
    $expired = Coupon::factory()->active()->expired()->make(['code' => 'OLD']);

    expect(Coupons::code('EUR5')->check(Money::ofMinor(5000, 'EUR')))->toBeNull()
        ->and(Coupons::code('EUR5')->check(Money::ofMinor(5000, 'USD')))->toBe(RedemptionFailureReason::CurrencyMismatch)
        ->and(Coupons::code($expired)->check())->toBe(RedemptionFailureReason::Expired)
        ->and(Coupons::code('UNSEEDED')->check())->toBeNull()
        ->and($expired->isRedeemableBy())->toBeFalse();
});

it('previews against coupons created on the fake', function (): void {
    Coupons::fake();
    Coupons::create(CreateCouponData::percentage(20, 'TWENTY'));

    expect(Coupons::code('TWENTY')->preview(Money::ofMinor(5000, 'EUR'))->minor())->toBe('1000')
        ->and(Coupons::code('UNSEEDED')->preview(Money::ofMinor(5000, 'EUR'))->isZero())->toBeTrue();
});

it('finds coupons created on the fake before looking at the database', function (): void {
    Coupon::factory()->create(['code' => 'ROW']);
    Coupons::fake();
    Coupons::generate(DiscountType::Percentage, 1000, 'MEMORY');

    expect(Coupons::find('MEMORY')?->exists)->toBeFalse()
        ->and(Coupons::find('ROW')?->exists)->toBeTrue()
        ->and(Coupons::exists('ROW'))->toBeTrue()
        ->and(Coupons::findOrFail('MEMORY')->code)->toBe('MEMORY');
});

it('asserts nothing was created', function (): void {
    Coupons::fake()->assertNothingCreated();
});

it('fails asserting nothing created after a create', function (): void {
    $fake = Coupons::fake();
    Coupons::generate(DiscountType::Percentage, 1000);

    $fake->assertNothingCreated();
})->throws(AssertionFailedError::class);

it('asserts a revocation through the handle and by model, without saving', function (): void {
    $fake = Coupons::fake();
    $row = Coupon::factory()->active()->create(['code' => 'ROW']);

    Coupons::code('KILL')->revoke();
    Coupons::revoke($row);

    $fake->assertRevoked();
    $fake->assertRevoked('KILL');
    $fake->assertRevoked('ROW');
    expect($row->fresh()->isExpired())->toBeFalse();
});

it('fails asserting a revocation that did not happen', function (): void {
    $fake = Coupons::fake();
    Coupons::revoke('KILL');

    $fake->assertRevoked('OTHER');
})->throws(AssertionFailedError::class, 'Expected coupon "OTHER" to be revoked');

it('fails asserting any revocation when none happened', function (): void {
    Coupons::fake()->assertRevoked();
})->throws(AssertionFailedError::class, 'Expected a coupon to be revoked');

it('asserts nothing was revoked', function (): void {
    Coupons::fake()->assertNothingRevoked();
});

it('fails asserting nothing revoked after a revoke', function (): void {
    $fake = Coupons::fake();
    Coupons::code('KILL')->revoke();

    $fake->assertNothingRevoked();
})->throws(AssertionFailedError::class);

it('counts a bulk expiry as a revocation', function (): void {
    $fake = Coupons::fake();
    Coupons::expireAll();

    $fake->assertNothingRevoked();
})->throws(AssertionFailedError::class, 'expireAll() was called');

it('asserts a bulk expiry and expires the coupons created on it', function (): void {
    $fake = Coupons::fake();
    $a = Coupons::generate(DiscountType::Percentage, 1000, 'A');
    $b = Coupons::generate(DiscountType::Percentage, 1000, 'B');

    expect(Coupons::expireAll('A'))->toBe(1)
        ->and($a->isExpired())->toBeTrue()
        ->and($b->isExpired())->toBeFalse()
        ->and(Coupons::expireAll())->toBe(1);

    $fake->assertExpiredAll();
});

it('fails asserting a bulk expiry that did not happen', function (): void {
    Coupons::fake()->assertExpiredAll();
})->throws(AssertionFailedError::class);

it('asserts a prune by window and mode without deleting', function (): void {
    $fake = Coupons::fake();
    $row = Coupon::factory()->create(['expires_at' => now()->subDays(40)]);

    expect(Coupons::prune(30, force: true))->toBe(0);

    $fake->assertPruned();
    $fake->assertPruned(days: 30);
    $fake->assertPruned(force: true);
    expect(Coupon::query()->whereKey($row->id)->exists())->toBeTrue();
});

it('fails asserting a prune with another window', function (): void {
    $fake = Coupons::fake();
    Coupons::prune(30);

    $fake->assertPruned(days: 7);
})->throws(AssertionFailedError::class);

it('asserts nothing was pruned', function (): void {
    Coupons::fake()->assertNothingPruned();
});

it('fails asserting nothing pruned after a prune', function (): void {
    $fake = Coupons::fake();
    Coupons::prune();

    $fake->assertNothingPruned();
})->throws(AssertionFailedError::class);

it('validates through the fake', function (): void {
    Coupons::fake();

    expect(validator(['code' => 'UNSEEDED'], ['code' => new Redeemable])->passes())->toBeTrue()
        ->and(validator(['code' => ['not-a-string']], ['code' => new Redeemable])->fails())->toBeTrue();
});

// Regression: the fake waived the "not active" refusal for every coupon with neither date,
// database rows included — so a seeded coupon the app forgot to activate was redeemable under
// the fake while the real check reported it expired, hiding the production bug.
it('refuses a seeded coupon that was never activated, like the real check', function (): void {
    $row = Coupon::factory()->percentage(2000)->create(['code' => 'NEVERACTIVE']);
    $fake = Coupons::fake();

    $result = Coupons::redeem('NEVERACTIVE', Money::ofMinor(5000, 'EUR'));

    expect(Coupons::check('NEVERACTIVE'))->toBe(RedemptionFailureReason::Expired)
        ->and($row->isRedeemableBy())->toBeFalse()
        ->and($result->discount->isZero())->toBeTrue()
        ->and($result->total->minor())->toBe('5000');

    $fake->assertRedemptionFailed('NEVERACTIVE', RedemptionFailureReason::Expired);
    $fake->assertNothingRedeemed();
});

// Regression: the fake's redeem() always returned a zero discount and the full price, so code
// reading `$result->total` behaved differently under the fake than in production.
it('returns the real discount and total from a faked redemption', function (): void {
    Coupon::factory()->active()->fixed(750, 'EUR')->create(['code' => 'ROW']);
    $fake = Coupons::fake();
    Coupons::create(CreateCouponData::percentage(20, 'TWENTY'));
    $cart = Money::ofMinor(5000, 'EUR');

    $percent = Coupons::redeem('TWENTY', $cart);
    $fixed = Coupons::redeem('ROW', $cart);
    $unseeded = Coupons::redeem('UNSEEDED', $cart);

    expect($percent->discount->minor())->toBe('1000')
        ->and($percent->total->minor())->toBe('4000')
        ->and($fixed->discount->minor())->toBe('750')
        ->and($fixed->total->minor())->toBe('4250')
        ->and($unseeded->discount->isZero())->toBeTrue()
        ->and($unseeded->total->minor())->toBe('5000');

    $fake->assertRedeemed('TWENTY', fn ($result): bool => $result->total->minor() === '4000');
});

it('refuses the same invalid definitions the real create does', function (DiscountType $type, int $value, ?string $currency): void {
    $fake = Coupons::fake();

    expect(fn () => Coupons::generate($type, $value, 'INVALID', currency: $currency))->toThrow(InvalidCouponDefinition::class);

    $fake->assertNothingCreated();
})->with([
    'fixed without a currency' => [DiscountType::Fixed, 500, null],
    'negative fixed value' => [DiscountType::Fixed, -1, 'EUR'],
    'percentage out of range' => [DiscountType::Percentage, 10_001, null],
]);

// Regression: the fake recorded every successful redemption but never counted it, so a
// single-use coupon — created on the fake or seeded as a row — and a one-per-customer cap all
// redeemed twice under the fake while production refused the second attempt.
it('consumes usage on the fake like the real manager', function (Closure $seed, RedemptionFailureReason $reason, string $exception, bool $faked): void {
    $fake = $faked ? Coupons::fake() : null;
    [$code, $redeemer] = $seed($faked);
    $cart = Money::ofMinor(5000, 'EUR');

    expect(Coupons::redeem($code, $cart, $redeemer)->discount->minor())->toBe('500');

    if ($fake === null) {
        expect(fn () => Coupons::redeem($code, $cart, $redeemer))->toThrow($exception);

        return;
    }

    expect(Coupons::check($code, $cart, $redeemer))->toBe($reason)
        ->and(Coupons::redeem($code, $cart, $redeemer)->discount->isZero())->toBeTrue();

    $fake->assertRedemptionFailed($code, $reason);

    $successes = 0;
    $fake->assertRedeemed($code, function () use (&$successes): bool {
        $successes++;

        return true;
    });

    expect($successes)->toBe(1);
})->with([
    'a coupon created with maxUsage 1' => [function (bool $faked): array {
        $coupon = Coupons::generate(DiscountType::Percentage, 1000, 'ONCE', maxUsage: 1);

        // On the fake the undated in-memory coupon is not refused as inactive; a row must be.
        if (! $faked) {
            $coupon->activate()->save();
        }

        return ['ONCE', null];
    }, RedemptionFailureReason::AtMaxUsage, CouponAtMaxUsage::class],
    'a seeded row with max_usage 1' => [function (): array {
        Coupon::factory()->active()->percentage(1000)->create(['code' => 'ROW', 'max_usage' => 1]);

        return ['ROW', null];
    }, RedemptionFailureReason::AtMaxUsage, CouponAtMaxUsage::class],
    'a seeded row with max_usage_per_redeemer 1' => [function (): array {
        Coupon::factory()->active()->percentage(1000)->create(['code' => 'PER', 'max_usage_per_redeemer' => 1]);

        return ['PER', Customer::query()->create(['name' => 'Ada'])];
    }, RedemptionFailureReason::AlreadyRedeemed, CouponAlreadyRedeemed::class],
])->with(['real manager' => false, 'fake' => true]);

// Regression: waiving "inactive" for an undated in-memory coupon returned null outright, so
// the usage checks behind it never ran.
it('still checks the usage cap of an undated in-memory coupon', function (): void {
    Coupons::fake();

    $exhausted = Coupon::factory()->percentage(1000)->make(['code' => 'USEDUP', 'usage' => 1, 'max_usage' => 1]);
    $fresh = Coupon::factory()->percentage(1000)->make(['code' => 'FRESH', 'max_usage' => 1]);

    expect(Coupons::check($exhausted))->toBe(RedemptionFailureReason::AtMaxUsage)
        ->and(Coupons::check($fresh))->toBeNull();
});
