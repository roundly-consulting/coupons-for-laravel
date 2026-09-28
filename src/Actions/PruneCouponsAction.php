<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\Support\CouponModel;

/**
 * Delete coupons that expired more than a given number of days ago.
 */
final readonly class PruneCouponsAction
{
    /**
     * Soft deletes by default. `$force` deletes permanently — including coupons an earlier
     * soft prune already trashed, which would otherwise never be purged. Returns how many
     * coupons were pruned.
     *
     * @throws InvalidArgumentException when `$days` is negative: the window would reach into
     *                                  the future and delete coupons that are still live.
     */
    public function execute(int $days = 30, bool $force = false): int
    {
        if ($days < 0) {
            throw new InvalidArgumentException("The prune window must be zero days or more, {$days} given.");
        }

        $query = CouponModel::class()::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', CarbonImmutable::now()->subDays($days));

        if ($force) {
            $query->withTrashed();
        }

        $pruned = 0;

        $query->chunkById(100, function (Collection $coupons) use ($force, &$pruned): void {
            /** @var Collection<int, Coupon> $coupons */
            foreach ($coupons as $coupon) {
                $force ? $coupon->forceDelete() : $coupon->delete();
                $pruned++;
            }
        });

        return $pruned;
    }
}
