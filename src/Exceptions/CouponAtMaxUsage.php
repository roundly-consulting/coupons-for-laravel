<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Exceptions;

/**
 * Thrown when a coupon has reached its global usage cap.
 */
final class CouponAtMaxUsage extends CouponNotRedeemable
{
    public static function forCode(string $code): self
    {
        return new self("Coupon [{$code}] has reached its maximum usage.");
    }
}
