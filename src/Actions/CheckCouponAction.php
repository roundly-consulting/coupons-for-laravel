<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Coupons\Enums\RedemptionFailureReason;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\Support\CouponModel;
use RoundlyConsulting\Coupons\Support\RedemptionGuard;
use RoundlyConsulting\Money\Money;

/**
 * Why a coupon would be refused right now — the first failing reason, in the order
 * redemption checks them — or null when it is redeemable. The pre-checkout twin of
 * RedeemCouponAction: the same RedemptionGuard, but it never throws, locks or writes.
 */
final readonly class CheckCouponAction
{
    public function __construct(
        private RedemptionGuard $guard,
    ) {}

    /**
     * An unknown code reports NotFound. Pass a price to also check the currency lock and
     * minimum spend, and a redeemer to check the per-redeemer cap; omit either to skip them.
     */
    public function execute(Coupon|string $coupon, ?Money $price = null, ?Model $redeemer = null): ?RedemptionFailureReason
    {
        $model = $coupon instanceof Coupon ? $coupon : $this->find($coupon);

        if ($model === null) {
            return RedemptionFailureReason::NotFound;
        }

        return $this->guard->firstFailure($model, $price, $redeemer);
    }

    private function find(string $code): ?Coupon
    {
        if ($code === '') {
            return null;
        }

        return CouponModel::class()::query()->where('code', $code)->first();
    }
}
