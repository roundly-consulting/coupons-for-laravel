<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Coupons\DataTransferObjects\RedeemCouponData;
use RoundlyConsulting\Coupons\DataTransferObjects\RedemptionResult;
use RoundlyConsulting\Coupons\Events\CouponRedeemed;
use RoundlyConsulting\Coupons\Exceptions\CouponAlreadyRedeemed;
use RoundlyConsulting\Coupons\Exceptions\CouponAtMaxUsage;
use RoundlyConsulting\Coupons\Exceptions\CouponExpired;
use RoundlyConsulting\Coupons\Exceptions\CouponNotFound;
use RoundlyConsulting\Coupons\Exceptions\CurrencyMismatch;
use RoundlyConsulting\Coupons\Exceptions\MinimumSpendNotMet;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\ValueObjects\Money;

final class RedeemCouponAction
{
    /**
     * Validate a coupon and redeem it atomically: the eligibility checks and the
     * usage increment run inside a single transaction against a row locked with
     * lockForUpdate, so two concurrent redemptions can never exceed max_usage.
     *
     * @throws CouponNotFound when the code resolves to no coupon.
     * @throws CurrencyMismatch|MinimumSpendNotMet|CouponExpired|CouponAtMaxUsage|CouponAlreadyRedeemed
     */
    public function execute(RedeemCouponData $data): RedemptionResult
    {
        $couponId = $this->resolveCouponId($data->coupon);

        return DB::transaction(function () use ($couponId, $data): RedemptionResult {
            $coupon = $this->newQuery()
                ->whereKey($couponId)
                ->lockForUpdate()
                ->first();

            if ($coupon === null) {
                throw CouponNotFound::forCode((string) $this->describe($data->coupon));
            }

            $this->guard($coupon, $data->price, $data->redeemer);

            $discount = $coupon->discountFor($data->price);
            $total = $data->price->subtract($discount);

            $coupon->increment('usage');

            $this->recordRedemption($coupon, $discount, $data->redeemer);

            $result = new RedemptionResult(
                coupon: $coupon->refresh(),
                discount: $discount,
                total: $total,
                redeemer: $data->redeemer,
                freeShipping: $coupon->isFreeShipping(),
            );

            CouponRedeemed::dispatch($coupon, $result);

            return $result;
        });
    }

    /**
     * @throws CurrencyMismatch|MinimumSpendNotMet|CouponExpired|CouponAtMaxUsage|CouponAlreadyRedeemed
     */
    private function guard(Coupon $coupon, Money $price, ?Model $redeemer): void
    {
        if (! $coupon->appliesToCurrency($price)) {
            throw CurrencyMismatch::forCode($coupon->code, (string) $coupon->currency, $price->getCurrency());
        }

        if (! $coupon->meetsMinimumSpend($price)) {
            throw MinimumSpendNotMet::forCode($coupon->code, (int) $coupon->minimum_spend);
        }

        if (! $coupon->isActive() || $coupon->isExpired()) {
            throw CouponExpired::forCode($coupon->code);
        }

        if ($coupon->isAtMaximumUsage()) {
            throw CouponAtMaxUsage::forCode($coupon->code);
        }

        if ($redeemer !== null && $coupon->isAtMaximumUsageFor($redeemer)) {
            throw CouponAlreadyRedeemed::forCode($coupon->code);
        }
    }

    private function recordRedemption(Coupon $coupon, Money $discount, ?Model $redeemer): void
    {
        if ($redeemer === null || config('coupons.redeemer.track', true) !== true) {
            return;
        }

        $coupon->redemptions()->create([
            'redeemer_type' => $redeemer->getMorphClass(),
            'redeemer_id' => $redeemer->getKey(),
            'amount_discounted' => $discount->getAmount(),
            'currency' => $discount->getCurrency(),
        ]);
    }

    private function resolveCouponId(Coupon|string $coupon): int
    {
        if ($coupon instanceof Coupon) {
            return (int) $coupon->getKey();
        }

        $found = $this->newQuery()->where('code', $coupon)->first();

        if ($found === null) {
            throw CouponNotFound::forCode($coupon);
        }

        return (int) $found->getKey();
    }

    private function describe(Coupon|string $coupon): string
    {
        return $coupon instanceof Coupon ? $coupon->code : $coupon;
    }

    /**
     * @return Builder<Coupon>
     */
    private function newQuery(): Builder
    {
        /** @var class-string<Coupon> $model */
        $model = config('coupons.model', Coupon::class);

        return $model::query();
    }
}
