<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Coupons\ValueObjects\Money;

it('subtracts a fixed amount in minor units', function (): void {
    $result = DiscountType::Fixed->apply(new Money(1000, 'EUR'), 250);

    expect($result->getAmount())->toBe(750)
        ->and($result->getCurrency())->toBe('EUR');
});

it('subtracts a percentage of the price', function (): void {
    $result = DiscountType::Percentage->apply(new Money(1000, 'EUR'), 25);

    expect($result->getAmount())->toBe(750);
});

it('never produces a negative fixed discount', function (): void {
    $result = DiscountType::Fixed->apply(new Money(100, 'EUR'), 500);

    expect($result->getAmount())->toBe(0);
});

it('exposes its string backing values', function (): void {
    expect(DiscountType::Fixed->value)->toBe('fixed')
        ->and(DiscountType::Percentage->value)->toBe('percentage')
        ->and(DiscountType::FreeShipping->value)->toBe('free_shipping');
});

it('treats free shipping as a zero discount', function (): void {
    expect(DiscountType::FreeShipping->discount(new Money(1000, 'EUR'), 0)->getAmount())->toBe(0)
        ->and(DiscountType::FreeShipping->apply(new Money(1000, 'EUR'), 0)->getAmount())->toBe(1000);
});

it('caps a percentage discount at the maximum discount', function (): void {
    $discount = DiscountType::Percentage->discount(new Money(1000, 'EUR'), 50, maxDiscount: 300);

    expect($discount->getAmount())->toBe(300)
        ->and(DiscountType::Percentage->apply(new Money(1000, 'EUR'), 50, maxDiscount: 300)->getAmount())->toBe(700);
});

it('leaves a percentage discount unchanged below the cap', function (): void {
    expect(DiscountType::Percentage->discount(new Money(1000, 'EUR'), 10, maxDiscount: 300)->getAmount())->toBe(100);
});

it('caps a fixed discount at the maximum discount', function (): void {
    expect(DiscountType::Fixed->discount(new Money(1000, 'EUR'), 800, maxDiscount: 500)->getAmount())->toBe(500);
});
