<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Tests\Fixtures;

use RoundlyConsulting\Coupons\Tests\TestCase;

/**
 * The suite's base case with `coupons.model` already pointed at {@see CustomCoupon}
 * BEFORE the providers boot.
 *
 * Boot order is the whole point: the providers hang observers and relationship wiring on
 * whatever `coupons.model` names at boot. A `config()->set()` inside the test body reads
 * back correctly but leaves every listener on the packaged Coupon — precisely the shape
 * that let media #28 ship.
 *
 * Note the `array_merge(parent::configBeforeBoot(), …)`: dropping it would silently
 * discard the base case's app.key, the same decapitation an un-parented
 * `defineEnvironment()` override causes one level up.
 *
 * @see TestCase
 */
abstract class SwappedCouponTestCase extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function configBeforeBoot(): array
    {
        return array_merge(parent::configBeforeBoot(), [
            'coupons.model' => CustomCoupon::class,
        ]);
    }
}
