<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\Coupons\CouponManager;

final class ExpireCouponsCommand extends Command
{
    protected $signature = 'coupons:expire {--code= : Expire only the coupon with this code}';

    protected $description = 'Immediately expire coupons (an admin kill-switch).';

    /**
     * `Coupons::expireAll()`: each live coupon is revoked like `Coupons::revoke()` does it,
     * so CouponRevoked fires per coupon.
     */
    public function handle(CouponManager $coupons): int
    {
        $code = $this->option('code');

        $count = $coupons->expireAll(is_string($code) && $code !== '' ? $code : null);

        $this->info("Expired {$count} coupon(s).");

        return self::SUCCESS;
    }
}
