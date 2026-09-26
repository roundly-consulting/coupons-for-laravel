<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\CouponManager;
use RoundlyConsulting\Coupons\Exceptions\CouponException;
use RoundlyConsulting\Coupons\Exceptions\CouponNotRedeemable;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\Models\CouponRedemption;
use RoundlyConsulting\Testing\Arch\ArchPresets;

/**
 * The seven presets replace the generic hand-written rules that were here (finality,
 * strict types). The two bespoke rules below have no preset equivalent and are kept.
 */
ArchPresets::strictTypes('RoundlyConsulting\Coupons');

/**
 * Exempt from finality, each deliberately:
 *  - Coupon — `coupons.model` invites a host to subclass it (pinned below instead);
 *  - CouponRedemption — the redemption rows a swapped Coupon hasMany, extended alongside it;
 *  - CouponException — the base every coupons error extends, so a host can catch uniformly;
 *  - CouponNotRedeemable — the intermediate base for every "why it was refused" cause, so
 *    a host can catch the whole not-redeemable surface with one type;
 *  - CouponManager — the package's own FakeCouponManager extends it, which is how
 *    `Coupons::fake()` works. `final` here would break a feature this package ships.
 */
ArchPresets::finalByDefault('RoundlyConsulting\Coupons', [
    Coupon::class,
    CouponRedemption::class,
    CouponException::class,
    CouponNotRedeemable::class,
    CouponManager::class,
]);

/**
 * The counter-weight to the rule above, and the fleet's 7×-shipped fatal: `final` on a
 * config-swappable model is a PHP fatal the moment a host uses the seam the config
 * documents. Also pins that `coupons.model` really defaults to the packaged model, so the
 * seam cannot rot in the other direction either.
 */
ArchPresets::swappableModelsAreNotFinal([
    Coupon::class => 'coupons.model',
]);

/**
 * Coupons generates codes from a configured alphabet. That generator is the one place a
 * hand-rolled randomness scheme would plausibly land here rather than in crypto-for-laravel.
 */
ArchPresets::noLocalCryptoPrimitives('RoundlyConsulting\Coupons');

/**
 * `coupons.model` resolves through the CouponModel seam in Support. Adopted on the
 * pre-classified rule (Swap? > 0): coupons has the shape the preset targets — a real
 * Eloquent model behind a `*_model`-style key — and nothing here needs the late static
 * binding it bans; every call site goes through `CouponModel::class()`.
 */
ArchPresets::modelsResolveThroughSeam(__DIR__.'/../../src', 'Support');

/**
 * `modelsResolveThroughSeam` bans `static::query()` / `new static`, but not a call site
 * naming the packaged class outright — which is how both console commands bypassed
 * `coupons.model` while every other path honoured it. This pins that shape too. The
 * recording fake is exempt: it is DB-free and never hydrates host rows.
 */
it('never queries the packaged coupon model directly', function (): void {
    $offenders = [];

    foreach (couponsPhpFilesIn(__DIR__.'/../../src') as $file) {
        if (str_contains($file->getPathname(), '/Testing/')) {
            continue;
        }

        // Comments stripped: docblocks legitimately name `Coupon::isRedeemableBy()`.
        $contents = php_strip_whitespace($file->getPathname());

        if (preg_match('/\bCoupon::(?!class\b)[a-zA-Z]+\(/', $contents) === 1) {
            $offenders[] = $file->getBasename();
        }
    }

    expect($offenders)->toBe([]);
});

/**
 * The morph-key seam, guarded. Coupons migrated its redemption morph column off raw
 * `$table->morphs()` onto `morphKey($name, KeyType::…)` so a uuid/ulid host can flip its
 * whole graph coherently — a hardcoded bigint id breaks those hosts on Postgres, and SQLite
 * type affinity hides it. This pin reds if a future migration reintroduces a raw morph and
 * bypasses the seam.
 */
ArchPresets::morphColumnsUseTheSeam(__DIR__.'/../../database/migrations');

/**
 * The Dependency Policy as a test. No `alsoAllow`: coupons' `require` ships only
 * php/illuminate/roundly, and the workflow installs test tooling with `--dev`, so nothing
 * legitimately lands in `require` that this must forgive. If this goes red the graph is
 * wrong — never widen the allow-list to quiet it.
 */
ArchPresets::runtimeRequireIsWhitelisted(__DIR__.'/../../composer.json');

ArchPresets::noDebuggingLeftovers();

/**
 * Kept — bespoke, no preset equivalent: the vendor roots src may touch. Narrower than
 * `runtimeRequireIsWhitelisted` (which reads composer.json), this pins the actual imports.
 */
arch('src uses only allowed namespaces')
    ->expect('RoundlyConsulting\Coupons')
    ->toOnlyUse([
        'RoundlyConsulting\Coupons',
        'RoundlyConsulting\Coupons\Database\Factories',
        'RoundlyConsulting\PackageToolkit',
        'RoundlyConsulting\Money',
        'Illuminate',
        'Carbon',
        'Closure',
        'RuntimeException',
        // PHP's Randomizer (CSPRNG engine by default) draws generated code symbols.
        'Random\Randomizer',
        // native/framework helpers used unqualified
        'app',
        'class_basename',
        'config',
        'now',
        'trans',
        'dispatch',
        'fake',
    ])
    // FakeCouponManager ships PHPUnit assertions for host-app tests.
    ->ignoring('PHPUnit\Framework\Assert');

/**
 * Kept — bespoke: `finalByDefault` covers the final half, but nothing in the presets pins
 * that the DTOs are also readonly.
 */
arch('data transfer objects are final and readonly')
    ->expect('RoundlyConsulting\Coupons\DataTransferObjects')
    ->toBeFinal()
    ->toBeReadonly();

/**
 * money-for-laravel is a hard dependency, but only its public surface is: its cast
 * implementations, bcmath gateway and schema internals are `@internal` and may change
 * without notice. Coupons goes through AsMoney / AsCurrency / Money / Discount only.
 */
it('does not import a money class marked @internal', function (): void {
    $internal = [];

    foreach (couponsPhpFilesIn(__DIR__.'/../../vendor/roundly-consulting/money-for-laravel/src') as $file) {
        $contents = (string) file_get_contents($file->getPathname());

        // Class-level only: money also tags single methods of public classes.
        if (preg_match('/@internal\b[^\n]*\n(?:\s*\*[^\n]*\n)*\s*\*\/\s*\n(?:#\[[^\n]*\]\s*\n)*(?:(?:final|abstract|readonly)\s+)*(?:class|interface|trait|enum)\s/', $contents) !== 1
            || preg_match('/^namespace\s+([^;]+);/m', $contents, $namespace) !== 1) {
            continue;
        }

        $internal[] = $namespace[1].'\\'.$file->getBasename('.php');
    }

    // Pinned by name so the scan cannot silently cover nothing.
    expect($internal)
        ->toContain('RoundlyConsulting\Money\Casts\MoneyCast')
        ->toContain('RoundlyConsulting\Money\Math\Calculator')
        ->not->toContain('RoundlyConsulting\Money\Money');

    $offenders = [];

    foreach ([...couponsPhpFilesIn(__DIR__.'/../../src'), ...couponsPhpFilesIn(__DIR__.'/../../database')] as $file) {
        $contents = (string) file_get_contents($file->getPathname());

        foreach ($internal as $class) {
            if (str_contains($contents, 'use '.$class.';')) {
                $offenders[] = $file->getBasename().' → '.$class;
            }
        }
    }

    expect($offenders)->toBe([]);
});

/**
 * @return list<SplFileInfo>
 */
function couponsPhpFilesIn(string $directory): array
{
    $files = [];

    /** @var iterable<SplFileInfo> $iterator */
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file;
        }
    }

    return $files;
}
