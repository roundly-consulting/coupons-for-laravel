<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use RoundlyConsulting\Coupons\CouponManager;
use RoundlyConsulting\Coupons\DataTransferObjects\RedemptionResult;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\Models\CouponRedemption;
use RoundlyConsulting\Coupons\Support\CodeFormat;
use RoundlyConsulting\Money\Money;

/**
 * Gives a redeemer model (typically the host's User) first-class coupon
 * behaviour: a redemption history relation, a redeem method, and a redeemed
 * check. Redemption goes through the same CouponManager the facade uses, so there is
 * no parallel redemption path and `Coupons::fake()` records it.
 *
 * @phpstan-require-extends Model
 */
trait HasCoupons
{
    /**
     * This redeemer's recorded coupon redemptions.
     *
     * @return MorphMany<CouponRedemption, $this>
     */
    public function couponRedemptions(): MorphMany
    {
        return $this->morphMany(CouponRedemption::class, 'redeemer');
    }

    /**
     * Redeem a coupon as this redeemer against the cart total — the price the discount comes
     * off, in the cart's own currency. The total is required: a redemption without a price
     * would consume a use at a zero discount.
     */
    public function redeemCoupon(Coupon|string $coupon, Money $cartTotal): RedemptionResult
    {
        return app(CouponManager::class)->redeem($coupon, $cartTotal, $this);
    }

    /**
     * Whether this redeemer has a recorded redemption of the live coupon holding the given
     * code (matched case-insensitively) — a pruned coupon that once held it does not count.
     * Only reflects tracked redemptions (coupons.redeemer.track = true).
     */
    public function hasRedeemed(string $code): bool
    {
        return $this->couponRedemptions()
            ->whereHas('coupon', fn (Builder $query): Builder => $query->where('code', CodeFormat::normalize($code)))
            ->exists();
    }
}
