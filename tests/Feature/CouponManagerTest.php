<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\CouponManager;
use RoundlyConsulting\Coupons\DataTransferObjects\CreateCouponData;
use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Coupons\Exceptions\CouponNotFound;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\ValueObjects\Money;

beforeEach(function (): void {
    $this->manager = app(CouponManager::class);
});

it('generates and persists a coupon', function (): void {
    $coupon = $this->manager->generate(DiscountType::Percentage, 20, 'SAVE20', 100);

    expect($coupon)->toBeInstanceOf(Coupon::class)
        ->exists->toBeTrue()
        ->code->toBe('SAVE20')
        ->max_usage->toBe(100);
});

it('generates a coupon with an auto code', function (): void {
    $coupon = $this->manager->generate(DiscountType::Fixed, 500);

    expect($coupon->code)->not->toBeEmpty()
        ->and($coupon->exists)->toBeTrue();
});

it('creates a coupon from a dto', function (): void {
    $coupon = $this->manager->create(new CreateCouponData(DiscountType::Fixed, 250, 'TEN'));

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
    Coupon::factory()->percentage(20)->active()->create(['code' => 'SAVE20']);

    $result = $this->manager->redeem('SAVE20', new Money(5000, 'EUR'));

    expect($result->total->getAmount())->toBe(4000);
});

it('is a shared singleton', function (): void {
    expect(app(CouponManager::class))->toBe(app(CouponManager::class));
});
