<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Coupons\Enums\RedemptionFailureReason;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\Support\RedemptionGuard;
use RoundlyConsulting\Coupons\ValueObjects\Money;

/**
 * Validates that a coupon code field resolves to a coupon that can be redeemed
 * right now. Reuses the shared RedemptionGuard so the rule, the redemption
 * action, and Coupon::isRedeemableBy() never diverge. On failure it reports the
 * matching translatable message for the first failing reason.
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
        $coupon = $this->resolveCoupon($code);

        if ($coupon === null) {
            $this->failWith($fail, RedemptionFailureReason::NotFound, ['code' => $code]);

            return;
        }

        $reason = app(RedemptionGuard::class)->firstFailure($coupon, $this->cartTotal, $this->redeemer);

        if ($reason !== null) {
            $this->failWith($fail, $reason, ['code' => $coupon->code]);
        }
    }

    /**
     * @param  array<string, string>  $replace
     */
    private function failWith(Closure $fail, RedemptionFailureReason $reason, array $replace): void
    {
        $fail($reason->translationKey())->translate($replace);
    }

    private function resolveCoupon(string $code): ?Coupon
    {
        if ($code === '') {
            return null;
        }

        /** @var class-string<Coupon> $model */
        $model = config('coupons.model', Coupon::class);

        return $model::query()->where('code', $code)->first();
    }
}
