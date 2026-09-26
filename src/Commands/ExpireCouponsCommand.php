<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use RoundlyConsulting\Coupons\Actions\RevokeCouponAction;
use RoundlyConsulting\Coupons\Support\CouponModel;

final class ExpireCouponsCommand extends Command
{
    protected $signature = 'coupons:expire {--code= : Expire only the coupon with this code}';

    protected $description = 'Immediately expire coupons (an admin kill-switch).';

    /**
     * Each coupon is revoked like `Coupons::revoke()` does it, so CouponRevoked fires
     * per coupon. chunkById is safe while the filtered column changes under it.
     */
    public function handle(RevokeCouponAction $revoke): int
    {
        $code = $this->option('code');

        $query = CouponModel::class()::query()
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', CarbonImmutable::now());
            });

        if (is_string($code) && $code !== '') {
            $query->where('code', $code);
        }

        $count = 0;

        $query->chunkById(100, function (Collection $coupons) use ($revoke, &$count): void {
            foreach ($coupons as $coupon) {
                $revoke->execute($coupon);
                $count++;
            }
        });

        $this->info("Expired {$count} coupon(s).");

        return self::SUCCESS;
    }
}
