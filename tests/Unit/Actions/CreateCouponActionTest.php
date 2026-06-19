<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use RoundlyConsulting\Coupons\Actions\CreateCouponAction;
use RoundlyConsulting\Coupons\DataTransferObjects\CreateCouponData;
use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Coupons\Events\CouponCreated;
use RoundlyConsulting\Coupons\Models\Coupon;

it('creates a coupon with a generated code and dispatches an event', function (): void {
    Event::fake(CouponCreated::class);

    $coupon = app(CreateCouponAction::class)->execute(
        new CreateCouponData(type: DiscountType::Fixed, value: 100),
    );

    expect($coupon)
        ->toBeInstanceOf(Coupon::class)
        ->code->not->toBeEmpty();

    Event::assertDispatched(fn (CouponCreated $e): bool => $e->coupon->is($coupon));

    $this->assertDatabaseHas('coupons', [
        'type' => DiscountType::Fixed->value,
        'value' => 100,
        'code' => $coupon->code,
    ]);
});

it('creates a coupon with a custom code and max usage', function (): void {
    Event::fake(CouponCreated::class);

    $coupon = app(CreateCouponAction::class)->execute(
        new CreateCouponData(
            type: DiscountType::Percentage,
            value: 50,
            code: 'PAYHALF',
            maxUsage: 3,
        ),
    );

    Event::assertDispatched(fn (CouponCreated $e): bool => $e->coupon->is($coupon));

    $this->assertDatabaseHas('coupons', [
        'type' => DiscountType::Percentage->value,
        'value' => 50,
        'code' => 'PAYHALF',
        'max_usage' => 3,
    ]);
});

it('generates a unique code when one already exists', function (): void {
    Event::fake(CouponCreated::class);

    Coupon::factory()->create(['code' => 'ABC123']);

    $sequence = ['ABC123', 'ZZZ999'];
    $index = 0;
    Str::createRandomStringsUsing(function () use (&$index, $sequence): string {
        return mb_strtolower($sequence[$index++] ?? 'fallback');
    });

    $coupon = app(CreateCouponAction::class)->execute(
        new CreateCouponData(type: DiscountType::Fixed, value: 100),
    );

    expect($coupon->code)->toBe('ZZZ999');

    Str::createRandomStringsNormally();
});
