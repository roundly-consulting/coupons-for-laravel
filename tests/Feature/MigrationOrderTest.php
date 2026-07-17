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
 * R (`toApplyOnConnection` + `toRejectBrokenOrderOnConnection`) is deliberately NOT adopted
 * yet, and this is a HOLD rather than a judgement about coupons.
 *
 * `DriverMatrix::configure()` currently builds `connections.testing` and `connections.pgsql`
 * from the same `connectionConfig('pgsql')`, so on the pgsql leg they are one physical
 * database reached by two PDO sessions. `MigrationRunner::runFiles()` drops all tables on
 * entry and again in `finally`, which means an R assertion tears the schema out from under
 * the live suite mid-run. With `executionOrder="random"` that is seed-dependent, so a green
 * run proves nothing.
 *
 * Measured on this row, R adopted, 5 consecutive local pgsql runs:
 *   239 passed / 21 FAILED / 239 passed / 239 passed / 15 FAILED.
 *
 * Both halves WERE written and proven to bite before being held back (a wrong `migrations:`
 * count -> red; dropping the FK edge -> the negative control fails loudly, "the engine
 * accepted the broken order"). Coupons is one of the few rows where the negative control
 * genuinely fits — 2 files and a real FK edge mean the reversed order is something Postgres
 * actually refuses — so this is worth restoring once the base case gives R its own database.
 *
 * The structural pin above (M) needs no engine: it parses the migration source, so it stays.
 */

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
 * The `jsonb` meta column and the soft-delete timestamps are what the drivers genuinely
 * render differently. Pinning a round-trip on whatever engine the leg configured proves the
 * columns are usable rather than merely creatable.
 *
 * Asserted key-by-key, not against a whole literal array: Postgres `jsonb` sorts object keys
 * by (length, bytes), so `toBe(['campaign' => ..., 'tier' => ...])` would compare insertion
 * order the engine never promised to keep. The value types still matter — `tier` must come
 * back the int 2, not "2" — so each key keeps a strict assertion.
 */
it('round-trips the coupon columns on the configured engine', function (): void {
    $coupon = Coupon::factory()->fixed(500)->active()->create([
        'code' => 'ROUNDTRIP',
        'meta' => ['campaign' => 'launch', 'tier' => 2],
        'currency' => 'EUR',
        'minimum_spend' => 1000,
    ]);

    $fresh = $coupon->fresh();

    expect($fresh?->meta?->get('campaign'))->toBe('launch')
        ->and($fresh?->meta?->get('tier'))->toBe(2)
        ->and($fresh?->value)->toBe(500)
        ->and($fresh?->currency)->toBe('EUR')
        ->and($fresh?->minimum_spend)->toBe(1000);
});
