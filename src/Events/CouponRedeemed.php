<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use RoundlyConsulting\Coupons\DataTransferObjects\RedemptionResult;
use RoundlyConsulting\Coupons\Models\Coupon;

/**
 * Dispatched when a redemption succeeds — after the transaction that persisted it commits
 * (the host's outermost one, when redeem() runs inside it), and never for a redemption that
 * was rolled back.
 */
final class CouponRedeemed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public Coupon $coupon,
        public RedemptionResult $result,
    ) {}
}
