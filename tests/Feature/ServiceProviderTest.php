<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Coupons\CouponManager;
use RoundlyConsulting\Coupons\Support\RedemptionGuard;

it('registers all publish tags', function (string $tag): void {
    $paths = ServiceProvider::pathsToPublish(null, $tag);

    expect($paths)->not->toBeEmpty();
})->with([
    'coupons-config',
    'coupons-migrations',
    'coupons-translations',
]);

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

it('reports the code alphabet by size and never prints it', function (): void {
    config()->set('coupons.code.charset', 'ABCDEF');
    config()->set('coupons.code.length', 8);

    $this->artisan('about --only=coupons')
        ->expectsOutputToContain('8 chars from a 6-symbol alphabet')
        ->doesntExpectOutputToContain('ABCDEF')
        ->assertExitCode(0);
});
