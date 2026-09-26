<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Enums;

use RoundlyConsulting\Money\Currency;
use RoundlyConsulting\Money\Discounts\Discount;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Money\Percentage;

/**
 * How a coupon's value is applied to a price. The discount math itself belongs to
 * money-for-laravel: {@see self::toDiscount()} turns a coupon's stored value into a money
 * {@see Discount}.
 */
enum DiscountType: string
{
    /** Subtract a fixed amount, stored as minor units of the coupon's (required) currency. */
    case Fixed = 'fixed';

    /** Subtract a percentage of the price, stored in basis points (2500 = 25 %, 1250 = 12.5 %). */
    case Percentage = 'percentage';

    /**
     * A marker type carrying free-shipping intent. The package owns no cart or
     * shipping line, so it discounts nothing from the price — the host zeroes
     * its own shipping total when the redemption reports free shipping.
     */
    case FreeShipping = 'free_shipping';

    /**
     * The money Discount for a stored coupon value.
     *
     * @param  int  $value  Fixed: minor units of `$currency`; Percentage: basis points; FreeShipping: ignored.
     * @param  Money|null  $cap  The most the discount may remove.
     */
    public function toDiscount(int $value, Currency $currency, ?Money $cap = null): Discount
    {
        $discount = match ($this) {
            self::Fixed => Discount::fixed(Money::ofMinor($value, $currency)),
            self::Percentage => Discount::percentage(Percentage::fromBasisPoints($value)),
            self::FreeShipping => Discount::freeShipping(),
        };

        return $cap === null ? $discount : $discount->cappedAt($cap);
    }

    /**
     * A short, translatable label for this type, suited to admin UIs.
     */
    public function label(): string
    {
        return (string) trans("coupons::messages.type.{$this->value}.label");
    }

    /**
     * A translatable, human description of what this type does.
     */
    public function description(): string
    {
        return (string) trans("coupons::messages.type.{$this->value}.description");
    }

    /**
     * Whether this type needs a coupon value. Free shipping carries no value;
     * fixed and percentage do.
     */
    public function requiresValue(): bool
    {
        return $this !== self::FreeShipping;
    }
}
