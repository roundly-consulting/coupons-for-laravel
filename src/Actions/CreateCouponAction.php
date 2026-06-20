<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Actions;

use Illuminate\Support\Str;
use RoundlyConsulting\Coupons\DataTransferObjects\CreateCouponData;
use RoundlyConsulting\Coupons\Events\CouponCreated;
use RoundlyConsulting\Coupons\Models\Coupon;

final class CreateCouponAction
{
    /**
     * Create and persist a coupon. Pass $quiet to skip the CouponCreated event,
     * for seeders and fixtures that don't want listeners to fire.
     */
    public function execute(CreateCouponData $data, bool $quiet = false): Coupon
    {
        $coupon = $this->newModelInstance([
            'type' => $data->type,
            'value' => $data->value,
            'code' => $data->code ?? $this->createUniqueCode(),
            'max_usage' => $data->maxUsage,
        ]);

        $coupon->save();

        if (! $quiet) {
            CouponCreated::dispatch($coupon);
        }

        return $coupon;
    }

    private function createUniqueCode(): string
    {
        do {
            $code = mb_strtoupper(Str::random(6));
        } while ($this->newModelInstance()->newQuery()->where('code', $code)->exists());

        return $code;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function newModelInstance(array $attributes = []): Coupon
    {
        /** @var class-string<Coupon> $model */
        $model = config('coupons.model', Coupon::class);

        return new $model($attributes);
    }
}
