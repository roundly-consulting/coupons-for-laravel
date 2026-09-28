<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Facades;

use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Coupons\CouponManager;
use RoundlyConsulting\Coupons\Testing\CouponsFake;

/**
 * @method static \RoundlyConsulting\Coupons\Models\Coupon generate(\RoundlyConsulting\Coupons\Enums\DiscountType $type, int $value, ?string $code = null, int $maxUsage = 0, \RoundlyConsulting\Money\Currency|string|null $currency = null)
 * @method static \RoundlyConsulting\Coupons\Models\Coupon create(\RoundlyConsulting\Coupons\DataTransferObjects\CreateCouponData $data)
 * @method static \RoundlyConsulting\Coupons\Models\Coupon createQuietly(\RoundlyConsulting\Coupons\DataTransferObjects\CreateCouponData $data)
 * @method static ?\RoundlyConsulting\Coupons\Models\Coupon find(string $code)
 * @method static \RoundlyConsulting\Coupons\Models\Coupon findOrFail(string $code)
 * @method static bool exists(string $code)
 * @method static \Illuminate\Database\Eloquent\Builder<\RoundlyConsulting\Coupons\Models\Coupon> redeemable()
 * @method static \RoundlyConsulting\Coupons\Handles\CouponCode code(\RoundlyConsulting\Coupons\Models\Coupon|string $coupon)
 * @method static ?\RoundlyConsulting\Coupons\Enums\RedemptionFailureReason check(\RoundlyConsulting\Coupons\Models\Coupon|string $coupon, ?\RoundlyConsulting\Money\Money $price = null, ?\Illuminate\Database\Eloquent\Model $redeemer = null)
 * @method static \RoundlyConsulting\Money\Money preview(\RoundlyConsulting\Coupons\Models\Coupon|string $coupon, \RoundlyConsulting\Money\Money $price)
 * @method static \RoundlyConsulting\Coupons\DataTransferObjects\RedemptionResult redeem(\RoundlyConsulting\Coupons\Models\Coupon|string $coupon, \RoundlyConsulting\Money\Money $price, ?\Illuminate\Database\Eloquent\Model $redeemer = null)
 * @method static \RoundlyConsulting\Coupons\Models\Coupon revoke(\RoundlyConsulting\Coupons\Models\Coupon|string $coupon)
 * @method static int expireAll(?string $code = null)
 * @method static int prune(int $days = 30, bool $force = false)
 *
 * @see CouponManager
 */
final class Coupons extends Facade
{
    /**
     * Swap the manager for a recorder that writes nothing — to the facade and to every
     * constructor-injected CouponManager — and return it for assertions.
     */
    public static function fake(): CouponsFake
    {
        $fake = app(CouponsFake::class);

        self::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return CouponManager::class;
    }
}
