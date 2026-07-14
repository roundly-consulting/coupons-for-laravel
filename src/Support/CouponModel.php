<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Support;

use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing coupons from `coupons.model`.
 *
 * The toolkit's ModelResolver validates that the configured value is a real
 * Eloquent model; anything that isn't a Coupon (so it can't answer the
 * package's queries, scopes, or redemption checks) falls back to the packaged
 * model.
 */
final class CouponModel
{
    /**
     * @return class-string<Coupon>
     */
    public static function class(): string
    {
        $model = ModelResolver::for('coupons.model', Coupon::class);

        return is_a($model, Coupon::class, true) ? $model : Coupon::class;
    }
}
