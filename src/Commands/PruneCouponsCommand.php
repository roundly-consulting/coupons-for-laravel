<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use RoundlyConsulting\Coupons\CouponManager;

final class PruneCouponsCommand extends Command
{
    protected $signature = 'coupons:prune {--days=30 : Prune coupons expired more than this many days ago}
                            {--force : Permanently delete instead of soft deleting}';

    protected $description = 'Prune coupons that expired more than the given number of days ago.';

    /**
     * `Coupons::prune()`. A negative window is refused rather than reaching into the future.
     */
    public function handle(CouponManager $coupons): int
    {
        try {
            $pruned = $coupons->prune((int) $this->option('days'), (bool) $this->option('force'));
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Pruned {$pruned} coupon(s).");

        return self::SUCCESS;
    }
}
