<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Coupons\Actions\RedeemCouponAction;
use RoundlyConsulting\Coupons\DataTransferObjects\RedeemCouponData;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\ValueObjects\Money;
use RoundlyConsulting\Testing\Database\DriverMatrix;
use RoundlyConsulting\Testing\Fixtures\LockRecorder;
use RoundlyConsulting\Testing\Fixtures\LockRecordingGrammar;

/**
 * L — the lock the redemption cap rests on, pinned in shape as well as in depth.
 *
 * Credits shipped `select sum("amount") … for update` since day one: Postgres rejects it
 * outright ("FOR UPDATE is not allowed with aggregate functions", SQLSTATE 0A000), so every
 * overdraft-guarded spend threw on any real engine. It hid because SQLite compiles
 * `lockForUpdate()` to an empty string. The lesson that matters here: credits' own suite
 * *recorded the lock* with purpose-built fixtures and still could not see the bug —
 * **recording that a lock was asked for says nothing about whether the SQL it produced is
 * legal**.
 *
 * That is exactly what coupons' suite did before this row. It used a hand-rolled variant-A
 * recorder (`LockRecordingCoupon` + `LockRecordingBuilder`, both now deleted in favour of
 * the testing package) which hooked the *builder method* and recorded only the transaction
 * depth. It would have been green on credits' bug too.
 *
 * So this uses **variant B**: `LockRecordingGrammar` compiles the lock to a trailing
 * `/* lock-for-update *\/` comment, which means the recorded SQL is the SQL the engine
 * would have run. Both halves are pinned:
 *
 *  1. the locked read is a **row** read, not an aggregate — the credits shape, which
 *     coupons does not have and must not grow;
 *  2. the lock is real and correctly placed — transaction depth 1 (the datum that
 *     condemned `LockedUpdate`: a lock one level too deep is a silent non-lock).
 */
beforeEach(function (): void {
    LockRecorder::flush();
});

it('locks the coupon row without an aggregate the engine would reject', function (): void {
    $connection = DB::connection();
    $connection->setQueryGrammar(new LockRecordingGrammar($connection));
    LockRecorder::listenForMarkers();

    Coupon::factory()->fixed(500)->active()->create(['code' => 'LOCKED', 'max_usage' => 1]);

    LockRecorder::flush();

    app(RedeemCouponAction::class)->execute(
        new RedeemCouponData(coupon: 'LOCKED', price: new Money(5000, 'USD'), redeemer: null),
    );

    $locks = LockRecorder::recorded();

    // The redemption takes exactly one lock, and it is a real FOR UPDATE.
    expect($locks)->toHaveCount(1)
        ->and($locks[0]['marker'])->toBe('lock-for-update')
        // The credits shape, pinned as a regression: `sum(...) ... for update` is invalid
        // on Postgres and MySQL alike. The locked read must select the row, not aggregate.
        ->and(strtolower($locks[0]['sql']))->not->toContain('sum(')
        ->and(strtolower($locks[0]['sql']))->not->toContain('count(')
        // It locks the coupons row itself — the row the usage cap is enforced against.
        ->and(strtolower($locks[0]['sql']))->toContain('from "coupons"')
        // Depth 1 = taken inside the redemption transaction, so a second redeemer blocks
        // on the row until the first has committed its increment. A lock at depth 0 is
        // released immediately and serialises nothing.
        ->and($locks[0]['transactionDepth'])->toBe(1);
})->skip(fn (): bool => DriverMatrix::driver() !== 'sqlite', 'recording grammar is sqlite-only');
