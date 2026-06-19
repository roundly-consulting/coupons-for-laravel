<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\ValueObjects;

use NumberFormatter;
use RoundlyConsulting\Coupons\Exceptions\InvalidMoney;
use Stringable;

/**
 * Immutable money value object: an integer amount in the currency's minor unit
 * (e.g. cents) plus an ISO 4217 currency code.
 *
 * Reimplemented natively in-package so the runtime dependency stays Laravel-only,
 * replacing the previous external money library.
 */
final readonly class Money implements Stringable
{
    public string $currency;

    public function __construct(
        public int $amount,
        string $currency,
    ) {
        $this->currency = strtoupper($currency);
    }

    /**
     * A zero amount in the given currency.
     */
    public static function zero(string $currency): self
    {
        return new self(0, $currency);
    }

    /**
     * Build from a major-unit amount (e.g. 10.50 EUR), assuming two minor
     * digits — see the README note on the minor-unit convention.
     */
    public static function fromMajor(float $amount, string $currency): self
    {
        return new self((int) round($amount * 100), $currency);
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount
            && $this->currency === $other->currency;
    }

    /**
     * Add another amount in the same currency, returning a new Money.
     *
     * @throws InvalidMoney when the currencies differ.
     */
    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    /**
     * Subtract another amount in the same currency, returning a new Money.
     * The result is clamped at zero so a discount never produces a negative total.
     *
     * @throws InvalidMoney when the currencies differ.
     */
    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self(max(0, $this->amount - $other->amount), $this->currency);
    }

    /**
     * Multiply the amount by a ratio, rounding to the nearest minor unit.
     */
    public function multiply(float $multiplier): self
    {
        return new self((int) round($this->amount * $multiplier), $this->currency);
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw InvalidMoney::currencyMismatch($this->currency, $other->currency);
        }
    }

    /**
     * Format the amount for display, converting from minor units to the major
     * unit and applying the locale's currency formatting.
     *
     * Amounts are assumed to use two minor digits (÷100), which covers the
     * common ISO 4217 currencies; see the README note. Falls back to a plain
     * "CODE 0.00" rendering when the intl formatter cannot format the value
     * (e.g. an invalid locale or a currency code the formatter rejects).
     */
    public function format(?string $locale = null): string
    {
        $formatter = new NumberFormatter(
            $locale ?? 'en_US',
            NumberFormatter::CURRENCY,
        );

        $formatted = $formatter->formatCurrency(
            $this->amount / 100,
            $this->currency,
        );

        if ($formatted === false) {
            return sprintf('%s %0.2f', $this->currency, $this->amount / 100);
        }

        return $formatted;
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
