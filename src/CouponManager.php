<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Coupons\Actions\CreateCouponAction;
use RoundlyConsulting\Coupons\Actions\RedeemCouponAction;
use RoundlyConsulting\Coupons\DataTransferObjects\CreateCouponData;
use RoundlyConsulting\Coupons\DataTransferObjects\RedeemCouponData;
use RoundlyConsulting\Coupons\DataTransferObjects\RedemptionResult;
use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Coupons\Exceptions\CouponNotFound;
use RoundlyConsulting\Coupons\Facades\Coupons;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\Testing\FakeCouponManager;
use RoundlyConsulting\Coupons\ValueObjects\Money;

class CouponManager
{
    public function __construct(
        private readonly CreateCouponAction $createCoupon,
        private readonly RedeemCouponAction $redeemCoupon,
    ) {}

    /**
     * Generate a coupon, auto-creating a code when none is given.
     */
    public function generate(DiscountType $type, int $value, ?string $code = null, int $maxUsage = 0): Coupon
    {
        return $this->create(new CreateCouponData(
            type: $type,
            value: $value,
            code: $code,
            maxUsage: $maxUsage,
        ));
    }

    public function create(CreateCouponData $data): Coupon
    {
        return $this->createCoupon->execute($data);
    }

    public function find(string $code): ?Coupon
    {
        return $this->newQuery()->where('code', $code)->first();
    }

    /**
     * @throws CouponNotFound when no coupon matches the code.
     */
    public function findOrFail(string $code): Coupon
    {
        return $this->find($code) ?? throw CouponNotFound::forCode($code);
    }

    /**
     * Redeem a coupon by code or model: validates, increments usage atomically,
     * records the redemption, and fires CouponRedeemed.
     */
    public function redeem(Coupon|string $coupon, Money $price, ?Model $redeemer = null): RedemptionResult
    {
        return $this->redeemCoupon->execute(
            new RedeemCouponData(coupon: $coupon, price: $price, redeemer: $redeemer),
        );
    }

    /**
     * Swap the container binding for a fake that records calls without touching
     * the database, and return it for assertions.
     */
    public function fake(): FakeCouponManager
    {
        $fake = new FakeCouponManager(
            $this->createCoupon,
            $this->redeemCoupon,
        );

        app()->instance(self::class, $fake);

        // Drop any instance the Coupons facade has already resolved so calls
        // through the facade hit the fake from here on.
        Coupons::clearResolvedInstance(self::class);

        return $fake;
    }

    /**
     * @return Builder<Coupon>
     */
    private function newQuery(): Builder
    {
        /** @var class-string<Coupon> $model */
        $model = config('coupons.model', Coupon::class);

        return $model::query();
    }
}
