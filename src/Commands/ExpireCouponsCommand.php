<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use RoundlyConsulting\Coupons\Models\Coupon;

final class ExpireCouponsCommand extends Command
{
    protected $signature = 'coupons:expire {--code= : Expire only the coupon with this code}';

    protected $description = 'Immediately expire coupons (an admin kill-switch).';

    public function handle(): int
    {
        $code = $this->option('code');

        $query = Coupon::query()
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', CarbonImmutable::now());
            });

        if (is_string($code) && $code !== '') {
            $query->where('code', $code);
        }

        $count = $query->update(['expires_at' => CarbonImmutable::now()]);

        $this->info("Expired {$count} coupon(s).");

        return self::SUCCESS;
    }
}
