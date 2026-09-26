<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use RoundlyConsulting\Coupons\Support\CouponModel;

final class PruneCouponsCommand extends Command
{
    protected $signature = 'coupons:prune {--days=30 : Prune coupons expired more than this many days ago}
                            {--force : Permanently delete instead of soft deleting}';

    protected $description = 'Prune coupons that expired more than the given number of days ago.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $force = (bool) $this->option('force');
        $threshold = CarbonImmutable::now()->subDays($days);

        $pruned = 0;

        CouponModel::class()::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $threshold)
            ->chunkById(100, function ($coupons) use ($force, &$pruned): void {
                foreach ($coupons as $coupon) {
                    $force ? $coupon->forceDelete() : $coupon->delete();
                    $pruned++;
                }
            });

        $this->info("Pruned {$pruned} coupon(s).");

        return self::SUCCESS;
    }
}
