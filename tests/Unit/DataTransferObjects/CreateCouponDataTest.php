<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\DataTransferObjects\CreateCouponData;
use RoundlyConsulting\Coupons\Enums\DiscountType;

it('holds the coupon creation payload with defaults', function (): void {
    $data = new CreateCouponData(type: DiscountType::Fixed, value: 100);

    expect($data->type)->toBe(DiscountType::Fixed)
        ->and($data->value)->toBe(100)
        ->and($data->code)->toBeNull()
        ->and($data->maxUsage)->toBe(0);
});

it('accepts a custom code and max usage', function (): void {
    $data = new CreateCouponData(
        type: DiscountType::Percentage,
        value: 50,
        code: 'PAYHALF',
        maxUsage: 5,
    );

    expect($data->code)->toBe('PAYHALF')
        ->and($data->maxUsage)->toBe(5);
});
