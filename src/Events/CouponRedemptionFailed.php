<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use RoundlyConsulting\Coupons\Enums\RedemptionFailureReason;

/**
 * Dispatched on every rejected redemption attempt that flows through the
 * redemption action, carrying the attempted code, the failing reason, and the
 * redeemer (when one was supplied). Useful for fraud and analytics hooks.
 */
final class CouponRedemptionFailed
{
    use Dispatchable;

    public function __construct(
        public string $code,
        public RedemptionFailureReason $reason,
        public ?Model $redeemer = null,
    ) {}
}
