<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Coupons\DataTransferObjects\CreateCouponData;
use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Coupons\Exceptions\CouponCodeTaken;
use RoundlyConsulting\Coupons\Exceptions\CouponException;
use RoundlyConsulting\Coupons\Facades\Coupons;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\Tests\Fixtures\Customer;
use RoundlyConsulting\Money\Money;

/**
 * A code is unique among coupons that are not soft-deleted. The unique index used to span
 * trashed rows, so a code `coupons:prune` had soft-deleted could never be used again — the
 * next season's `XMAS` died on a raw UniqueConstraintViolationException, and only `--force`
 * freed it, cascading away the old coupon's redemption history with it.
 */
it('reuses the code of a pruned coupon and keeps the old history', function (): void {
    $old = Coupon::factory()->active()->fixed()->create(['code' => 'XMAS', 'expires_at' => now()->subDays(40)]);
    $customer = Customer::query()->create(['name' => 'Ada']);
    $old->redemptions()->create([
        'redeemer_type' => $customer->getMorphClass(),
        'redeemer_id' => $customer->getKey(),
        'amount_discounted' => Money::ofMinor(100, 'USD'),
    ]);

    expect(Coupons::prune(30))->toBe(1)
        ->and(Coupons::find('XMAS'))->toBeNull();

    $new = Coupons::generate(DiscountType::Percentage, 1000, code: 'XMAS');

    expect(Coupons::find('XMAS')?->is($new))->toBeTrue()
        ->and(Coupon::withTrashed()->where('code', 'XMAS')->count())->toBe(2)
        ->and($old->redemptions()->count())->toBe(1)
        ->and($new->redemptions()->count())->toBe(0)
        // The new XMAS is a fresh coupon: last season's use neither counts against its
        // per-redeemer cap nor shows up as a redemption of it.
        ->and($customer->hasRedeemed('XMAS'))->toBeFalse();
});

it('refuses an explicit code a live coupon holds with a coupon exception', function (): void {
    Coupons::generate(DiscountType::Percentage, 1000, code: 'DUP');

    expect(fn () => Coupons::create(CreateCouponData::percentage(20, code: ' dup ')))
        ->toThrow(CouponCodeTaken::class, 'Coupon code [DUP] is already taken.')
        ->and(Coupon::query()->count())->toBe(1)
        ->and(CouponCodeTaken::forCode('DUP'))->toBeInstanceOf(CouponException::class);
});

it('refuses a duplicate that lands between the check and the insert', function (): void {
    $raced = false;

    // Simulate the race: another request inserts the same code right after our check.
    DB::listen(function (QueryExecuted $query) use (&$raced): void {
        if ($raced || ! str_contains($query->sql, 'from "coupons" where "coupons"."code" = ?')) {
            return;
        }

        $raced = true;

        Coupon::factory()->create(['code' => 'RACE']);
    });

    expect(fn () => Coupons::generate(DiscountType::Percentage, 1000, code: 'RACE'))
        ->toThrow(CouponCodeTaken::class)
        ->and($raced)->toBeTrue()
        ->and(Coupon::query()->count())->toBe(1);
});

it('keeps codes unique among live coupons at the database level', function (): void {
    Coupon::factory()->create(['code' => 'LIVE']);

    expect(fn () => Coupon::factory()->create(['code' => 'LIVE']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('refuses to restore a trashed coupon over a live one holding its code', function (): void {
    $old = Coupon::factory()->create(['code' => 'BACK']);
    $old->delete();
    Coupon::factory()->create(['code' => 'BACK']);

    expect(fn () => $old->restore())->toThrow(UniqueConstraintViolationException::class);
});

it('keeps the generated uniqueness column out of serialisation and replicas', function (): void {
    $coupon = Coupon::factory()->create(['code' => 'ORIGINAL'])->fresh();

    $copy = $coupon?->replicate();
    $copy?->setAttribute('code', 'COPY');
    $copy?->save();

    expect($coupon?->toArray())->not->toHaveKey('undeleted_code')
        ->and(Coupons::find('COPY'))->not->toBeNull();
});
