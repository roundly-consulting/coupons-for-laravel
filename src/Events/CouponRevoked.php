<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Events;

use Illuminate\Foundation\Events\Dispatchable;
use RoundlyConsulting\Coupons\Models\Coupon;

/**
 * Dispatched when a coupon is revoked (expired immediately) via `Coupons::revoke()`
 * or `coupons:expire` (once per coupon it expires). Revocation is reversible: it sets
 * expires_at to now rather than deleting.
 */
final class CouponRevoked
{
    use Dispatchable;

    public function __construct(public Coupon $coupon) {}
}
