<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Coupons\CouponManager;
use RoundlyConsulting\Coupons\CouponsServiceProvider;
use RoundlyConsulting\Coupons\Support\RedemptionGuard;

it('registers all publish tags', function (string $tag): void {
    $paths = ServiceProvider::pathsToPublish(null, $tag);

    expect($paths)->not->toBeEmpty();
})->with([
    'coupons-config',
    'coupons-migrations',
    'coupons-translations',
]);

it('publishes both migrations into the host, in create-before-reference order', function (): void {
    $paths = ServiceProvider::pathsToPublish(CouponsServiceProvider::class, 'coupons-migrations');

    $sources = array_keys($paths);
    $targets = array_values($paths);

    expect($sources)->toHaveCount(2)
        ->and(basename((string) $sources[0]))->toBe('2024_01_01_000000_create_coupons_table.php')
        ->and(basename((string) $sources[1]))->toBe('2024_01_01_000001_create_coupon_redemptions_table.php');

    // Published under a fresh timestamp into the host's migrations directory, and the
    // coupons table still lands before the redemptions table that references it.
    expect($targets[0])->toStartWith(database_path('migrations'))
        ->and($targets[1])->toStartWith(database_path('migrations'))
        ->and(basename((string) $targets[0]))->toEndWith('_create_coupons_table.php')
        ->and(basename((string) $targets[1]))->toEndWith('_create_coupon_redemptions_table.php')
        ->and(basename((string) $targets[0]))->toBeLessThan(basename((string) $targets[1]));
});

it('never auto-loads its migrations — the host must publish them', function (): void {
    $registered = array_map(
        static fn (string $path): string => realpath($path) ?: $path,
        app('migrator')->paths(),
    );

    expect($registered)->not->toContain(realpath(__DIR__.'/../../database/migrations'));
});

it('loads the package translations', function (): void {
    expect(__('coupons::messages.expired'))->not->toBe('coupons::messages.expired');
});

it('binds the manager and the redemption guard as singletons', function (): void {
    expect(app(CouponManager::class))->toBe(app(CouponManager::class))
        ->and(app(RedemptionGuard::class))->toBe(app(RedemptionGuard::class));
});

it('registers the package commands', function (string $command): void {
    expect(array_keys(app('Illuminate\Contracts\Console\Kernel')->all()))->toContain($command);
})->with([
    'coupons:expire',
    'coupons:prune',
]);

it('contributes a coupons section to about', function (string $expected): void {
    $this->artisan('about --only=coupons')
        ->expectsOutputToContain($expected)
        ->assertExitCode(0);
})->with([
    'Coupons',
    'Model',
    'Default currency',
    'Redeemer tracking',
    'Generated codes',
    'Route key',
]);

it('flags an unusable code format in about instead of failing', function (): void {
    config()->set('coupons.code.charset', 'AABB');

    $this->artisan('about --only=coupons')
        ->expectsOutputToContain('INVALID')
        ->doesntExpectOutputToContain('AABB')
        ->assertExitCode(0);
});

it('reports the code alphabet by size and never prints it', function (): void {
    config()->set('coupons.code.charset', 'ABCDEF');
    config()->set('coupons.code.length', 8);

    $this->artisan('about --only=coupons')
        ->expectsOutputToContain('8 chars from a 6-symbol alphabet')
        ->doesntExpectOutputToContain('ABCDEF')
        ->assertExitCode(0);
});
