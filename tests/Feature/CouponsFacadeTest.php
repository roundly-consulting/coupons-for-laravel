<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Coupons\Facades\Coupons;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\ValueObjects\Money;

it('generates a coupon through the facade', function (): void {
    $coupon = Coupons::generate(DiscountType::Fixed, 500, 'FIVE');

    expect($coupon)->toBeInstanceOf(Coupon::class)
        ->code->toBe('FIVE');
});

it('finds a coupon through the facade', function (): void {
    Coupon::factory()->create(['code' => 'HELLO']);

    expect(Coupons::find('HELLO')?->code)->toBe('HELLO');
});

it('redeems a coupon through the facade', function (): void {
    Coupon::factory()->fixed(500)->active()->create(['code' => 'FIVE']);

    $result = Coupons::redeem('FIVE', new Money(5000, 'EUR'));

    expect($result->total->getAmount())->toBe(4500);
});
