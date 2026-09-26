<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\DataTransferObjects\CreateCouponData;
use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Money\Exceptions\AmountOverflow;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Money\Percentage;

it('holds the coupon creation payload with defaults', function (): void {
    $data = new CreateCouponData(type: DiscountType::Fixed, value: 100);

    expect($data->type)->toBe(DiscountType::Fixed)
        ->and($data->value)->toBe(100)
        ->and($data->code)->toBeNull()
        ->and($data->maxUsage)->toBe(0)
        ->and($data->currency)->toBeNull()
        ->and($data->minimumSpend)->toBeNull()
        ->and($data->maxDiscount)->toBeNull()
        ->and($data->lockedCurrency())->toBeNull();
});

it('accepts a custom code and max usage', function (): void {
    $data = new CreateCouponData(
        type: DiscountType::Percentage,
        value: 5000,
        code: 'PAYHALF',
        maxUsage: 5,
    );

    expect($data->code)->toBe('PAYHALF')
        ->and($data->maxUsage)->toBe(5);
});

it('builds a fixed coupon locked to the amount currency', function (): void {
    $data = CreateCouponData::fixed(Money::ofMinor(500, 'JPY'), 'YEN', maxUsage: 2, minimumSpend: Money::ofMinor(3000, 'JPY'));

    expect($data->type)->toBe(DiscountType::Fixed)
        ->and($data->value)->toBe(500)
        ->and($data->currency?->code)->toBe('JPY')
        ->and($data->code)->toBe('YEN')
        ->and($data->maxUsage)->toBe(2)
        ->and($data->minimumSpend?->minor())->toBe('3000');
});

it('refuses a fixed amount beyond int64 minor units', function (): void {
    CreateCouponData::fixed(Money::ofMinor('100000000000000000000', 'EUR'));
})->throws(AmountOverflow::class);

it('stores percentages as basis points', function (): void {
    expect(CreateCouponData::percentage(25)->value)->toBe(2500)
        ->and(CreateCouponData::percentage('12.5')->value)->toBe(1250)
        ->and(CreateCouponData::percentage(Percentage::fromBasisPoints(333))->value)->toBe(333)
        ->and(CreateCouponData::percentage(25)->currency)->toBeNull();
});

it('locks a capped percentage to the cap currency', function (): void {
    $data = CreateCouponData::percentage(10, 'TEN', Money::ofMinor(500, 'EUR'));

    expect($data->currency?->code)->toBe('EUR')
        ->and($data->maxDiscount?->minor())->toBe('500')
        ->and($data->lockedCurrency()?->code)->toBe('EUR');
});

it('builds a free-shipping coupon', function (): void {
    $data = CreateCouponData::freeShipping('SHIP');

    expect($data->type)->toBe(DiscountType::FreeShipping)
        ->and($data->value)->toBe(0)
        ->and($data->currency)->toBeNull()
        ->and($data->code)->toBe('SHIP');
});

it('derives the lock from the minimum spend or cap', function (): void {
    expect((new CreateCouponData(DiscountType::Percentage, 100, maxDiscount: Money::ofMinor(1, 'USD')))->lockedCurrency()?->code)->toBe('USD')
        ->and((new CreateCouponData(DiscountType::Percentage, 100, minimumSpend: Money::ofMinor(1, 'GBP')))->lockedCurrency()?->code)->toBe('GBP');
});
