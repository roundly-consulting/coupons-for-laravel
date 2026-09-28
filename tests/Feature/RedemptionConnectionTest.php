<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Coupons\Exceptions\CouponAtMaxUsage;
use RoundlyConsulting\Coupons\Facades\Coupons;
use RoundlyConsulting\Coupons\Tests\Fixtures\OtherConnectionCoupon;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Testing\Fixtures\LockRecorder;
use RoundlyConsulting\Testing\Fixtures\LockRecordingGrammar;

/**
 * The redemption lock must be taken inside a transaction on the connection the coupon row
 * lives on. It used to open `DB::transaction()` — the DEFAULT connection's — while the
 * `SELECT … FOR UPDATE` ran on the coupon model's own connection. For a `coupons.model` with
 * a non-default `$connection` that select ran in autocommit, the row lock was released the
 * moment it was taken, and concurrent redemptions could overshoot `max_usage` and the
 * per-redeemer cap.
 */
beforeEach(function (): void {
    config()->set('database.connections.coupons_other', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);

    $this->artisan('migrate', [
        '--database' => 'coupons_other',
        '--path' => realpath(__DIR__.'/../../database/migrations'),
        '--realpath' => true,
    ])->assertSuccessful();

    config()->set('coupons.model', OtherConnectionCoupon::class);

    LockRecorder::flush();
});

it('locks the coupon row inside a transaction on the coupon model connection', function (): void {
    $connection = DB::connection('coupons_other');
    $connection->setQueryGrammar(new LockRecordingGrammar($connection));
    LockRecorder::listenForMarkers();

    Coupons::generate(DiscountType::Percentage, 1000, code: 'ELSEWHERE', maxUsage: 1)->activate()->save();
    LockRecorder::flush();

    $result = Coupons::redeem('ELSEWHERE', Money::ofMinor(5000, 'EUR'));

    $locks = LockRecorder::recorded();

    expect($result->discount->minor())->toBe('500')
        ->and($locks)->toHaveCount(1)
        // Depth 1 on the coupon's OWN connection: the lock holds until the increment commits.
        ->and($locks[0]['transactionDepth'])->toBe(1)
        ->and(OtherConnectionCoupon::query()->where('code', 'ELSEWHERE')->value('usage'))->toBe(1)
        ->and(fn () => Coupons::redeem('ELSEWHERE', Money::ofMinor(5000, 'EUR')))->toThrow(CouponAtMaxUsage::class);
});
