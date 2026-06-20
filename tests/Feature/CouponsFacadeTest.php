<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Coupons\DataTransferObjects\CreateCouponData;
use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Coupons\Events\CouponCreated;
use RoundlyConsulting\Coupons\Facades\Coupons;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\ValueObjects\Money;

it('generates a coupon through the facade', function (): void {
    $coupon = Coupons::generate(DiscountType::Fixed, 500, 'FIVE');

    expect($coupon)->toBeInstanceOf(Coupon::class)
        ->code->toBe('FIVE');
});

it('finds a coupon through the facade', function (): void {
    Coupon::factory()->create(['code' => 'HELLO']);

    expect(Coupons::find('HELLO')?->code)->toBe('HELLO');
});

it('redeems a coupon through the facade', function (): void {
    Coupon::factory()->fixed(500)->active()->create(['code' => 'FIVE']);

    $result = Coupons::redeem('FIVE', new Money(5000, 'EUR'));

    expect($result->total->getAmount())->toBe(4500);
});

it('queries redeemable coupons through the facade', function (): void {
    Coupon::factory()->active()->fixed()->create(['code' => 'GOOD']);
    Coupon::factory()->active()->expired()->create(['code' => 'OLD']);

    expect(Coupons::redeemable()->pluck('code')->all())->toBe(['GOOD']);
});

it('checks existence through the facade', function (): void {
    Coupon::factory()->create(['code' => 'HERE']);

    expect(Coupons::exists('HERE'))->toBeTrue()
        ->and(Coupons::exists('GONE'))->toBeFalse();
});

it('revokes a coupon through the facade', function (): void {
    Coupon::factory()->active()->fixed()->create(['code' => 'KILL']);

    expect(Coupons::revoke('KILL')->isExpired())->toBeTrue();
});

it('creates quietly through the facade', function (): void {
    Event::fake([CouponCreated::class]);

    Coupons::createQuietly(new CreateCouponData(DiscountType::Fixed, 100, 'QUIET'));

    Event::assertNotDispatched(CouponCreated::class);
});
