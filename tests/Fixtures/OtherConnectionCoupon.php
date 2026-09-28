<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Tests\Fixtures;

use RoundlyConsulting\Coupons\Models\Coupon;

/**
 * A host coupon model that lives on a non-default connection — the shape a `coupons.model`
 * swap with its own `$connection` produces.
 */
class OtherConnectionCoupon extends Coupon
{
    protected $connection = 'coupons_other';

    protected $table = 'coupons';
}
