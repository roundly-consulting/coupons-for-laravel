<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Coupons\Enums\RedemptionFailureReason;
use RoundlyConsulting\Coupons\Exceptions\CouponCodeTaken;
use RoundlyConsulting\Coupons\Exceptions\CouponNotFound;
use RoundlyConsulting\Coupons\Exceptions\InvalidCouponDefinition;
use RoundlyConsulting\Coupons\Facades\Coupons;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\Rules\Redeemable;
use RoundlyConsulting\Coupons\Support\CodeFormat;
use RoundlyConsulting\Coupons\Tests\Fixtures\Customer;
use RoundlyConsulting\Money\Money;

/**
 * Codes are case-insensitive and whitespace-trimmed, in one canonical (upper) case, on
 * every write and every lookup. Before, `SUMMER` was not found as `summer` on sqlite and
 * pgsql but was on MySQL's `_ci` collations — and a pasted ` SUMMER ` was found nowhere.
 * Lookup and uniqueness now never depend on the database collation.
 */
it('normalises a code to one trimmed, upper-cased form', function (string $given, string $canonical): void {
    expect(CodeFormat::normalize($given))->toBe($canonical);
})->with([
    'lower case' => ['summer', 'SUMMER'],
    'mixed case' => ['Summer', 'SUMMER'],
    'padded' => ["  SUMMER \t", 'SUMMER'],
    'non-breaking space' => ["\u{00A0}summer\u{00A0}", 'SUMMER'],
    'multibyte' => ['ärger', 'ÄRGER'],
    'blank' => ['   ', ''],
]);

it('stores an explicit code in its canonical form', function (): void {
    $coupon = Coupons::generate(DiscountType::Percentage, 1000, code: '  summer ');

    expect($coupon->code)->toBe('SUMMER');
    $this->assertDatabaseHas('coupons', ['code' => 'SUMMER']);
});

it('stores a code written straight to the model in its canonical form', function (): void {
    $coupon = Coupon::factory()->create(['code' => ' welcome']);

    expect($coupon->code)->toBe('WELCOME')
        ->and($coupon->fresh()?->code)->toBe('WELCOME');
});

it('finds a coupon whatever the case or the surrounding whitespace', function (string $typed): void {
    $coupon = Coupon::factory()->active()->percentage(2000)->create(['code' => 'SUMMER']);
    $cart = Money::ofMinor(5000, 'EUR');

    expect(Coupons::exists($typed))->toBeTrue()
        ->and(Coupons::find($typed)?->is($coupon))->toBeTrue()
        ->and(Coupons::findOrFail($typed)->is($coupon))->toBeTrue()
        ->and(Coupons::check($typed, $cart))->toBeNull()
        ->and(Coupons::preview($typed, $cart)->minor())->toBe('1000')
        ->and(Coupon::query()->whereCode($typed)->first()?->is($coupon))->toBeTrue()
        ->and(Validator::make(['code' => $typed], ['code' => new Redeemable($cart)])->passes())->toBeTrue()
        ->and(Coupons::redeem($typed, $cart)->discount->minor())->toBe('1000')
        ->and(Coupons::expireAll($typed))->toBe(1);
})->with([
    'lower case' => 'summer',
    'mixed case' => 'Summer',
    'pasted with spaces' => ' SUMMER ',
]);

it('binds a route parameter case-insensitively', function (): void {
    $coupon = Coupon::factory()->create(['code' => 'ROUTE']);

    expect((new Coupon)->resolveRouteBinding(' route ')?->is($coupon))->toBeTrue()
        ->and((new Coupon)->resolveRouteBinding((string) $coupon->getKey(), 'id')?->is($coupon))->toBeTrue();
});

it('refuses a second coupon whose code differs only by case', function (): void {
    Coupons::generate(DiscountType::Percentage, 1000, code: 'SUMMER');

    expect(fn () => Coupons::generate(DiscountType::Percentage, 1000, code: 'summer'))->toThrow(CouponCodeTaken::class)
        ->and(Coupon::query()->count())->toBe(1);
});

it('reports a redeemer history whatever the case of the code', function (): void {
    Coupon::factory()->active()->fixed()->create(['code' => 'HELLO']);
    $customer = Customer::query()->create(['name' => 'Ada']);

    $customer->redeemCoupon('hello', Money::ofMinor(1000, 'USD'));

    expect($customer->hasRedeemed(' Hello '))->toBeTrue();
});

it('matches codes case-insensitively on the fake', function (): void {
    $fake = Coupons::fake();

    Coupons::generate(DiscountType::Percentage, 1000, code: 'Faked');
    Coupons::redeem('faked', Money::ofMinor(1000, 'EUR'));

    expect(Coupons::find('FAKED')?->code)->toBe('FAKED')
        ->and(Coupons::exists(' faked '))->toBeTrue()
        ->and(Coupons::expireAll('faked'))->toBe(1);

    $fake->assertRedeemed('FAKED');
    $fake->assertRedeemed(' faked ');
    $fake->assertNotRedeemed('OTHER');
});

// Regression: an explicit '' was accepted as a code — and then `check('')` said not_found
// while `redeem('')` redeemed it. A blank code is refused on create, and no lookup matches one.
it('refuses a blank explicit code', function (string $blank): void {
    expect(fn () => Coupons::generate(DiscountType::Percentage, 1000, code: $blank))
        ->toThrow(InvalidCouponDefinition::class, 'A coupon code cannot be blank.')
        ->and(Coupon::query()->count())->toBe(0);
})->with(['empty' => '', 'whitespace' => "  \t "]);

it('refuses a blank explicit code on the fake too', function (): void {
    $fake = Coupons::fake();

    expect(fn () => Coupons::generate(DiscountType::Percentage, 1000, code: ' '))->toThrow(InvalidCouponDefinition::class);

    $fake->assertNothingCreated();
});

it('answers a blank code the same way on check and redeem', function (): void {
    expect(Coupons::check(''))->toBe(RedemptionFailureReason::NotFound)
        ->and(Coupons::exists(' '))->toBeFalse()
        ->and(fn () => Coupons::redeem(' ', Money::ofMinor(1000, 'USD')))->toThrow(CouponNotFound::class);
});
