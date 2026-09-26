<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\DataTransferObjects\RedeemCouponData;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\Tests\Fixtures\Customer;
use RoundlyConsulting\Money\Money;

it('holds the redemption inputs', function (): void {
    $customer = new Customer(['id' => 1]);
    $price = Money::ofMinor(5000, 'EUR');

    $data = new RedeemCouponData(coupon: 'SAVE20', price: $price, redeemer: $customer);

    expect($data->coupon)->toBe('SAVE20')
        ->and($data->price)->toBe($price)
        ->and($data->redeemer)->toBe($customer);
});

it('defaults the redeemer to null and accepts a model', function (): void {
    expect((new RedeemCouponData(coupon: 'X', price: Money::ofMinor(1, 'EUR')))->redeemer)->toBeNull();

    $coupon = Coupon::factory()->make();
    expect((new RedeemCouponData(coupon: $coupon, price: Money::ofMinor(1, 'EUR')))->coupon)->toBe($coupon);
});
