<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use RoundlyConsulting\Coupons\Models\Coupon;

/**
 * Dispatched when a coupon is created (not via `createQuietly()`) — after the surrounding
 * transaction commits, and never for a creation that was rolled back.
 */
final class CouponCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public Coupon $coupon) {}
}
