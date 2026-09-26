<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Coupons\Actions\CreateCouponAction;
use RoundlyConsulting\Coupons\Actions\RedeemCouponAction;
use RoundlyConsulting\Coupons\Actions\RevokeCouponAction;
use RoundlyConsulting\Coupons\DataTransferObjects\CreateCouponData;
use RoundlyConsulting\Coupons\DataTransferObjects\RedeemCouponData;
use RoundlyConsulting\Coupons\DataTransferObjects\RedemptionResult;
use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Coupons\Exceptions\CouponNotFound;
use RoundlyConsulting\Coupons\Facades\Coupons;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\Support\CouponModel;
use RoundlyConsulting\Coupons\Testing\FakeCouponManager;
use RoundlyConsulting\Money\Currency;
use RoundlyConsulting\Money\Money;

class CouponManager
{
    public function __construct(
        private readonly CreateCouponAction $createCoupon,
        private readonly RedeemCouponAction $redeemCoupon,
    ) {}

    /**
     * Generate a coupon, auto-creating a code when none is given. `$value` is minor units of
     * `$currency` for a fixed coupon (which must be locked to a currency) and basis points
     * for a percentage (2500 = 25 %).
     */
    public function generate(DiscountType $type, int $value, ?string $code = null, int $maxUsage = 0, Currency|string|null $currency = null): Coupon
    {
        return $this->create(new CreateCouponData(
            type: $type,
            value: $value,
            currency: is_string($currency) ? Currency::of($currency) : $currency,
            code: $code,
            maxUsage: $maxUsage,
        ));
    }

    public function create(CreateCouponData $data): Coupon
    {
        return $this->createCoupon->execute($data);
    }

    /**
     * Create a coupon without dispatching CouponCreated, for seeders and fixtures.
     */
    public function createQuietly(CreateCouponData $data): Coupon
    {
        return $this->createCoupon->execute($data, quiet: true);
    }

    public function find(string $code): ?Coupon
    {
        return $this->newQuery()->where('code', $code)->first();
    }

    /**
     * A query scoped to coupons that can be redeemed right now.
     *
     * @return Builder<Coupon>
     */
    public function redeemable(): Builder
    {
        return $this->newQuery()->redeemable();
    }

    /**
     * Whether a coupon with the given code exists.
     */
    public function exists(string $code): bool
    {
        return $this->newQuery()->whereCode($code)->exists();
    }

    /**
     * Revoke a coupon by expiring it immediately. This is reversible — the row is
     * not deleted, only its expires_at is set to now — and fires CouponRevoked.
     *
     * @throws CouponNotFound when no coupon matches the code.
     */
    public function revoke(string $code): Coupon
    {
        // Resolved here rather than injected: the constructor is extended by hosts'
        // and the package's own fakes, so it stays as it is.
        return app(RevokeCouponAction::class)->execute($this->findOrFail($code));
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
        return CouponModel::class()::query();
    }
}
