<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Support;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Coupons\Enums\RedemptionFailureReason;
use RoundlyConsulting\Coupons\Exceptions\CouponAlreadyRedeemed;
use RoundlyConsulting\Coupons\Exceptions\CouponAtMaxUsage;
use RoundlyConsulting\Coupons\Exceptions\CouponExpired;
use RoundlyConsulting\Coupons\Exceptions\CurrencyMismatch;
use RoundlyConsulting\Coupons\Exceptions\MinimumSpendNotMet;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Money\Money;

/**
 * The single source of truth for coupon eligibility. Runs the redemption checks
 * in their canonical order and reports the first failing reason without throwing,
 * so the action, the Coupon model, and the validation rule share one evaluator
 * and can never drift apart.
 */
final class RedemptionGuard
{
    /**
     * The first failing reason in canonical order, or null when the coupon is
     * redeemable. A null price skips the currency and minimum-spend checks (the
     * caller has no cart total), leaving only the time and usage checks.
     */
    public function firstFailure(Coupon $coupon, ?Money $price, ?Model $redeemer): ?RedemptionFailureReason
    {
        if ($price !== null && ! $coupon->appliesToCurrency($price)) {
            return RedemptionFailureReason::CurrencyMismatch;
        }

        if ($price !== null && ! $coupon->meetsMinimumSpend($price)) {
            return RedemptionFailureReason::MinimumSpendNotMet;
        }

        if (! $coupon->isActive() || $coupon->isExpired()) {
            return RedemptionFailureReason::Expired;
        }

        if ($coupon->isAtMaximumUsage()) {
            return RedemptionFailureReason::AtMaxUsage;
        }

        if ($redeemer !== null && $coupon->isAtMaximumUsageFor($redeemer)) {
            return RedemptionFailureReason::AlreadyRedeemed;
        }

        return null;
    }

    /**
     * Throw the package exception that corresponds to the given reason, matching
     * the exact types and messages the action raised before this guard existed.
     *
     * @throws CurrencyMismatch|MinimumSpendNotMet|CouponExpired|CouponAtMaxUsage|CouponAlreadyRedeemed
     */
    public function throwFor(Coupon $coupon, RedemptionFailureReason $reason, Money $price): never
    {
        throw match ($reason) {
            RedemptionFailureReason::CurrencyMismatch => CurrencyMismatch::forCode(
                $coupon->code,
                $coupon->currency->code ?? '',
                $price->currency()->code,
            ),
            RedemptionFailureReason::MinimumSpendNotMet => MinimumSpendNotMet::forCode(
                $coupon->code,
                $coupon->minimum_spend ?? Money::zero($price->currency()),
            ),
            RedemptionFailureReason::Expired => CouponExpired::forCode($coupon->code),
            RedemptionFailureReason::AtMaxUsage => CouponAtMaxUsage::forCode($coupon->code),
            RedemptionFailureReason::AlreadyRedeemed => CouponAlreadyRedeemed::forCode($coupon->code),
            RedemptionFailureReason::NotFound => throw new \LogicException(
                'NotFound is resolved before guarding and has no in-guard exception mapping.',
            ),
        };
    }
}
