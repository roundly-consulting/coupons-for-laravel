<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Coupons\CouponManager;
use RoundlyConsulting\Coupons\Enums\RedemptionFailureReason;
use RoundlyConsulting\Money\Money;

/**
 * Validates that a coupon code field resolves to a coupon that can be redeemed
 * right now. It asks `Coupons::check()` — the same checks, in the same order, as
 * redemption and Coupon::isRedeemableBy() — and reports the translatable message for
 * the first failing reason.
 *
 * Pass a cart total to also validate currency and minimum spend, and a redeemer
 * to validate per-redeemer caps; omit either to skip those checks.
 */
final class Redeemable implements ValidationRule
{
    public function __construct(
        private readonly ?Money $cartTotal = null,
        private readonly ?Model $redeemer = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $code = is_string($value) ? $value : '';

        $reason = $code === ''
            ? RedemptionFailureReason::NotFound
            : app(CouponManager::class)->check($code, $this->cartTotal, $this->redeemer);

        if ($reason !== null) {
            $this->failWith($fail, $reason, ['code' => $code]);
        }
    }

    /**
     * @param  array<string, string>  $replace
     */
    private function failWith(Closure $fail, RedemptionFailureReason $reason, array $replace): void
    {
        $fail($reason->translationKey())->translate($replace);
    }
}
