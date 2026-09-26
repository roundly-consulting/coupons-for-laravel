<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use RoundlyConsulting\Coupons\Actions\RedeemCouponAction;
use RoundlyConsulting\Coupons\DataTransferObjects\RedeemCouponData;
use RoundlyConsulting\Coupons\DataTransferObjects\RedemptionResult;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\Models\CouponRedemption;
use RoundlyConsulting\Money\Money;

/**
 * Gives a redeemer model (typically the host's User) first-class coupon
 * behaviour: a redemption history relation, a redeem method, and a redeemed
 * check. Redemption delegates to the same RedeemCouponAction the facade uses, so
 * there is no parallel redemption path.
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
     * Redeem a coupon as this redeemer. With no cart total a zero amount in the
     * configured default currency is used, so a coupon with a minimum spend will
     * correctly reject an empty basket.
     */
    public function redeemCoupon(Coupon|string $coupon, ?Money $cartTotal = null): RedemptionResult
    {
        /** @var string $currency */
        $currency = config('coupons.default_currency', 'USD');

        return app(RedeemCouponAction::class)->execute(new RedeemCouponData(
            coupon: $coupon,
            price: $cartTotal ?? Money::zero($currency),
            redeemer: $this,
        ));
    }

    /**
     * Whether this redeemer has a recorded redemption of the given coupon code.
     * Only reflects tracked redemptions (coupons.redeemer.track = true).
     */
    public function hasRedeemed(string $code): bool
    {
        return $this->couponRedemptions()
            ->whereHas('coupon', fn (Builder $query): Builder => $query->where('code', $code))
            ->exists();
    }
}
