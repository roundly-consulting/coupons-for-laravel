<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Facades;

use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Coupons\CouponManager;

/**
 * @method static \RoundlyConsulting\Coupons\Models\Coupon generate(\RoundlyConsulting\Coupons\Enums\DiscountType $type, int $value, ?string $code = null, int $maxUsage = 0)
 * @method static \RoundlyConsulting\Coupons\Models\Coupon create(\RoundlyConsulting\Coupons\DataTransferObjects\CreateCouponData $data)
 * @method static ?\RoundlyConsulting\Coupons\Models\Coupon find(string $code)
 * @method static \RoundlyConsulting\Coupons\Models\Coupon findOrFail(string $code)
 * @method static \RoundlyConsulting\Coupons\DataTransferObjects\RedemptionResult redeem(\RoundlyConsulting\Coupons\Models\Coupon|string $coupon, \RoundlyConsulting\Coupons\ValueObjects\Money $price, ?\Illuminate\Database\Eloquent\Model $redeemer = null)
 * @method static \RoundlyConsulting\Coupons\Testing\FakeCouponManager fake()
 *
 * @see CouponManager
 */
final class Coupons extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CouponManager::class;
    }
}
