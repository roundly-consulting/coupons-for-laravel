<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\Enums\RedemptionFailureReason;

it('exposes a backed value for every reason', function (): void {
    expect(RedemptionFailureReason::NotFound->value)->toBe('not_found')
        ->and(RedemptionFailureReason::CurrencyMismatch->value)->toBe('currency_mismatch')
        ->and(RedemptionFailureReason::MinimumSpendNotMet->value)->toBe('minimum_spend_not_met')
        ->and(RedemptionFailureReason::Expired->value)->toBe('expired')
        ->and(RedemptionFailureReason::AtMaxUsage->value)->toBe('at_max_usage')
        ->and(RedemptionFailureReason::AlreadyRedeemed->value)->toBe('already_redeemed');
});

it('builds a namespaced translation key for every reason', function (RedemptionFailureReason $reason): void {
    expect($reason->translationKey())->toBe("coupons::messages.{$reason->value}");
})->with(RedemptionFailureReason::cases());
