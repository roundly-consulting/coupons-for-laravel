<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use RoundlyConsulting\Coupons\Models\Coupon;

/**
 * Dispatched exactly once, when a successful redemption consumes the final
 * available use of a capped coupon (post-increment usage equals max_usage) — after
 * CouponRedeemed, once the redemption's transaction commits.
 */
final class CouponExhausted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public Coupon $coupon) {}
}
