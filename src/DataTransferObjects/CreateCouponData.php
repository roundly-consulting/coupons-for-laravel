<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\DataTransferObjects;

use RoundlyConsulting\Coupons\Enums\DiscountType;
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
     * The currency the coupon is locked to: the explicit one, else the currency of its
     * minimum spend or cap, else none.
     */
    public function lockedCurrency(): ?Currency
    {
        return $this->currency ?? $this->minimumSpend?->currency() ?? $this->maxDiscount?->currency();
    }
}
