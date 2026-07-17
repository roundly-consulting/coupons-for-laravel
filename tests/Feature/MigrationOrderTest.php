<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Coupons\CouponsServiceProvider;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Testing\Database\DriverMatrix;

/**
 * Coupons ships two CREATEs and exactly one foreign key: `coupon_redemptions.coupon_id`
 * constrained to `coupons`, cascading on delete. The redeemer side is a
 * `nullableMorphs()` and is deliberately unconstrained — a host's redeemer can live in
 * any table — so it is not an edge.
 *
 * One real FK edge across two files is the shape both halves of R need, so unlike credits
 * (1 migration, 0 edges) this row adopts the negative control too.
 */
$migrations = __DIR__.'/../../database/migrations';

/**
 * M — the structural pin. Five packages shipped uninstallable migration orders under green
 * SQLite suites, because SQLite happily creates a table pointing at a missing parent and
 * only complains at insert time. `foreignKeys: 1` pins the edge count so the check can
 * never pass over an empty parse — the failure mode that makes a green order test worthless.
 */
it('creates the coupons table before the redemptions that reference it', function () use ($migrations): void {
    expect($migrations)->toHaveRunnableMigrationOrder(foreignKeys: 1);
});

/**
 * P — the publish-only guards. The fleet publishes migrations timestamped rather than
 * auto-loading them; doing both runs both copies and dies on a duplicate table (bug #5, on
 * three packages). `2` pins the file count so neither check can pass over an empty or
 * relocated directory.
 */
it('never auto-loads its migrations — the host publishes them', function (): void {
    expect(CouponsServiceProvider::class)->toNotAutoLoadMigrations();
});

it('publishes its migrations timestamp-injected into the host', function (): void {
    expect(CouponsServiceProvider::class)->toPublishMigrationsTimestamped('coupons-migrations', 2);
});

/**
 * R — the real-engine proof. Coupons' DDL had never met a real engine before this row: the
 * suite ran on SQLite for the package's whole life. `migrations: 2` pins the count, and the
 * expectation additionally fails a set that "applies cleanly" while creating no tables — an
 * empty `up()` otherwise passes and proves nothing.
 */
it('applies its migrations on postgres', function () use ($migrations): void {
    expect($migrations)->toApplyOnConnection('pgsql', migrations: 2);
})->skip(fn (): bool => ! test()->connectionAvailable('pgsql'), 'no postgres connection available');

/**
 * The negative control. A green FK test proves nothing until you have watched the engine
 * *reject* the broken order (forms #28) — on SQLite this assertion fails loudly by design,
 * because SQLite does not enforce the constraint and would accept the reordered set.
 *
 * Adoptable here precisely because the structural facts allow it: reversing two files puts
 * `coupon_redemptions` first, and its FK to a not-yet-existing `coupons` is something
 * Postgres genuinely refuses. Credits could adopt only the positive half (one file, no
 * edges — nothing to refuse).
 */
it('rejects a redemptions-before-coupons order on postgres', function () use ($migrations): void {
    expect($migrations)->toRejectBrokenOrderOnConnection(
        fn (array $files): array => array_reverse($files),
        'pgsql',
    );
})->skip(fn (): bool => ! test()->connectionAvailable('pgsql'), 'no postgres connection available');

/**
 * The driver-truth pin: the env-declared driver against what the connection itself answers.
 * It makes a lying pgsql leg impossible — a base case decapitated by an un-parented
 * `defineEnvironment()` override goes red here instead of quietly running SQLite and
 * reporting itself green. It fires automatically rather than needing a human to read a
 * skip count.
 */
it('runs on the driver the environment declared', function (): void {
    expect(DB::connection()->getDriverName())->toBe(DriverMatrix::driver());
});

/**
 * The `json` meta column and the soft-delete timestamps are what the drivers genuinely
 * render differently. Pinning a round-trip on whatever engine the leg configured proves the
 * columns are usable rather than merely creatable.
 */
it('round-trips the coupon columns on the configured engine', function (): void {
    $coupon = Coupon::factory()->fixed(500)->active()->create([
        'code' => 'ROUNDTRIP',
        'meta' => ['campaign' => 'launch', 'tier' => 2],
        'currency' => 'EUR',
        'minimum_spend' => 1000,
    ]);

    $fresh = $coupon->fresh();

    expect($fresh?->meta?->toArray())->toBe(['campaign' => 'launch', 'tier' => 2])
        ->and($fresh?->value)->toBe(500)
        ->and($fresh?->currency)->toBe('EUR')
        ->and($fresh?->minimum_spend)->toBe(1000);
});
