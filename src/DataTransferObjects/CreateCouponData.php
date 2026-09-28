<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\DataTransferObjects;

use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Coupons\Exceptions\InvalidCouponDefinition;
use RoundlyConsulting\Money\Currency;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Money\Percentage;

final readonly class CreateCouponData
{
    /**
     * @param  int  $value  Fixed: minor units of `$currency`; Percentage: basis points (2500 = 25 %); FreeShipping: 0.
     * @param  Currency|null  $currency  Required for Fixed coupons and whenever a minimum spend or cap is set.
     */
    public function __construct(
        public DiscountType $type,
        public int $value,
        public ?Currency $currency = null,
        public ?string $code = null,
        public int $maxUsage = 0,
        public ?Money $minimumSpend = null,
        public ?Money $maxDiscount = null,
    ) {}

    /**
     * A fixed amount off, locked to the amount's currency. Throws AmountOverflow for an
     * amount beyond int64 minor units (the `value` column is a bigint).
     */
    public static function fixed(Money $amount, ?string $code = null, int $maxUsage = 0, ?Money $minimumSpend = null): self
    {
        return new self(
            type: DiscountType::Fixed,
            value: $amount->minorInt(),
            currency: $amount->currency(),
            code: $code,
            maxUsage: $maxUsage,
            minimumSpend: $minimumSpend,
        );
    }

    /**
     * A percentage off: `25` or `'12.5'` percent, or a Percentage. A cap locks the coupon to
     * the cap's currency.
     */
    public static function percentage(
        Percentage|int|string $percent,
        ?string $code = null,
        ?Money $maxDiscount = null,
        int $maxUsage = 0,
        ?Money $minimumSpend = null,
    ): self {
        $percent = $percent instanceof Percentage ? $percent : Percentage::of($percent);

        return new self(
            type: DiscountType::Percentage,
            value: $percent->basisPoints(),
            currency: $maxDiscount?->currency() ?? $minimumSpend?->currency(),
            code: $code,
            maxUsage: $maxUsage,
            minimumSpend: $minimumSpend,
            maxDiscount: $maxDiscount,
        );
    }

    public static function freeShipping(?string $code = null, int $maxUsage = 0, ?Money $minimumSpend = null): self
    {
        return new self(
            type: DiscountType::FreeShipping,
            value: 0,
            currency: $minimumSpend?->currency(),
            code: $code,
            maxUsage: $maxUsage,
            minimumSpend: $minimumSpend,
        );
    }

    /**
     * Refuse a definition that breaks a coupon invariant. `$code` is the code the coupon will
     * hold (generated or normalised), named in the error.
     *
     * @throws InvalidCouponDefinition when the code is blank, a fixed coupon has no currency
     *                                 or a negative value, or a percentage is outside
     *                                 0..10000 basis points.
     */
    public function assertValid(string $code): void
    {
        if ($code === '') {
            throw InvalidCouponDefinition::blankCode();
        }

        if ($this->type === DiscountType::Fixed) {
            if ($this->lockedCurrency() === null) {
                throw InvalidCouponDefinition::fixedWithoutCurrency($code);
            }

            if ($this->value < 0) {
                throw InvalidCouponDefinition::negativeValue($this->value);
            }
        }

        if ($this->type === DiscountType::Percentage && ($this->value < 0 || $this->value > 10_000)) {
            throw InvalidCouponDefinition::percentOutOfRange($this->value);
        }
    }

    /**
     * The currency the coupon is locked to: the explicit one, else the currency of its
     * minimum spend or cap, else none.
     */
    public function lockedCurrency(): ?Currency
    {
        return $this->currency ?? $this->minimumSpend?->currency() ?? $this->maxDiscount?->currency();
    }
}
