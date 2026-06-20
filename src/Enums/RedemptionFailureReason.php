<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Enums;

/**
 * The ordered reasons a coupon redemption can be rejected. Each maps to the
 * matching package exception and to a translatable message key, so the action,
 * the validation rule, and the failed-redemption event all share one vocabulary.
 */
enum RedemptionFailureReason: string
{
    case NotFound = 'not_found';

    case CurrencyMismatch = 'currency_mismatch';

    case MinimumSpendNotMet = 'minimum_spend_not_met';

    /** The coupon is inactive or past its expiry. */
    case Expired = 'expired';

    case AtMaxUsage = 'at_max_usage';

    /** The per-redeemer cap has been reached. */
    case AlreadyRedeemed = 'already_redeemed';

    /**
     * The translation key for this reason's user-facing message.
     */
    public function translationKey(): string
    {
        return "coupons::messages.{$this->value}";
    }
}
