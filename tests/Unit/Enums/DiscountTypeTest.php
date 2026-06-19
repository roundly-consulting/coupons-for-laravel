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
        ->and(DiscountType::Percentage->value)->toBe('percentage');
});
