<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Enums;

use RoundlyConsulting\Coupons\ValueObjects\Money;

/**
 * How a coupon's value is applied to a price.
 *
 * Reimplemented natively in-package (replaces the discount type + applicators
 * that previously came from an external money library) so the runtime stays
 * Laravel-only.
 */
enum DiscountType: string
{
    /** Subtract a fixed amount, expressed in the price's minor units. */
    case Fixed = 'fixed';

    /** Subtract a percentage of the price, where the value is whole percent (e.g. 25 = 25%). */
    case Percentage = 'percentage';

    /**
     * Apply this discount type to the given price.
     *
     * @param  Money  $price  The price to discount.
     * @param  int  $value  The coupon value: minor units for Fixed, whole percent for Percentage.
     */
    public function apply(Money $price, int $value): Money
    {
        return match ($this) {
            self::Fixed => $price->subtract(new Money($value, $price->getCurrency())),
            self::Percentage => $price->subtract($price->multiply($value / 100)),
        };
    }
}
