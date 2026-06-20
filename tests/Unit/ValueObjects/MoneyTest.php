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

it('falls back to a plain rendering for an unformattable currency code', function (): void {
    // A non-ISO 4217 code (not three letters) makes the intl formatter return
    // false, exercising the plain "CODE 0.00" fallback.
    expect((new Money(1000, 'XX'))->format())
        ->toBe('XX 10.00');
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

it('reports whether the amount is positive', function (): void {
    expect((new Money(1, 'USD'))->isPositive())->toBeTrue()
        ->and((new Money(0, 'USD'))->isPositive())->toBeFalse()
        ->and(Money::zero('USD')->isPositive())->toBeFalse();
});

it('computes a whole-percent share', function (): void {
    expect((new Money(2000, 'USD'))->percentageOf(25))
        ->getAmount()->toBe(500)
        ->getCurrency()->toBe('USD');

    expect((new Money(1000, 'USD'))->percentageOf(33)->getAmount())->toBe(330);
});

it('allocates an even split distributing the remainder', function (): void {
    $parts = (new Money(100, 'USD'))->allocate([1, 1, 1]);

    expect(array_map(fn (Money $m): int => $m->getAmount(), $parts))->toBe([34, 33, 33])
        ->and(array_sum(array_map(fn (Money $m): int => $m->getAmount(), $parts)))->toBe(100);
});

it('allocates a weighted split', function (): void {
    $parts = (new Money(100, 'USD'))->allocate([7, 3]);

    expect(array_map(fn (Money $m): int => $m->getAmount(), $parts))->toBe([70, 30]);
});

it('allocates an odd amount preserving the sum', function (): void {
    $parts = (new Money(101, 'USD'))->allocate([1, 1]);

    expect(array_map(fn (Money $m): int => $m->getAmount(), $parts))->toBe([51, 50]);
});

it('allocates a single bucket as the whole amount', function (): void {
    $parts = (new Money(100, 'USD'))->allocate([1]);

    expect($parts)->toHaveCount(1)
        ->and($parts[0]->getAmount())->toBe(100);
});

it('preserves the currency across allocated parts', function (): void {
    foreach ((new Money(100, 'eur'))->allocate([1, 1]) as $part) {
        expect($part->getCurrency())->toBe('EUR');
    }
});

it('preserves the sum across many allocations', function (): void {
    $cases = [[5, 3, 2], [1, 1, 1, 1], [9, 1], [10, 20, 30], [1, 2, 3, 4, 5]];

    foreach ($cases as $ratios) {
        $parts = (new Money(997, 'USD'))->allocate($ratios);

        expect(array_sum(array_map(fn (Money $m): int => $m->getAmount(), $parts)))->toBe(997);
    }
});

it('throws when allocating across a non-positive ratio total', function (): void {
    (new Money(100, 'USD'))->allocate([0, 0]);
})->throws(InvalidMoney::class);
