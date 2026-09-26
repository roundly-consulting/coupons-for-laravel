<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Exceptions;

/**
 * Thrown when a `coupons.*` config value is unusable. Messages name the key, never its
 * value: the code alphabet is the key space every generated coupon code is drawn from.
 */
final class InvalidCouponConfiguration extends CouponException
{
    public static function invalidCharset(string $reason): self
    {
        return new self("Configuration value [coupons.code.charset] {$reason}.");
    }

    public static function codeSpaceExhausted(int $attempts): self
    {
        return new self("Could not generate a unique coupon code in {$attempts} attempts: the code space set by [coupons.code.length] and [coupons.code.charset] is nearly exhausted. Widen it, or pass an explicit code.");
    }
}
