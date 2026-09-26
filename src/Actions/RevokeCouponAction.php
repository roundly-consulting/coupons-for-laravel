<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Actions;

use RoundlyConsulting\Coupons\Events\CouponRevoked;
use RoundlyConsulting\Coupons\Models\Coupon;

/**
 * Revoke a coupon: expire it now and fire CouponRevoked. The one path both
 * `Coupons::revoke()` and `coupons:expire` go through, so listeners hear about every
 * revocation. Reversible — the row is kept, only expires_at is set.
 */
final class RevokeCouponAction
{
    public function execute(Coupon $coupon): Coupon
    {
        $coupon->expire()->save();

        CouponRevoked::dispatch($coupon);

        return $coupon;
    }
}
