<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\DataTransferObjects\RedemptionResult;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Money\Money;

it('carries the redemption outcome', function (): void {
    $coupon = Coupon::factory()->make();
    $discount = Money::ofMinor(1000, 'EUR');
    $total = Money::ofMinor(4000, 'EUR');

    $result = new RedemptionResult(
        coupon: $coupon,
        discount: $discount,
        total: $total,
        freeShipping: true,
    );

    expect($result->coupon)->toBe($coupon)
        ->and($result->discount)->toBe($discount)
        ->and($result->total)->toBe($total)
        ->and($result->redeemer)->toBeNull()
        ->and($result->freeShipping)->toBeTrue();
});
