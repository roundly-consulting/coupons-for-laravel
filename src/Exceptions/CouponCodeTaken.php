<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Exceptions;

use Throwable;

/**
 * Thrown when an explicit code is already held by a live (not soft-deleted) coupon. A code a
 * soft-deleted coupon holds is free to use again.
 */
final class CouponCodeTaken extends CouponException
{
    public static function forCode(string $code, ?Throwable $previous = null): self
    {
        return new self("Coupon code [{$code}] is already taken.", previous: $previous);
    }
}
