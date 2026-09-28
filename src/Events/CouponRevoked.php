<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use RoundlyConsulting\Coupons\Models\Coupon;

/**
 * Dispatched when a coupon is revoked (expired immediately) via `Coupons::revoke()`
 * or `coupons:expire` (once per coupon it expires). Revocation is reversible: it sets
 * expires_at to now rather than deleting. Fires after the surrounding transaction
 * commits, and never for a revocation that was rolled back.
 */
final class CouponRevoked implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public Coupon $coupon) {}
}
