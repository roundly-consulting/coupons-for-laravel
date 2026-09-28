<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use RoundlyConsulting\Coupons\Actions\CheckCouponAction;
use RoundlyConsulting\Coupons\Actions\CreateCouponAction;
use RoundlyConsulting\Coupons\Actions\ExpireCouponsAction;
use RoundlyConsulting\Coupons\Actions\PruneCouponsAction;
use RoundlyConsulting\Coupons\Actions\RedeemCouponAction;
use RoundlyConsulting\Coupons\Actions\RevokeCouponAction;
use RoundlyConsulting\Coupons\DataTransferObjects\CreateCouponData;
use RoundlyConsulting\Coupons\DataTransferObjects\RedeemCouponData;
use RoundlyConsulting\Coupons\DataTransferObjects\RedemptionResult;
use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Coupons\Enums\RedemptionFailureReason;
use RoundlyConsulting\Coupons\Exceptions\CouponNotFound;
use RoundlyConsulting\Coupons\Handles\CouponCode;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\Support\CouponModel;
use RoundlyConsulting\Money\Currency;
use RoundlyConsulting\Money\Money;

/**
 * The root of the `Coupons` facade, injectable on its own. Every mutation is one flat verb
 * that resolves its action from the container; `code()`, the Coupon model and the
 * HasCoupons trait all delegate here, so `Coupons::fake()` sees every call.
 *
 * Not final: `Coupons::fake()` swaps in CouponsFake, a subtype, so constructor-injected
 * managers keep type-checking under the fake.
 */
class CouponManager
{
    public function __construct(
        protected readonly Container $container,
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
        return $this->container->make(CreateCouponAction::class)->execute($data);
    }

    /**
     * Create a coupon without dispatching CouponCreated, for seeders and fixtures.
     */
    public function createQuietly(CreateCouponData $data): Coupon
    {
        return $this->container->make(CreateCouponAction::class)->execute($data, quiet: true);
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
     * Whether a coupon with the given code exists.
     */
    public function exists(string $code): bool
    {
        return $this->newQuery()->whereCode($code)->exists();
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
     * One coupon, by code or model: `check()`, `preview()`, `redeem()` and `revoke()` it.
     */
    public function code(Coupon|string $coupon): CouponCode
    {
        return new CouponCode($this, $coupon);
    }

    /**
     * Why the coupon would be refused right now — the first failing reason in redemption
     * order — or null when it is redeemable. Never throws; an unknown code reports NotFound.
     * Pass a price to also check the currency lock and minimum spend, and a redeemer to
     * check the per-redeemer cap.
     */
    public function check(Coupon|string $coupon, ?Money $price = null, ?Model $redeemer = null): ?RedemptionFailureReason
    {
        return $this->container->make(CheckCouponAction::class)->execute($coupon, $price, $redeemer);
    }

    /**
     * The discount the coupon would take off the price, without redeeming it — zero on a
     * currency mismatch, so it is safe to render.
     *
     * @throws CouponNotFound when the code resolves to no coupon.
     */
    public function preview(Coupon|string $coupon, Money $price): Money
    {
        return $this->resolve($coupon)->previewDiscount($price);
    }

    /**
     * Redeem a coupon by code or model: validates, increments usage atomically,
     * records the redemption, and fires CouponRedeemed.
     */
    public function redeem(Coupon|string $coupon, Money $price, ?Model $redeemer = null): RedemptionResult
    {
        return $this->container->make(RedeemCouponAction::class)->execute(
            new RedeemCouponData(coupon: $coupon, price: $price, redeemer: $redeemer),
        );
    }

    /**
     * Revoke a coupon by expiring it immediately. This is reversible — the row is
     * not deleted, only its expires_at is set to now — and fires CouponRevoked.
     *
     * @throws CouponNotFound when no coupon matches the code.
     */
    public function revoke(Coupon|string $coupon): Coupon
    {
        return $this->container->make(RevokeCouponAction::class)->execute($this->resolve($coupon));
    }

    /**
     * Revoke every live coupon — or only the live one holding `$code` — and return how many
     * were revoked. CouponRevoked fires once per coupon. Backs `coupons:expire`.
     */
    public function expireAll(?string $code = null): int
    {
        return $this->container->make(ExpireCouponsAction::class)->execute($code);
    }

    /**
     * Delete coupons that expired more than `$days` days ago — soft by default, permanently
     * with `$force` — and return how many were pruned. Backs `coupons:prune`.
     *
     * @throws InvalidArgumentException when `$days` is negative.
     */
    public function prune(int $days = 30, bool $force = false): int
    {
        return $this->container->make(PruneCouponsAction::class)->execute($days, $force);
    }

    /**
     * @throws CouponNotFound when no coupon matches the code.
     */
    private function resolve(Coupon|string $coupon): Coupon
    {
        return $coupon instanceof Coupon ? $coupon : $this->findOrFail($coupon);
    }

    /**
     * @return Builder<Coupon>
     */
    private function newQuery(): Builder
    {
        return CouponModel::class()::query();
    }
}
