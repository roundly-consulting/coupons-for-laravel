<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Handles;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Coupons\CouponManager;
use RoundlyConsulting\Coupons\DataTransferObjects\RedemptionResult;
use RoundlyConsulting\Coupons\Enums\RedemptionFailureReason;
use RoundlyConsulting\Coupons\Exceptions\CouponNotFound;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Money\Money;

/**
 * One coupon, by code or model — `Coupons::code('SUMMER')`. Every method delegates to the
 * manager's flat verb, so a fake records it and a container override applies.
 */
final readonly class CouponCode
{
    public function __construct(
        private CouponManager $manager,
        private Coupon|string $coupon,
    ) {}

    /**
     * Why the coupon would be refused right now, or null when it is redeemable. Never
     * throws; an unknown code reports NotFound.
     */
    public function check(?Money $price = null, ?Model $redeemer = null): ?RedemptionFailureReason
    {
        return $this->manager->check($this->coupon, $price, $redeemer);
    }

    /**
     * The discount the coupon would take off the price — zero on a currency mismatch.
     *
     * @throws CouponNotFound when the code resolves to no coupon.
     */
    public function preview(Money $price): Money
    {
        return $this->manager->preview($this->coupon, $price);
    }

    public function redeem(Money $price, ?Model $redeemer = null): RedemptionResult
    {
        return $this->manager->redeem($this->coupon, $price, $redeemer);
    }

    /**
     * @throws CouponNotFound when the code resolves to no coupon.
     */
    public function revoke(): Coupon
    {
        return $this->manager->revoke($this->coupon);
    }
}
