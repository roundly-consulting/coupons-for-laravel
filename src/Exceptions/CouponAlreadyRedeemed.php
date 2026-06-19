<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Exceptions;

/**
 * Thrown when a redeemer has reached their per-redeemer usage cap.
 */
final class CouponAlreadyRedeemed extends CouponNotRedeemable
{
    public static function forCode(string $code): self
    {
        return new self("Coupon [{$code}] has already been redeemed the maximum number of times by this redeemer.");
    }
}
