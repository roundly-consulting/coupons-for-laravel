<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Exceptions;

/**
 * Thrown when a coupon's definition breaks an invariant: a blank code, a fixed coupon
 * without a currency, a percentage outside 0..100 %, or a negative fixed amount.
 */
final class InvalidCouponDefinition extends CouponException
{
    public static function blankCode(): self
    {
        return new self('A coupon code cannot be blank.');
    }

    public static function fixedWithoutCurrency(string $code): self
    {
        return new self("Fixed coupon [{$code}] must be locked to a currency: its value is an amount of minor units of that currency.");
    }

    public static function percentOutOfRange(int $basisPoints): self
    {
        return new self("A percentage coupon's value is in basis points and must be between 0 and 10000 (0..100 %), {$basisPoints} given.");
    }

    public static function negativeValue(int $value): self
    {
        return new self("A fixed coupon's value is an amount of minor units and cannot be negative, {$value} given.");
    }
}
