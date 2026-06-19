<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Coupons\Models\Coupon;

/** @extends Factory<Coupon> */
final class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => mb_strtoupper($this->faker->unique()->bothify('??##??')),
            'type' => $this->faker->randomElement(DiscountType::cases()),
            'value' => $this->faker->numberBetween(100, 1000),
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

    public function fixed(int $value = 100): self
    {
        return $this->state(fn (array $attributes): array => [
            'type' => DiscountType::Fixed,
            'value' => $value,
        ]);
    }

    public function percentage(int $value = 10): self
    {
        return $this->state(fn (array $attributes): array => [
            'type' => DiscountType::Percentage,
            'value' => $value,
        ]);
    }
}
