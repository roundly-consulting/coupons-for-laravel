<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Support;

use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing coupons from `coupons.model`.
 *
 * Absent config resolves the packaged model; anything else must be that model or a subclass of
 * it, or the toolkit's ModelResolver throws InvalidConfigurationException naming the key — a
 * foreign class is never silently replaced.
 */
final class CouponModel
{
    /**
     * @return class-string<Coupon>
     */
    public static function class(): string
    {
        return ModelResolver::for('coupons.model', Coupon::class);
    }
}
