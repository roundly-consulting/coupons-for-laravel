<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\Exceptions\InvalidMoney;
use RoundlyConsulting\Coupons\ValueObjects\Money;

it('exposes the amount and uppercased currency', function (): void {
    $money = new Money(1000, 'eur');

    expect($money->getAmount())->toBe(1000)
        ->and($money->getCurrency())->toBe('EUR');
});

it('compares two money instances for equality', function (): void {
    expect((new Money(1000, 'EUR'))->equals(new Money(1000, 'EUR')))->toBeTrue()
        ->and((new Money(1000, 'EUR'))->equals(new Money(999, 'EUR')))->toBeFalse()
        ->and((new Money(1000, 'EUR'))->equals(new Money(1000, 'USD')))->toBeFalse();
});

it('adds and subtracts amounts in the same currency', function (): void {
    $money = new Money(1000, 'EUR');

    expect($money->add(new Money(250, 'EUR'))->getAmount())->toBe(1250)
        ->and($money->subtract(new Money(250, 'EUR'))->getAmount())->toBe(750);
});

it('clamps a subtraction at zero', function (): void {
    $money = new Money(100, 'EUR');

    expect($money->subtract(new Money(500, 'EUR'))->getAmount())->toBe(0);
});

it('multiplies and rounds to the nearest minor unit', function (): void {
    expect((new Money(1000, 'EUR'))->multiply(0.255)->getAmount())->toBe(255)
        ->and((new Money(101, 'EUR'))->multiply(0.5)->getAmount())->toBe(51);
});

it('reports a zero amount', function (): void {
    expect((new Money(0, 'EUR'))->isZero())->toBeTrue()
        ->and((new Money(1, 'EUR'))->isZero())->toBeFalse();
});

it('throws when operating across currencies', function (): void {
    (new Money(1000, 'EUR'))->add(new Money(1000, 'USD'));
})->throws(InvalidMoney::class);

it('formats using the intl currency formatter', function (): void {
    expect((new Money(1000, 'EUR'))->format('en_US'))->toContain('10');
});

it('renders an amount for an unknown currency code', function (): void {
    expect((new Money(1000, 'ZZZ'))->format())
        ->toContain('ZZZ')
        ->toContain('10.00');
});

it('casts to a string via the formatter', function (): void {
    expect((string) new Money(1000, 'USD'))->toContain('10');
});

it('builds a zero amount in a currency', function (): void {
    expect(Money::zero('eur'))
        ->getAmount()->toBe(0)
        ->getCurrency()->toBe('EUR');
});

it('builds from a major-unit amount', function (): void {
    expect(Money::fromMajor(10.50, 'eur'))
        ->getAmount()->toBe(1050)
        ->getCurrency()->toBe('EUR');
});
