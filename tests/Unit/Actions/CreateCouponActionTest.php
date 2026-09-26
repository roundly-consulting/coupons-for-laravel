<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use RoundlyConsulting\Coupons\Actions\CreateCouponAction;
use RoundlyConsulting\Coupons\DataTransferObjects\CreateCouponData;
use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Coupons\Events\CouponCreated;
use RoundlyConsulting\Coupons\Exceptions\InvalidCouponDefinition;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Money\Currency;
use RoundlyConsulting\Money\Exceptions\CurrencyMismatch;
use RoundlyConsulting\Money\Money;

it('creates a coupon with a generated code and dispatches an event', function (): void {
    Event::fake(CouponCreated::class);

    $coupon = app(CreateCouponAction::class)->execute(
        CreateCouponData::fixed(Money::ofMinor(100, 'EUR')),
    );

    expect($coupon)
        ->toBeInstanceOf(Coupon::class)
        ->code->not->toBeEmpty();

    Event::assertDispatched(fn (CouponCreated $e): bool => $e->coupon->is($coupon));

    $this->assertDatabaseHas('coupons', [
        'type' => DiscountType::Fixed->value,
        'value' => 100,
        'code' => $coupon->code,
        'currency' => 'EUR',
    ]);
});

it('creates a coupon with a custom code and max usage', function (): void {
    Event::fake(CouponCreated::class);

    $coupon = app(CreateCouponAction::class)->execute(
        new CreateCouponData(
            type: DiscountType::Percentage,
            value: 5000,
            code: 'PAYHALF',
            maxUsage: 3,
        ),
    );

    Event::assertDispatched(fn (CouponCreated $e): bool => $e->coupon->is($coupon));

    $this->assertDatabaseHas('coupons', [
        'type' => DiscountType::Percentage->value,
        'value' => 5000,
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
        CreateCouponData::fixed(Money::ofMinor(100, 'EUR')),
    );

    expect($coupon->code)->toBe('ZZZ999');

    Str::createRandomStringsNormally();
});

it('rejects a fixed coupon without a currency', function (): void {
    app(CreateCouponAction::class)->execute(new CreateCouponData(type: DiscountType::Fixed, value: 500, code: 'NOCUR'));
})->throws(InvalidCouponDefinition::class, 'Fixed coupon [NOCUR] must be locked to a currency');

it('rejects a negative fixed value', function (): void {
    app(CreateCouponAction::class)->execute(new CreateCouponData(type: DiscountType::Fixed, value: -1, currency: Currency::of('EUR')));
})->throws(InvalidCouponDefinition::class, 'cannot be negative');

it('rejects a percentage outside 0..10000 basis points', function (int $basisPoints): void {
    expect(fn () => app(CreateCouponAction::class)->execute(new CreateCouponData(type: DiscountType::Percentage, value: $basisPoints)))
        ->toThrow(InvalidCouponDefinition::class, 'basis points');

    expect(Coupon::query()->count())->toBe(0);
})->with([-1, 10_001]);

it('accepts the percentage bounds 0 and 10000 basis points', function (): void {
    $action = app(CreateCouponAction::class);

    expect($action->execute(new CreateCouponData(type: DiscountType::Percentage, value: 0))->value)->toBe(0)
        ->and($action->execute(new CreateCouponData(type: DiscountType::Percentage, value: 10_000))->value)->toBe(10_000);
});

it('persists the currency lock, minimum spend and cap as money', function (): void {
    $coupon = app(CreateCouponAction::class)->execute(CreateCouponData::percentage(
        '12.5',
        code: 'EIGHTH',
        maxDiscount: Money::ofMinor(1500, 'EUR'),
        minimumSpend: Money::ofMinor(5000, 'EUR'),
    ), quiet: true);

    $fresh = Coupon::query()->where('code', 'EIGHTH')->firstOrFail();

    expect($coupon->value)->toBe(1250)
        ->and($fresh->currency?->code)->toBe('EUR')
        ->and($fresh->minimum_spend?->equals(Money::ofMinor(5000, 'EUR')))->toBeTrue()
        ->and($fresh->max_discount?->equals(Money::ofMinor(1500, 'EUR')))->toBeTrue();
});

it('locks a coupon to the currency of its minimum spend', function (): void {
    $coupon = app(CreateCouponAction::class)->execute(CreateCouponData::freeShipping(
        code: 'SHIPFREE',
        minimumSpend: Money::ofMinor(3000, 'JPY'),
    ), quiet: true);

    expect($coupon->fresh()?->currency?->code)->toBe('JPY');
});

it('refuses a cap in another currency than the coupon lock', function (): void {
    app(CreateCouponAction::class)->execute(new CreateCouponData(
        type: DiscountType::Percentage,
        value: 1000,
        currency: Currency::of('EUR'),
        maxDiscount: Money::ofMinor(500, 'USD'),
    ), quiet: true);
})->throws(CurrencyMismatch::class);
