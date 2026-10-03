<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use RoundlyConsulting\Coupons\Exceptions\InvalidCouponConfiguration;
use RoundlyConsulting\Coupons\Models\Coupon;

/**
 * Owner rule: a typo in a host's config fails loudly and never falls back silently. A string
 * setting that is not set — absent, null or blank (`''` or whitespace, a host's `KEY=`) — takes
 * its default; a wrong-typed one throws naming the key instead of quietly binding routes by
 * `code` or locking coupons to USD.
 */
it('refuses a wrong-typed route key (strict config)', function (mixed $value): void {
    config()->set('coupons.route_key', $value);

    expect(fn () => (new Coupon)->getRouteKeyName())
        ->toThrow(InvalidCouponConfiguration::class, '[coupons.route_key]');
})->with([
    'array' => [['code']],
    'int' => 1,
]);

it('binds by the configured route key, and by code when not set (strict config)', function (?string $unset): void {
    config()->set('coupons.route_key', 'id');
    expect((new Coupon)->getRouteKeyName())->toBe('id');

    config()->set('coupons.route_key', $unset);
    expect((new Coupon)->getRouteKeyName())->toBe('code');
})->with(['absent' => null, 'empty' => '', 'whitespace' => '  ']);

it('refuses a wrong-typed default currency (strict config)', function (mixed $value): void {
    config()->set('coupons.default_currency', $value);

    expect(fn () => Coupon::factory()->fixed(500)->make())
        ->toThrow(InvalidCouponConfiguration::class, '[coupons.default_currency]');
})->with([
    'array' => [['EUR']],
    'false' => false,
]);

it('locks factory coupons to USD when no currency is set (strict config)', function (?string $unset): void {
    config()->set('coupons.default_currency', $unset);

    expect(Coupon::factory()->fixed(500)->make()->currency?->code)->toBe('USD');
})->with(['absent' => null, 'empty' => '', 'whitespace' => '  ']);

it('reports a broken string setting as INVALID in about instead of its default (strict config)', function (): void {
    config()->set('coupons.route_key', 1);
    config()->set('coupons.default_currency', ['EUR']);

    Artisan::call('about', ['--only' => 'coupons']);
    $output = Artisan::output();

    expect($output)->toContain('INVALID: Configuration value [coupons.route_key]')
        ->and($output)->toContain('INVALID: Configuration value [coupons.default_currency]');
});
