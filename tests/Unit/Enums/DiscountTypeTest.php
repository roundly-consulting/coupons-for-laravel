<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Money\Currency;
use RoundlyConsulting\Money\Enums\DiscountTarget;
use RoundlyConsulting\Money\Enums\DiscountType as MoneyDiscountType;
use RoundlyConsulting\Money\Money;

it('maps a fixed value to a fixed money discount in the given currency', function (): void {
    $discount = DiscountType::Fixed->toDiscount(250, Currency::of('EUR'));

    expect($discount->type())->toBe(MoneyDiscountType::Fixed)
        ->and($discount->fixedAmount()?->equals(Money::ofMinor(250, 'EUR')))->toBeTrue()
        ->and($discount->amountFor(Money::ofMinor(1000, 'EUR'))->minor())->toBe('250')
        ->and($discount->applyTo(Money::ofMinor(1000, 'EUR'))->minor())->toBe('750');
});

it('maps a percentage value in basis points', function (): void {
    $quarter = DiscountType::Percentage->toDiscount(2500, Currency::of('EUR'));
    $eighth = DiscountType::Percentage->toDiscount(1250, Currency::of('EUR'));

    expect($quarter->percent()?->value())->toBe('25')
        ->and($quarter->amountFor(Money::ofMinor(1000, 'EUR'))->minor())->toBe('250')
        ->and($eighth->percent()?->value())->toBe('12.5')
        ->and($eighth->amountFor(Money::ofMinor(999, 'EUR'))->minor())->toBe('125');
});

it('never produces a fixed discount above the price', function (): void {
    expect(DiscountType::Fixed->toDiscount(500, Currency::of('EUR'))->amountFor(Money::ofMinor(100, 'EUR'))->minor())->toBe('100');
});

it('exposes its string backing values', function (): void {
    expect(DiscountType::Fixed->value)->toBe('fixed')
        ->and(DiscountType::Percentage->value)->toBe('percentage')
        ->and(DiscountType::FreeShipping->value)->toBe('free_shipping');
});

it('maps free shipping to a discount on the shipping target', function (): void {
    $discount = DiscountType::FreeShipping->toDiscount(0, Currency::of('EUR'));

    expect($discount->target())->toBe(DiscountTarget::Shipping)
        ->and($discount->amountFor(Money::ofMinor(495, 'EUR'))->minor())->toBe('495');
});

it('caps a percentage discount at the maximum discount', function (): void {
    $discount = DiscountType::Percentage->toDiscount(5000, Currency::of('EUR'), Money::ofMinor(300, 'EUR'));

    expect($discount->cap()?->equals(Money::ofMinor(300, 'EUR')))->toBeTrue()
        ->and($discount->amountFor(Money::ofMinor(1000, 'EUR'))->minor())->toBe('300');
});

it('leaves a percentage discount unchanged below the cap', function (): void {
    expect(DiscountType::Percentage->toDiscount(1000, Currency::of('EUR'), Money::ofMinor(300, 'EUR'))->amountFor(Money::ofMinor(1000, 'EUR'))->minor())->toBe('100');
});

it('caps a fixed discount at the maximum discount', function (): void {
    expect(DiscountType::Fixed->toDiscount(800, Currency::of('EUR'), Money::ofMinor(500, 'EUR'))->amountFor(Money::ofMinor(1000, 'EUR'))->minor())->toBe('500');
});

it('respects the currency exponent of a fixed value', function (): void {
    expect((string) DiscountType::Fixed->toDiscount(500, Currency::of('JPY'))->fixedAmount())->toBe('500 JPY')
        ->and((string) DiscountType::Fixed->toDiscount(500, Currency::of('BHD'))->fixedAmount())->toBe('0.500 BHD');
});

it('exposes a translatable label for each type', function (): void {
    expect(DiscountType::Fixed->label())->toBe('Fixed amount')
        ->and(DiscountType::Percentage->label())->toBe('Percentage')
        ->and(DiscountType::FreeShipping->label())->toBe('Free shipping');
});

it('exposes a translatable description for each type', function (): void {
    expect(DiscountType::Fixed->description())->toBe('Subtracts a fixed amount from the price.')
        ->and(DiscountType::Percentage->description())->toBe('Subtracts a percentage of the price.')
        ->and(DiscountType::FreeShipping->description())->toBe('Marks the order for free shipping.');
});

it('knows which types require a value', function (): void {
    expect(DiscountType::Fixed->requiresValue())->toBeTrue()
        ->and(DiscountType::Percentage->requiresValue())->toBeTrue()
        ->and(DiscountType::FreeShipping->requiresValue())->toBeFalse();
});
