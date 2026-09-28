<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Coupons\CouponManager;
use RoundlyConsulting\Coupons\DataTransferObjects\CreateCouponData;
use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Coupons\Enums\RedemptionFailureReason;
use RoundlyConsulting\Coupons\Events\CouponCreated;
use RoundlyConsulting\Coupons\Events\CouponRedeemed;
use RoundlyConsulting\Coupons\Events\CouponRevoked;
use RoundlyConsulting\Coupons\Exceptions\CouponNotFound;
use RoundlyConsulting\Coupons\Facades\Coupons;
use RoundlyConsulting\Coupons\Handles\CouponCode;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\Tests\Fixtures\Customer;
use RoundlyConsulting\Money\Money;

it('generates a coupon through the facade', function (): void {
    $coupon = Coupons::generate(DiscountType::Fixed, 500, 'FIVE', currency: 'EUR');

    expect($coupon)->toBeInstanceOf(Coupon::class)
        ->code->toBe('FIVE');
});

it('finds a coupon through the facade', function (): void {
    Coupon::factory()->create(['code' => 'HELLO']);

    expect(Coupons::find('HELLO')?->code)->toBe('HELLO');
});

it('redeems a coupon through the facade', function (): void {
    Coupon::factory()->fixed(500, 'EUR')->active()->create(['code' => 'FIVE']);

    $result = Coupons::redeem('FIVE', Money::ofMinor(5000, 'EUR'));

    expect($result->total->minor())->toBe('4500');
});

it('queries redeemable coupons through the facade', function (): void {
    Coupon::factory()->active()->fixed(100, 'EUR')->create(['code' => 'GOOD']);
    Coupon::factory()->active()->expired()->create(['code' => 'OLD']);

    expect(Coupons::redeemable()->pluck('code')->all())->toBe(['GOOD']);
});

it('checks existence through the facade', function (): void {
    Coupon::factory()->create(['code' => 'HERE']);

    expect(Coupons::exists('HERE'))->toBeTrue()
        ->and(Coupons::exists('GONE'))->toBeFalse();
});

it('revokes a coupon through the facade', function (): void {
    Coupon::factory()->active()->fixed(100, 'EUR')->create(['code' => 'KILL']);

    expect(Coupons::revoke('KILL')->isExpired())->toBeTrue();
});

it('creates quietly through the facade', function (): void {
    Event::fake([CouponCreated::class]);

    Coupons::createQuietly(CreateCouponData::fixed(Money::ofMinor(100, 'EUR'), 'QUIET'));

    Event::assertNotDispatched(CouponCreated::class);
});

it('finds or fails through the facade', function (): void {
    Coupon::factory()->create(['code' => 'HELLO']);

    expect(Coupons::findOrFail('HELLO')->code)->toBe('HELLO')
        ->and(fn (): Coupon => Coupons::findOrFail('GONE'))->toThrow(CouponNotFound::class);
});

it('creates from a dto through the facade', function (): void {
    expect(Coupons::create(CreateCouponData::fixed(Money::ofMinor(100, 'EUR'), 'DTO'))->exists)->toBeTrue();
});

it('returns a handle for a code or a model', function (): void {
    $coupon = Coupon::factory()->create(['code' => 'SUMMER']);

    expect(Coupons::code('SUMMER'))->toBeInstanceOf(CouponCode::class)
        ->and(Coupons::code($coupon))->toBeInstanceOf(CouponCode::class);
});

it('checks a code through the handle: why it would be refused, or null', function (): void {
    $customer = Customer::query()->create(['name' => 'Ada']);
    Coupon::factory()->active()->fixed(500, 'EUR')->withMinimumSpend(Money::ofMinor(3000, 'EUR'))->create(['code' => 'SUMMER']);

    expect(Coupons::code('SUMMER')->check(Money::ofMinor(5000, 'EUR'), $customer))->toBeNull()
        ->and(Coupons::code('SUMMER')->check(Money::ofMinor(1000, 'EUR'), $customer))->toBe(RedemptionFailureReason::MinimumSpendNotMet)
        ->and(Coupons::code('SUMMER')->check(Money::ofMinor(5000, 'USD')))->toBe(RedemptionFailureReason::CurrencyMismatch)
        ->and(Coupons::code('NOPE')->check())->toBe(RedemptionFailureReason::NotFound)
        ->and(Coupons::check('SUMMER'))->toBeNull();
});

it('previews a discount through the handle without redeeming', function (): void {
    $coupon = Coupon::factory()->active()->percentage(2000)->create(['code' => 'SUMMER']);

    expect(Coupons::code('SUMMER')->preview(Money::ofMinor(5000, 'EUR'))->minor())->toBe('1000')
        ->and(Coupons::preview($coupon, Money::ofMinor(1000, 'EUR'))->minor())->toBe('200')
        ->and($coupon->fresh()->usage)->toBe(0);
});

it('previews zero on a currency mismatch and throws for an unknown code', function (): void {
    Coupon::factory()->active()->fixed(500, 'EUR')->create(['code' => 'EUR5']);

    expect(Coupons::code('EUR5')->preview(Money::ofMinor(5000, 'USD'))->isZero())->toBeTrue()
        ->and(fn (): Money => Coupons::code('NOPE')->preview(Money::ofMinor(5000, 'EUR')))->toThrow(CouponNotFound::class);
});

it('redeems through the handle', function (): void {
    Event::fake([CouponRedeemed::class]);
    $customer = Customer::query()->create(['name' => 'Ada']);
    Coupon::factory()->active()->fixed(500, 'EUR')->create(['code' => 'SUMMER']);

    $result = Coupons::code('SUMMER')->redeem(Money::ofMinor(5000, 'EUR'), $customer);

    expect($result->total->minor())->toBe('4500')
        ->and($result->redeemer)->toBe($customer)
        ->and($customer->hasRedeemed('SUMMER'))->toBeTrue();
    Event::assertDispatched(CouponRedeemed::class);
});

it('revokes through the handle and by model', function (): void {
    Event::fake([CouponRevoked::class]);
    Coupon::factory()->active()->create(['code' => 'A']);
    $b = Coupon::factory()->active()->create(['code' => 'B']);

    expect(Coupons::code('A')->revoke()->isExpired())->toBeTrue()
        ->and(Coupons::revoke($b)->isExpired())->toBeTrue()
        ->and($b->fresh()->isExpired())->toBeTrue();
    Event::assertDispatchedTimes(CouponRevoked::class, 2);
});

it('refuses to revoke an unknown code through the handle', function (): void {
    Coupons::code('NOPE')->revoke();
})->throws(CouponNotFound::class);

it('expires every live coupon through the facade', function (): void {
    Coupon::factory()->count(2)->active()->create();
    Coupon::factory()->active()->create(['code' => 'ONLY']);

    expect(Coupons::expireAll('ONLY'))->toBe(1)
        ->and(Coupons::expireAll())->toBe(2)
        ->and(Coupons::redeemable()->count())->toBe(0);
});

it('prunes through the facade', function (): void {
    Coupon::factory()->create(['expires_at' => now()->subDays(10)]);

    expect(Coupons::prune(30))->toBe(0)
        ->and(Coupons::prune(7, force: true))->toBe(1)
        ->and(Coupon::withTrashed()->count())->toBe(0);
});

it('serves the same api to an injected manager', function (): void {
    Coupon::factory()->active()->fixed(500, 'EUR')->create(['code' => 'DI']);

    $manager = app(CouponManager::class);

    expect($manager->code('DI')->check(Money::ofMinor(5000, 'EUR')))->toBeNull()
        ->and($manager->code('DI')->redeem(Money::ofMinor(5000, 'EUR'))->total->minor())->toBe('4500');
});

it('routes the model and the redeemer trait through the manager', function (): void {
    $customer = Customer::query()->create(['name' => 'Ada']);
    $coupon = Coupon::factory()->active()->fixed(500, 'EUR')->create(['code' => 'PATH']);

    expect($coupon->isRedeemableBy($customer, Money::ofMinor(5000, 'EUR')))->toBeTrue()
        ->and($coupon->redeemBy($customer, Money::ofMinor(5000, 'EUR'))->total->minor())->toBe('4500')
        ->and($customer->redeemCoupon('PATH', Money::ofMinor(2000, 'EUR'))->total->minor())->toBe('1500')
        ->and($coupon->fresh()->usage)->toBe(2);
});
