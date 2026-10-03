<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use RoundlyConsulting\Coupons\Exceptions\InvalidCouponConfiguration;
use RoundlyConsulting\Coupons\Models\Coupon;

/**
 * Owner rule: a typo in a host's config fails loudly and never falls back silently. An
 * absent (null) string setting takes its default; a blank or wrong-typed one throws naming
 * the key instead of quietly binding routes by `code` or locking coupons to USD.
 */
it('refuses a blank or wrong-typed route key (strict config)', function (mixed $value): void {
    config()->set('coupons.route_key', $value);

    expect(fn () => (new Coupon)->getRouteKeyName())
        ->toThrow(InvalidCouponConfiguration::class, '[coupons.route_key]');
})->with([
    'empty' => '',
    'blank' => '  ',
    'array' => [['code']],
    'int' => 1,
]);

it('binds by the configured route key, and by code when absent (strict config)', function (): void {
    config()->set('coupons.route_key', 'id');
    expect((new Coupon)->getRouteKeyName())->toBe('id');

    config()->set('coupons.route_key', null);
    expect((new Coupon)->getRouteKeyName())->toBe('code');
});

it('refuses a blank or wrong-typed default currency (strict config)', function (mixed $value): void {
    config()->set('coupons.default_currency', $value);

    expect(fn () => Coupon::factory()->fixed(500)->make())
        ->toThrow(InvalidCouponConfiguration::class, '[coupons.default_currency]');
})->with([
    'empty' => '',
    'array' => [['EUR']],
    'false' => false,
]);

it('locks factory coupons to USD only when no currency is configured (strict config)', function (): void {
    config()->set('coupons.default_currency', null);

    expect(Coupon::factory()->fixed(500)->make()->currency?->code)->toBe('USD');
});

it('reports a broken string setting as INVALID in about instead of its default (strict config)', function (): void {
    config()->set('coupons.route_key', '');
    config()->set('coupons.default_currency', ['EUR']);

    Artisan::call('about', ['--only' => 'coupons']);
    $output = Artisan::output();

    expect($output)->toContain('INVALID: Configuration value [coupons.route_key]')
        ->and($output)->toContain('INVALID: Configuration value [coupons.default_currency]');
});
