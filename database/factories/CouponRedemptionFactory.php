<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\Models\CouponRedemption;

/** @extends Factory<CouponRedemption> */
final class CouponRedemptionFactory extends Factory
{
    protected $model = CouponRedemption::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'coupon_id' => Coupon::factory(),
            'redeemer_type' => null,
            'redeemer_id' => null,
            'amount_discounted' => $this->faker->numberBetween(100, 1000),
            'currency' => 'EUR',
        ];
    }
}
