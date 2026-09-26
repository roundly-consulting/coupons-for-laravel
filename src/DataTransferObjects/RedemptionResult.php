<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\DataTransferObjects;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Money\Money;

final readonly class RedemptionResult
{
    public function __construct(
        public Coupon $coupon,
        public Money $discount,
        public Money $total,
        public ?Model $redeemer = null,
        public bool $freeShipping = false,
    ) {}
}
