<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\Rules\Redeemable;
use RoundlyConsulting\Coupons\Tests\Fixtures\Customer;
use RoundlyConsulting\Coupons\ValueObjects\Money;

function validateCode(string $code, ?Redeemable $rule = null): Illuminate\Validation\Validator
{
    return Validator::make(
        ['code' => $code],
        ['code' => $rule ?? new Redeemable],
    );
}

it('passes for a redeemable coupon', function (): void {
    Coupon::factory()->active()->fixed()->create(['code' => 'SAVE10']);

    expect(validateCode('SAVE10')->passes())->toBeTrue();
});

it('fails with the not-found message for an unknown code', function (): void {
    $validator = validateCode('NOPE');

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('code'))->toBe('The coupon "NOPE" does not exist.');
});

it('fails for an expired coupon', function (): void {
    Coupon::factory()->active()->expired()->create(['code' => 'OLD']);

    $validator = validateCode('OLD');

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('code'))->toBe('This coupon is no longer available.');
});

it('fails for a coupon at its usage limit', function (): void {
    Coupon::factory()->active()->create(['code' => 'MAXED', 'max_usage' => 1, 'usage' => 1]);

    $validator = validateCode('MAXED');

    expect($validator->errors()->first('code'))->toBe('This coupon has reached its usage limit.');
});

it('fails for a redeemer who already redeemed the coupon', function (): void {
    $coupon = Coupon::factory()->active()->create(['code' => 'ONCE', 'max_usage_per_redeemer' => 1]);
    $customer = Customer::query()->create(['name' => 'Ada']);
    $coupon->redemptions()->create([
        'redeemer_type' => $customer->getMorphClass(),
        'redeemer_id' => $customer->getKey(),
        'amount_discounted' => 100,
        'currency' => 'USD',
    ]);

    $validator = validateCode('ONCE', new Redeemable(redeemer: $customer));

    expect($validator->errors()->first('code'))->toBe('You have already used this coupon.');
});

it('fails when the cart total is below the minimum spend', function (): void {
    Coupon::factory()->active()->fixed()->withMinimumSpend(2000)->create(['code' => 'BIG']);

    $validator = validateCode('BIG', new Redeemable(cartTotal: new Money(1000, 'USD')));

    expect($validator->errors()->first('code'))->toBe('Your total does not meet this coupon’s minimum spend.');
});

it('fails when the cart currency does not match', function (): void {
    Coupon::factory()->active()->fixed()->forCurrency('EUR')->create(['code' => 'EUR10']);

    $validator = validateCode('EUR10', new Redeemable(cartTotal: new Money(1000, 'USD')));

    expect($validator->errors()->first('code'))->toBe('This coupon cannot be used in the selected currency.');
});

it('skips money checks without a cart total', function (): void {
    Coupon::factory()->active()->fixed()->forCurrency('EUR')->withMinimumSpend(9999)->create(['code' => 'EURONLY']);

    expect(validateCode('EURONLY')->passes())->toBeTrue();
});

it('skips the per-redeemer check without a redeemer', function (): void {
    $coupon = Coupon::factory()->active()->create(['code' => 'SHARED', 'max_usage_per_redeemer' => 1]);
    $customer = Customer::query()->create(['name' => 'Ada']);
    $coupon->redemptions()->create([
        'redeemer_type' => $customer->getMorphClass(),
        'redeemer_id' => $customer->getKey(),
        'amount_discounted' => 100,
        'currency' => 'USD',
    ]);

    expect(validateCode('SHARED')->passes())->toBeTrue();
});

it('fails an empty or non-string code as not found', function (): void {
    $failures = [];
    $fail = function (string $key) use (&$failures): object {
        $failures[] = $key;

        return new class
        {
            public function translate(array $replace = []): void {}
        };
    };

    (new Redeemable)->validate('code', '', $fail);
    (new Redeemable)->validate('code', 123, $fail);

    expect($failures)->toBe([
        'coupons::messages.not_found',
        'coupons::messages.not_found',
    ]);
});

it('respects a swapped locale for the failure message', function (): void {
    app('translator')->addLines([
        'messages.expired' => 'Ce coupon n’est plus disponible.',
    ], 'fr', 'coupons');
    app()->setLocale('fr');

    Coupon::factory()->active()->expired()->create(['code' => 'OLD']);

    expect(validateCode('OLD')->errors()->first('code'))->toBe('Ce coupon n’est plus disponible.');
});
