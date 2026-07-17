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
ArchPresets::finalByDefault('RoundlyConsulting\Coupons')
    ->ignoring([
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
        'Illuminate',
        'Carbon',
        'Closure',
        'NumberFormatter',
        'RuntimeException',
        'Stringable',
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
