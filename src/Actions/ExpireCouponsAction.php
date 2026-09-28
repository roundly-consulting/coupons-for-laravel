<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\Support\CouponModel;

/**
 * The admin kill-switch: revoke every live coupon — one with no expiry yet, or one still
 * in the future — through RevokeCouponAction, so CouponRevoked fires once per coupon.
 */
final readonly class ExpireCouponsAction
{
    public function __construct(
        private RevokeCouponAction $revoke,
    ) {}

    /**
     * Returns how many coupons were revoked. `$code` narrows the sweep to the live coupon
     * holding that code (zero when there is none). chunkById is safe while the filtered
     * column changes under it: it pages by key, not by offset.
     */
    public function execute(?string $code = null): int
    {
        $query = CouponModel::class()::query()
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', CarbonImmutable::now());
            });

        if ($code !== null) {
            $query->whereCode($code);
        }

        $count = 0;

        $query->chunkById(100, function (Collection $coupons) use (&$count): void {
            /** @var Collection<int, Coupon> $coupons */
            foreach ($coupons as $coupon) {
                $this->revoke->execute($coupon);
                $count++;
            }
        });

        return $count;
    }
}
