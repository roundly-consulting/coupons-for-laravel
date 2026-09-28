<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Random\Randomizer;
use RoundlyConsulting\Coupons\DataTransferObjects\CreateCouponData;
use RoundlyConsulting\Coupons\Events\CouponCreated;
use RoundlyConsulting\Coupons\Exceptions\CouponCodeTaken;
use RoundlyConsulting\Coupons\Exceptions\InvalidCouponConfiguration;
use RoundlyConsulting\Coupons\Exceptions\InvalidCouponDefinition;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\Support\CodeFormat;
use RoundlyConsulting\Coupons\Support\CouponModel;
use RoundlyConsulting\Money\Exceptions\CurrencyMismatch;

final readonly class CreateCouponAction
{
    /**
     * How many candidates to try before giving up: a collision this persistent means the
     * configured code space is nearly full, and looping on would never terminate.
     */
    private const int MAX_CODE_ATTEMPTS = 10;

    /**
     * Create and persist a coupon. Pass $quiet to skip the CouponCreated event,
     * for seeders and fixtures that don't want listeners to fire.
     *
     * @throws InvalidCouponDefinition when a fixed coupon has no currency, a percentage is
     *                                 outside 0..10000 basis points, or a fixed value is negative.
     * @throws CurrencyMismatch when the minimum spend or cap is in another currency than the coupon.
     * @throws InvalidCouponConfiguration when a code must be generated and `coupons.code.*` is
     *                                    unusable or its code space is exhausted.
     * @throws CouponCodeTaken when a live coupon already holds the explicit code (a code only
     *                         soft-deleted coupons hold is free to reuse).
     */
    public function execute(CreateCouponData $data, bool $quiet = false): Coupon
    {
        $code = $data->code === null ? $this->createUniqueCode() : CodeFormat::normalize($data->code);

        $data->assertValid($code);

        if ($data->code !== null && $this->newModelInstance()->newQuery()->whereCode($code)->exists()) {
            throw CouponCodeTaken::forCode($code);
        }

        // `currency` first: minimum_spend and max_discount share it, and money's cast refuses
        // to re-denominate a currency column that already holds another code.
        $coupon = $this->newModelInstance([
            'type' => $data->type,
            'value' => $data->value,
            'code' => $code,
            'max_usage' => $data->maxUsage,
            'currency' => $data->lockedCurrency(),
            'minimum_spend' => $data->minimumSpend,
            'max_discount' => $data->maxDiscount,
        ]);

        try {
            $coupon->save();
        } catch (UniqueConstraintViolationException $e) {
            // Another request took the code between the check above and this insert; the
            // unique index on live codes is the arbiter.
            throw CouponCodeTaken::forCode($code, $e);
        }

        if (! $quiet) {
            CouponCreated::dispatch($coupon);
        }

        return $coupon;
    }

    /**
     * A code in the configured `coupons.code.*` format that no coupon holds. Trashed ones are
     * included even though their codes are free to reuse: a fresh code never collides with a
     * pruned coupon's redemption history.
     */
    private function createUniqueCode(): string
    {
        $randomizer = app(Randomizer::class);

        for ($attempt = 1; $attempt <= self::MAX_CODE_ATTEMPTS; $attempt++) {
            $code = CodeFormat::generate($randomizer);

            if (! $this->newModelInstance()->newQuery()->withTrashed()->whereCode($code)->exists()) {
                return $code;
            }
        }

        throw InvalidCouponConfiguration::codeSpaceExhausted(self::MAX_CODE_ATTEMPTS);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function newModelInstance(array $attributes = []): Coupon
    {
        $model = CouponModel::class();

        return new $model($attributes);
    }
}
