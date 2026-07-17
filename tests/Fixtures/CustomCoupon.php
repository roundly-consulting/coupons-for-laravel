<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Tests\Fixtures;

use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Testing\Fixtures\Concerns\CountsCreations;

/**
 * The host subclass `coupons.model` invites, used to prove the seam is real.
 *
 * `CountsCreations` is what makes the proof independent: it counts rows created as *this
 * exact class*, so a coupon created as the packaged Coupon — which would still pass an
 * `instanceof` check while firing none of the host's model events (permissions #31) —
 * cannot be mistaken for an honoured swap.
 */
class CustomCoupon extends Coupon
{
    use CountsCreations;

    protected $table = 'coupons';
}
