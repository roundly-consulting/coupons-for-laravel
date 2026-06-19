<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Exceptions;

/**
 * Thrown when a coupon is past its expiry or not yet active.
 */
final class CouponExpired extends CouponNotRedeemable
{
    public static function forCode(string $code): self
    {
        return new self("Coupon [{$code}] is expired or not yet active.");
    }
}
