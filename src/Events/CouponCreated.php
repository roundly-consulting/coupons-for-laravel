<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Events;

use Illuminate\Foundation\Events\Dispatchable;
use RoundlyConsulting\Coupons\Models\Coupon;

final class CouponCreated
{
    use Dispatchable;

    public function __construct(public Coupon $coupon) {}
}
