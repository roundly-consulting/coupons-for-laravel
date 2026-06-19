<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Exceptions;

/**
 * Thrown when a coupon is locked to a currency that differs from the price.
 */
final class CurrencyMismatch extends CouponNotRedeemable
{
    public static function forCode(string $code, string $expected, string $actual): self
    {
        return new self("Coupon [{$code}] is locked to {$expected} and cannot apply to {$actual}.");
    }
}
