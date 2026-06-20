<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Events;

use Illuminate\Foundation\Events\Dispatchable;
use RoundlyConsulting\Coupons\Models\Coupon;

/**
 * Dispatched exactly once, when a successful redemption consumes the final
 * available use of a capped coupon (post-increment usage equals max_usage).
 */
final class CouponExhausted
{
    use Dispatchable;

    public function __construct(public Coupon $coupon) {}
}
