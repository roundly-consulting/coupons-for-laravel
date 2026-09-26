<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Coupons\Actions\RedeemCouponAction;
use RoundlyConsulting\Coupons\DataTransferObjects\RedeemCouponData;
use RoundlyConsulting\Coupons\Exceptions\CouponAtMaxUsage;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Money\Money;

/**
 * Redemption limits are a money boundary: two racing redeemers must never push a
 * coupon past max_usage. These tests pin the two properties that guarantee it —
 * mutual exclusion (the eligibility guard and the usage write run inside one
 * transaction, against a row read FOR UPDATE) and a lost-update-proof write (the
 * increment is relative, never a stale literal read back from the model).
 */
function redeemCode(string $code): void
{
    app(RedeemCouponAction::class)->execute(
        new RedeemCouponData(coupon: $code, price: Money::ofMinor(5000, 'EUR'), redeemer: null),
    );
}

// The mutual-exclusion half — that the guard and the increment run against a row read FOR
// UPDATE at transaction depth 1 — is pinned in LockedRedemptionShapeTest. It moved there
// with the hand-rolled LockRecordingCoupon/LockRecordingBuilder deleted in favour of the
// testing package's variant-B recorder, which pins the locked SQL itself and not merely
// that a lock was asked for. Credits proved that distinction is the whole bug.

it('increments usage with a relative write, not a stale read-modify-write', function (): void {
    Coupon::factory()->fixed(500, 'EUR')->active()->create(['code' => 'RELATIVE', 'max_usage' => 0]);

    $statements = [];
    DB::listen(function (QueryExecuted $query) use (&$statements): void {
        $statements[] = $query->sql;
    });

    redeemCode('RELATIVE');

    $updates = array_values(array_filter(
        $statements,
        static fn (string $sql): bool => str_starts_with($sql, 'update "coupons"'),
    ));

    expect($updates)->toHaveCount(1)
        ->and($updates[0])->toContain('"usage" = "usage" + 1');
});

it('never loses a redemption that lands between the locked read and the write', function (): void {
    $coupon = Coupon::factory()->fixed(500, 'EUR')->active()->create(['code' => 'RACE', 'max_usage' => 0]);
    $interleaved = false;

    // Simulate the worst case: another redemption commits its increment after we
    // have read the row. A relative write folds it in (usage = 2); a literal write
    // built from the stale read would overwrite it (usage = 1) — a lost redemption.
    DB::listen(function (QueryExecuted $query) use ($coupon, &$interleaved): void {
        if ($interleaved || ! str_starts_with($query->sql, 'select * from "coupons" where "coupons"."id"')) {
            return;
        }

        $interleaved = true;

        DB::table('coupons')->where('id', $coupon->getKey())->increment('usage');
    });

    redeemCode('RACE');

    expect($interleaved)->toBeTrue()
        ->and($coupon->fresh()?->usage)->toBe(2);
});

it('rejects the redemption that would exceed the cap instead of overshooting it', function (): void {
    $coupon = Coupon::factory()->fixed(500, 'EUR')->active()->create(['code' => 'CAPPED', 'max_usage' => 1]);

    redeemCode('CAPPED');

    expect(fn () => redeemCode('CAPPED'))->toThrow(CouponAtMaxUsage::class)
        ->and($coupon->fresh()?->usage)->toBe(1);
});
