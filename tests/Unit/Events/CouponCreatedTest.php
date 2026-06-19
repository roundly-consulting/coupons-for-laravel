<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\Events\CouponCreated;
use RoundlyConsulting\Coupons\Models\Coupon;

it('carries the created coupon', function (): void {
    $coupon = Coupon::factory()->make();

    $event = new CouponCreated($coupon);

    expect($event->coupon)->toBe($coupon);
});
