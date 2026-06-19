<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Exceptions;

/**
 * Thrown when no coupon matches a given code.
 */
final class CouponNotFound extends CouponException
{
    public static function forCode(string $code): self
    {
        return new self("No coupon found for code [{$code}].");
    }
}
