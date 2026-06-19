<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\Actions\CreateCouponAction;
use RoundlyConsulting\Coupons\DataTransferObjects\CreateCouponData;
use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\ValueObjects\Money;

it('creates, persists, and applies a coupon end to end', function (): void {
    $coupon = app(CreateCouponAction::class)->execute(
        new CreateCouponData(type: DiscountType::Percentage, value: 20, code: 'SAVE20'),
    );

    $coupon->activate()->save();

    $fresh = Coupon::query()->where('code', 'SAVE20')->firstOrFail();

    expect($fresh->canBeApplied())->toBeTrue()
        ->and($fresh->apply(new Money(5000, 'EUR'))->getAmount())->toBe(4000);
});

it('exposes the config handle with a default model', function (): void {
    expect(config('coupons.model'))->toBe(Coupon::class);
});
