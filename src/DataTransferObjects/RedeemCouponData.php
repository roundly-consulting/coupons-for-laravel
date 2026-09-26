<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\DataTransferObjects;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Money\Money;

final readonly class RedeemCouponData
{
    public function __construct(
        public Coupon|string $coupon,
        public Money $price,
        public ?Model $redeemer = null,
    ) {}
}
