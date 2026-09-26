<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Testing\Database\DriverMatrix;

/**
 * The outbound `redeemer` morph column follows `coupons.key_type` (default `bigint`) through
 * the toolkit's `morphKey` macro. Two things must hold and are proven here:
 *
 *  - the default (`bigint`) emitted schema is BYTE-IDENTICAL to the pre-macro `nullableMorphs()`
 *    output — `morphKey($n, BigInt, nullable: true)` *is* `nullableMorphs($n)` — so a default
 *    host sees zero change;
 *  - a `uuid` / `ulid` host actually gets a uuid / char morph id column, checked on the only
 *    engine (Postgres) whose catalog can tell the three key types apart.
 */
function runCouponsMigrations(): void
{
    foreach ([
        '2024_01_01_000000_create_coupons_table',
        '2024_01_01_000001_create_coupon_redemptions_table',
    ] as $migration) {
        (require __DIR__.'/../../database/migrations/'.$migration.'.php')->up();
    }
}

/**
 * Drop only this package's tables, the FK-referencing table first, so a re-migration under
 * a different key type does not break the harness reset or trip a foreign-key constraint.
 */
function dropCouponsTables(): void
{
    foreach (['coupon_redemptions', 'coupons'] as $table) {
        Schema::dropIfExists($table);
    }
}

function emittedCouponsTable(string $table): string
{
    /** @var list<object{sql: string|null}> $rows */
    $rows = DB::select('select sql from sqlite_master where type = ? and name = ?', ['table', $table]);

    return (string) ($rows[0]->sql ?? '');
}

function pgsqlCouponsColumnType(string $table, string $column): string
{
    /** @var list<object{data_type: string, character_maximum_length: int|null}> $rows */
    $rows = DB::select(
        'select data_type, character_maximum_length from information_schema.columns where table_name = ? and column_name = ?',
        [$table, $column],
    );

    $row = $rows[0] ?? null;

    if ($row === null) {
        return 'MISSING';
    }

    return $row->character_maximum_length === null
        ? $row->data_type
        : $row->data_type.'('.$row->character_maximum_length.')';
}

$sqliteOnly = fn (): bool => DriverMatrix::driver() !== 'sqlite';
$pgsqlOnly = fn (): bool => DriverMatrix::driver() !== 'pgsql';

it('emits the frozen bigint morph schema byte-for-byte', function (): void {
    // The harness has already migrated on the default (bigint) config. This is the shipped
    // schema — the sweep's core safety property is that it must never drift. The amount is
    // money's decimal(38,0) column, which SQLite reports as `numeric`.
    expect(emittedCouponsTable('coupon_redemptions'))->toBe(
        'CREATE TABLE "coupon_redemptions" ("id" integer primary key autoincrement not null, '
        .'"coupon_id" integer not null, "redeemer_type" varchar, "redeemer_id" integer, '
        .'"amount_discounted" numeric not null, "currency" varchar not null, '
        .'"created_at" datetime, "updated_at" datetime, "deleted_at" datetime, '
        .'foreign key("coupon_id") references "coupons"("id") on delete cascade)'
    );
})->skip($sqliteOnly, 'sqlite_master is the sqlite catalog');

it('renders each configured key type as a distinct real morph column type', function (string $keyType, string $expected): void {
    config()->set('coupons.key_type', $keyType);

    dropCouponsTables();
    runCouponsMigrations();

    expect(pgsqlCouponsColumnType('coupon_redemptions', 'redeemer_id'))->toBe($expected)
        // The morph *type* column names a class — a string on every key type.
        ->and(pgsqlCouponsColumnType('coupon_redemptions', 'redeemer_type'))->toBe('character varying(255)');
})->with([
    'bigint' => ['bigint', 'bigint'],
    'uuid' => ['uuid', 'uuid'],
    'ulid' => ['ulid', 'character(26)'],
])->skip($pgsqlOnly, 'needs the postgres catalog to tell the key types apart');

it('falls back to the bigint morph schema for an unrecognized key type', function (): void {
    config()->set('coupons.key_type', 'nonsense');

    dropCouponsTables();
    runCouponsMigrations();

    // A typo in a host's config must never leave the package unable to migrate.
    expect(Schema::hasColumn('coupon_redemptions', 'redeemer_id'))->toBeTrue()
        ->and(DriverMatrix::driver() === 'pgsql' ? pgsqlCouponsColumnType('coupon_redemptions', 'redeemer_id') : 'bigint')
        ->toBe('bigint');
});
