<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\Actions\RedeemCouponAction;
use RoundlyConsulting\Coupons\DataTransferObjects\CreateCouponData;
use RoundlyConsulting\Coupons\DataTransferObjects\RedeemCouponData;
use RoundlyConsulting\Coupons\Exceptions\CouponAtMaxUsage;
use RoundlyConsulting\Coupons\Facades\Coupons;
use RoundlyConsulting\Coupons\Tests\Fixtures\CustomCoupon;
use RoundlyConsulting\Coupons\Tests\Fixtures\SwappedCouponTestCase;
use RoundlyConsulting\Money\Money;

/**
 * The model-swap proof (S) for the `coupons.model` seam, driven through the REAL flows.
 *
 * The bugs this class of test exists for are the retrofit's single biggest class:
 *
 *  - a runtime `config()->set()` leaves every observer the provider hung at boot on the
 *    packaged Coupon (media #28);
 *  - `instanceof` passes for a row created as the packaged class — which never fires the
 *    host's model events (permissions #31). Only the concrete class, plus a `created`
 *    event counted on the subclass itself, proves the row was made as the host's model;
 *  - a hard-coded call site sitting beside an honoured config (shops #3, media #28) —
 *    which is why the exercise below goes through the manager and the action rather than
 *    asserting on the resolver's return string.
 *
 * The swap is applied before boot by {@see SwappedCouponTestCase}, which this directory is
 * bound to — Pest binds a test case per directory, not per file.
 */

/**
 * A coupon is created inactive (`activated_at` is null and `isActive()` is false until it
 * is in the past), and CreateCouponData carries no activation. Activating through the
 * returned model keeps every write on the host's class.
 */
function createActiveCoupon(string $code, int $maxUsage = 0): CustomCoupon
{
    $coupon = Coupons::create(CreateCouponData::fixed(
        Money::ofMinor(500, 'USD'),
        code: $code,
        maxUsage: $maxUsage,
    ));

    $coupon->update(['activated_at' => now()->subDay()]);

    /** @var CustomCoupon $coupon */
    return $coupon;
}

it('honours a host coupon model through every redemption flow', function (): void {
    expect('coupons.model')->toHonourModelSwap(CustomCoupon::class, function (): array {
        $created = createActiveCoupon('SWAPPED', maxUsage: 5);

        // Redemption reads the row back under the lock, refreshes it, and returns it on
        // the result — every one of those hydrations must land on the host's model.
        $result = app(RedeemCouponAction::class)->execute(
            new RedeemCouponData(coupon: 'SWAPPED', price: Money::ofMinor(5000, 'USD'), redeemer: null),
        );

        return [
            $created,
            $result->coupon,
            // The lookup path hydrates through the seam too, not just the writes.
            Coupons::find('SWAPPED'),
            ...Coupons::redeemable()->get()->all(),
        ];
    });
});

/**
 * The swap must survive the path that matters most for money: the redemption cap. If the
 * locked read queried the packaged model while the writes went through the host's, the
 * usage the cap is enforced against would come from a different query than the row written.
 */
it('enforces the usage cap through the swapped model', function (): void {
    createActiveCoupon('CAPSWAP', maxUsage: 1);

    $result = app(RedeemCouponAction::class)->execute(
        new RedeemCouponData(coupon: 'CAPSWAP', price: Money::ofMinor(5000, 'USD'), redeemer: null),
    );

    expect($result->coupon)->toBeInstanceOf(CustomCoupon::class)
        ->and($result->coupon->usage)->toBe(1)
        // The cap is enforced against the host's model, and the second attempt is refused.
        ->and(fn (): mixed => app(RedeemCouponAction::class)->execute(
            new RedeemCouponData(coupon: 'CAPSWAP', price: Money::ofMinor(5000, 'USD'), redeemer: null),
        ))
        ->toThrow(CouponAtMaxUsage::class)
        ->and(CustomCoupon::query()->where('code', 'CAPSWAP')->value('usage'))->toBe(1);
});

// The structural half of the seam — Coupon is non-final, and `coupons.model` really
// defaults to the packaged model — is pinned once in tests/Unit/ArchTest.php by
// `ArchPresets::swappableModelsAreNotFinal()`. It deliberately does NOT live here: that
// preset asserts the config *default*, which this directory has swapped away.
