<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Money\Money;

/** @extends Factory<Coupon> */
final class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        /** @var DiscountType $type */
        $type = $this->faker->randomElement(DiscountType::cases());

        return [
            'code' => mb_strtoupper($this->faker->unique()->bothify('??##??')),
            'type' => $type,
            // Fixed: minor units; Percentage: basis points (1..50 %).
            'value' => match ($type) {
                DiscountType::Fixed => $this->faker->numberBetween(100, 1000),
                DiscountType::Percentage => $this->faker->numberBetween(100, 5000),
                DiscountType::FreeShipping => 0,
            },
            // A fixed coupon must be locked to a currency.
            'currency' => $type === DiscountType::Fixed ? self::defaultCurrency() : null,
            'usage' => 0,
            'max_usage' => 0,
        ];
    }

    public function active(): self
    {
        return $this->state(fn (array $attributes): array => [
            'activated_at' => now()->subHour(),
        ]);
    }

    public function expired(): self
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subHour(),
        ]);
    }

    /**
     * A fixed coupon of `$value` minor units, locked to `$currency` (default: the configured
     * default currency).
     */
    public function fixed(int $value = 100, ?string $currency = null): self
    {
        return $this->state(fn (array $attributes): array => [
            'type' => DiscountType::Fixed,
            'value' => $value,
            'currency' => mb_strtoupper($currency ?? self::defaultCurrency()),
        ]);
    }

    /**
     * A percentage coupon of `$basisPoints` (1000 = 10 %). Unlocked unless a currency is set.
     */
    public function percentage(int $basisPoints = 1000): self
    {
        return $this->state(fn (array $attributes): array => [
            'type' => DiscountType::Percentage,
            'value' => $basisPoints,
            'currency' => null,
        ]);
    }

    public function freeShipping(): self
    {
        return $this->state(fn (array $attributes): array => [
            'type' => DiscountType::FreeShipping,
            'value' => 0,
            'currency' => null,
        ]);
    }

    /**
     * A percentage coupon capped at `$maxDiscount` (default 5.00 in the default currency),
     * locked to the cap's currency.
     */
    public function cappedPercentage(int $basisPoints = 2500, ?Money $maxDiscount = null): self
    {
        return $this->state(function (array $attributes) use ($basisPoints, $maxDiscount): array {
            $cap = $maxDiscount ?? Money::ofMinor(500, self::defaultCurrency());

            return [
                'type' => DiscountType::Percentage,
                'value' => $basisPoints,
                'currency' => $cap->currency()->code,
                'max_discount' => $cap,
            ];
        });
    }

    /**
     * A minimum spend, locking the coupon to its currency (`currency` is set first so the
     * shared currency column is never re-denominated).
     */
    public function withMinimumSpend(Money $minimumSpend): self
    {
        return $this->state(fn (array $attributes): array => [
            'currency' => $minimumSpend->currency()->code,
            'minimum_spend' => $minimumSpend,
        ]);
    }

    public function forCurrency(string $currency): self
    {
        return $this->state(fn (array $attributes): array => [
            'currency' => mb_strtoupper($currency),
        ]);
    }

    private static function defaultCurrency(): string
    {
        $currency = config('coupons.default_currency', 'USD');

        return is_string($currency) && $currency !== '' ? $currency : 'USD';
    }
}
