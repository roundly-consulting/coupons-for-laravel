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
     * A marker type carrying free-shipping intent. The package owns no cart or
     * shipping line, so it discounts nothing from the price — the host zeroes
     * its own shipping total when the redemption reports free shipping.
     */
    case FreeShipping = 'free_shipping';

    /**
     * Apply this discount type to the given price, returning the new total.
     *
     * @param  Money  $price  The price to discount.
     * @param  int  $value  The coupon value: minor units for Fixed, whole percent for Percentage.
     * @param  int|null  $maxDiscount  Optional cap (minor units) on the discount amount.
     */
    public function apply(Money $price, int $value, ?int $maxDiscount = null): Money
    {
        return $price->subtract($this->discount($price, $value, $maxDiscount));
    }

    /**
     * The amount saved off the price (in minor units), respecting an optional cap.
     *
     * @param  Money  $price  The price the discount applies to.
     * @param  int  $value  The coupon value: minor units for Fixed, whole percent for Percentage.
     * @param  int|null  $maxDiscount  Optional cap (minor units) on the discount amount.
     */
    public function discount(Money $price, int $value, ?int $maxDiscount = null): Money
    {
        $discount = match ($this) {
            self::Fixed => new Money($value, $price->getCurrency()),
            self::Percentage => $price->multiply($value / 100),
            self::FreeShipping => Money::zero($price->getCurrency()),
        };

        if ($maxDiscount !== null && $discount->getAmount() > $maxDiscount) {
            return new Money($maxDiscount, $price->getCurrency());
        }

        return $discount;
    }
}
