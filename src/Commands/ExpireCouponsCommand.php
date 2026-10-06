<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\Coupons\CouponManager;
use RoundlyConsulting\Coupons\Support\CodeFormat;

final class ExpireCouponsCommand extends Command
{
    protected $signature = 'coupons:expire {--code= : Expire only the coupon with this code}';

    protected $description = 'Immediately expire coupons (an admin kill-switch).';

    /**
     * `Coupons::expireAll()`: each live coupon is revoked like `Coupons::revoke()` does it,
     * so CouponRevoked fires per coupon. Only an absent --code means "every coupon": a blank
     * one (`--code=`, an empty `--code="$CODE"`) is refused, never read as "all".
     */
    public function handle(CouponManager $coupons): int
    {
        $code = $this->option('code');
        $code = is_string($code) ? $code : null;

        if ($this->input->hasParameterOption('--code') && CodeFormat::normalize($code ?? '') === '') {
            $this->error('The --code option needs a coupon code; omit it to expire every coupon.');

            return self::FAILURE;
        }

        $count = $coupons->expireAll($code);

        $this->info("Expired {$count} coupon(s).");

        return self::SUCCESS;
    }
}
