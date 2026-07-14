<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Coupons\DataTransferObjects\RedeemCouponData;
use RoundlyConsulting\Coupons\DataTransferObjects\RedemptionResult;
use RoundlyConsulting\Coupons\Enums\RedemptionFailureReason;
use RoundlyConsulting\Coupons\Events\CouponExhausted;
use RoundlyConsulting\Coupons\Events\CouponRedeemed;
use RoundlyConsulting\Coupons\Events\CouponRedemptionFailed;
use RoundlyConsulting\Coupons\Exceptions\CouponAlreadyRedeemed;
use RoundlyConsulting\Coupons\Exceptions\CouponAtMaxUsage;
use RoundlyConsulting\Coupons\Exceptions\CouponExpired;
use RoundlyConsulting\Coupons\Exceptions\CouponNotFound;
use RoundlyConsulting\Coupons\Exceptions\CurrencyMismatch;
use RoundlyConsulting\Coupons\Exceptions\MinimumSpendNotMet;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\Support\CouponModel;
use RoundlyConsulting\Coupons\Support\RedemptionGuard;
use RoundlyConsulting\Coupons\ValueObjects\Money;

final class RedeemCouponAction
{
    public function __construct(
        private readonly RedemptionGuard $guard,
    ) {}

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
        $couponId = $this->resolveCouponId($data->coupon, $data->redeemer);

        return DB::transaction(function () use ($couponId, $data): RedemptionResult {
            $coupon = $this->newQuery()
                ->whereKey($couponId)
                ->lockForUpdate()
                ->first();

            if ($coupon === null) {
                $this->fail($this->describe($data->coupon), RedemptionFailureReason::NotFound, $data->redeemer);

                throw CouponNotFound::forCode($this->describe($data->coupon));
            }

            $this->guard($coupon, $data->price, $data->redeemer);

            $discount = $coupon->discountFor($data->price);
            $total = $data->price->subtract($discount);

            $coupon->increment('usage');
            $coupon->refresh();

            $this->recordRedemption($coupon, $discount, $data->redeemer);

            $result = new RedemptionResult(
                coupon: $coupon,
                discount: $discount,
                total: $total,
                redeemer: $data->redeemer,
                freeShipping: $coupon->isFreeShipping(),
            );

            CouponRedeemed::dispatch($coupon, $result);

            // Fire exactly once: only the redemption that brings usage up to the
            // cap matches; the next attempt is rejected before it can increment.
            if ($coupon->max_usage > 0 && $coupon->usage === $coupon->max_usage) {
                CouponExhausted::dispatch($coupon);
            }

            return $result;
        });
    }

    /**
     * Delegate eligibility to the shared guard, preserving the exact exception
     * types, messages, and precedence callers depended on before extraction. On a
     * failing reason it also dispatches CouponRedemptionFailed before throwing.
     *
     * @throws CurrencyMismatch|MinimumSpendNotMet|CouponExpired|CouponAtMaxUsage|CouponAlreadyRedeemed
     */
    private function guard(Coupon $coupon, Money $price, ?Model $redeemer): void
    {
        $reason = $this->guard->firstFailure($coupon, $price, $redeemer);

        if ($reason !== null) {
            $this->fail($coupon->code, $reason, $redeemer);
            $this->guard->throwFor($coupon, $reason, $price);
        }
    }

    private function fail(string $code, RedemptionFailureReason $reason, ?Model $redeemer): void
    {
        CouponRedemptionFailed::dispatch($code, $reason, $redeemer);
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

    private function resolveCouponId(Coupon|string $coupon, ?Model $redeemer): int
    {
        if ($coupon instanceof Coupon) {
            return (int) $coupon->getKey();
        }

        $found = $this->newQuery()->where('code', $coupon)->first();

        if ($found === null) {
            $this->fail($coupon, RedemptionFailureReason::NotFound, $redeemer);

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
        return CouponModel::class()::query();
    }
}
