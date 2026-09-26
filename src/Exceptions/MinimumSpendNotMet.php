<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Exceptions;

use RoundlyConsulting\Money\Money;

/**
 * Thrown when a price falls below the coupon's minimum spend.
 */
final class MinimumSpendNotMet extends CouponNotRedeemable
{
    public static function forCode(string $code, Money $minimumSpend): self
    {
        return new self("Coupon [{$code}] requires a minimum spend of {$minimumSpend}.");
    }
}
