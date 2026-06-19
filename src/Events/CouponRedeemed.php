<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Events;

use Illuminate\Foundation\Events\Dispatchable;
use RoundlyConsulting\Coupons\DataTransferObjects\RedemptionResult;
use RoundlyConsulting\Coupons\Models\Coupon;

final class CouponRedeemed
{
    use Dispatchable;

    public function __construct(
        public Coupon $coupon,
        public RedemptionResult $result,
    ) {}
}
