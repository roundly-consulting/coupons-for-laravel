<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\Models\Coupon;

beforeEach(function (): void {
    $this->active = Coupon::factory()->active()->create(['code' => 'ACTIVE']);
    $this->expired = Coupon::factory()->active()->expired()->create(['code' => 'EXPIRED']);
    $this->inactive = Coupon::factory()->create(['code' => 'INACTIVE']);
    $this->exhausted = Coupon::factory()->active()->create([
        'code' => 'EXHAUSTED',
        'usage' => 5,
        'max_usage' => 5,
    ]);
});

it('scopes to active coupons', function (): void {
    expect(Coupon::query()->active()->pluck('code')->sort()->values()->all())
        ->toBe(['ACTIVE', 'EXHAUSTED', 'EXPIRED']);
});

it('scopes to expired coupons', function (): void {
    expect(Coupon::query()->expired()->pluck('code')->all())->toBe(['EXPIRED']);
});

it('scopes to exhausted coupons', function (): void {
    expect(Coupon::query()->exhausted()->pluck('code')->all())->toBe(['EXHAUSTED']);
});

it('scopes to redeemable coupons only', function (): void {
    expect(Coupon::query()->redeemable()->pluck('code')->all())->toBe(['ACTIVE']);
});

it('includes an unlimited active coupon in the redeemable set', function (): void {
    Coupon::factory()->active()->create(['code' => 'UNLIMITED', 'max_usage' => 0, 'usage' => 99]);

    expect(Coupon::query()->redeemable()->pluck('code')->sort()->values()->all())
        ->toBe(['ACTIVE', 'UNLIMITED']);
});

it('scopes by code', function (): void {
    expect(Coupon::query()->whereCode('ACTIVE')->first()?->code)->toBe('ACTIVE');
});

it('binds routes by the configured key', function (): void {
    expect((new Coupon)->getRouteKeyName())->toBe('code');

    config()->set('coupons.route_key', 'id');
    expect((new Coupon)->getRouteKeyName())->toBe('id');
});

it('resolves route bindings by code', function (): void {
    $resolved = (new Coupon)->resolveRouteBinding('ACTIVE');

    expect($resolved?->getKey())->toBe($this->active->getKey());
});
