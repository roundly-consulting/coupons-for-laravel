<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Coupons\CouponManager;
use RoundlyConsulting\Coupons\DataTransferObjects\CreateCouponData;
use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Coupons\Events\CouponCreated;
use RoundlyConsulting\Coupons\Events\CouponRevoked;
use RoundlyConsulting\Coupons\Exceptions\CouponNotFound;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Money\Money;

beforeEach(function (): void {
    $this->manager = app(CouponManager::class);
});

it('generates and persists a coupon', function (): void {
    $coupon = $this->manager->generate(DiscountType::Percentage, 2000, 'SAVE20', 100);

    expect($coupon)->toBeInstanceOf(Coupon::class)
        ->exists->toBeTrue()
        ->code->toBe('SAVE20')
        ->max_usage->toBe(100);
});

it('generates a coupon with an auto code', function (): void {
    $coupon = $this->manager->generate(DiscountType::Fixed, 500, currency: 'EUR');

    expect($coupon->code)->not->toBeEmpty()
        ->and($coupon->exists)->toBeTrue();
});

it('creates a coupon from a dto', function (): void {
    $coupon = $this->manager->create(CreateCouponData::fixed(Money::ofMinor(250, 'EUR'), 'TEN'));

    expect($coupon->code)->toBe('TEN');
});

it('finds a coupon by code or returns null', function (): void {
    Coupon::factory()->create(['code' => 'FOUND']);

    expect($this->manager->find('FOUND')?->code)->toBe('FOUND')
        ->and($this->manager->find('MISSING'))->toBeNull();
});

it('finds or fails by code', function (): void {
    Coupon::factory()->create(['code' => 'FOUND']);

    expect($this->manager->findOrFail('FOUND')->code)->toBe('FOUND');
});

it('throws when finding or failing an unknown code', function (): void {
    app(CouponManager::class)->findOrFail('MISSING');
})->throws(CouponNotFound::class);

it('redeems a coupon', function (): void {
    Coupon::factory()->percentage(2000)->active()->create(['code' => 'SAVE20']);

    $result = $this->manager->redeem('SAVE20', Money::ofMinor(5000, 'EUR'));

    expect($result->total->minor())->toBe('4000');
});

it('is a shared singleton', function (): void {
    expect(app(CouponManager::class))->toBe(app(CouponManager::class));
});

it('queries only redeemable coupons', function (): void {
    Coupon::factory()->active()->fixed(100, 'EUR')->create(['code' => 'GOOD']);
    Coupon::factory()->active()->expired()->create(['code' => 'OLD']);
    Coupon::factory()->active()->create(['code' => 'MAXED', 'max_usage' => 1, 'usage' => 1]);
    Coupon::factory()->create(['code' => 'INACTIVE', 'activated_at' => null]);

    $codes = $this->manager->redeemable()->pluck('code')->all();

    expect($codes)->toBe(['GOOD']);
});

it('reports whether a coupon code exists', function (): void {
    Coupon::factory()->create(['code' => 'HERE']);

    expect($this->manager->exists('HERE'))->toBeTrue()
        ->and($this->manager->exists('GONE'))->toBeFalse();
});

it('revokes a coupon by expiring it without deleting', function (): void {
    Event::fake([CouponRevoked::class]);
    Coupon::factory()->active()->fixed(100, 'EUR')->create(['code' => 'KILL']);

    $coupon = $this->manager->revoke('KILL');

    expect($coupon->isExpired())->toBeTrue()
        ->and($this->manager->redeemable()->pluck('code')->all())->not->toContain('KILL');

    $this->assertDatabaseHas('coupons', ['code' => 'KILL']);
    Event::assertDispatched(CouponRevoked::class);
});

it('throws when revoking an unknown code', function (): void {
    $this->manager->revoke('MISSING');
})->throws(CouponNotFound::class);

it('creates a coupon quietly without the created event', function (): void {
    Event::fake([CouponCreated::class]);

    $coupon = $this->manager->createQuietly(CreateCouponData::fixed(Money::ofMinor(100, 'EUR'), 'QUIET'));

    expect($coupon->exists)->toBeTrue()
        ->and($coupon->code)->toBe('QUIET');
    $this->assertDatabaseHas('coupons', ['code' => 'QUIET']);
    Event::assertNotDispatched(CouponCreated::class);
});
