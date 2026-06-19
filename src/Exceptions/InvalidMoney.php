<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Exceptions;

/**
 * Thrown when a Money operation receives an incompatible value.
 */
final class InvalidMoney extends CouponException
{
    public static function currencyMismatch(string $left, string $right): self
    {
        return new self("Cannot operate on Money in different currencies: {$left} and {$right}.");
    }
}
