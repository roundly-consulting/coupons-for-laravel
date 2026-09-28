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
     * `Coupons::prune()`. A window that is not a whole number of days is refused rather than
     * read as 0 (which would prune every expired coupon), and a negative one rather than
     * reaching into the future.
     */
    public function handle(CouponManager $coupons): int
    {
        $option = $this->option('days');
        $days = is_string($option) || is_int($option) ? filter_var($option, FILTER_VALIDATE_INT) : false;

        if ($days === false) {
            $given = is_string($option) ? $option : '';
            $this->error("The --days option must be a whole number of days, \"{$given}\" given.");

            return self::FAILURE;
        }

        try {
            $pruned = $coupons->prune($days, (bool) $this->option('force'));
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Pruned {$pruned} coupon(s).");

        return self::SUCCESS;
    }
}
