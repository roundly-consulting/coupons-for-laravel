<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\Actions\CreateCouponAction;
use RoundlyConsulting\Coupons\DataTransferObjects\CreateCouponData;
use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Money\Money;

it('creates, persists, and applies a coupon end to end', function (): void {
    $coupon = app(CreateCouponAction::class)->execute(
        new CreateCouponData(type: DiscountType::Percentage, value: 2000, code: 'SAVE20'),
    );

    $coupon->activate()->save();

    $fresh = Coupon::query()->where('code', 'SAVE20')->firstOrFail();

    expect($fresh->canBeApplied())->toBeTrue()
        ->and($fresh->apply(Money::ofMinor(5000, 'EUR'))->minor())->toBe('4000');
});

it('exposes the config handle with a default model', function (): void {
    expect(config('coupons.model'))->toBe(Coupon::class);
});
