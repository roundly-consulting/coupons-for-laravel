<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Testing;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Assert;
use RoundlyConsulting\Coupons\CouponManager;
use RoundlyConsulting\Coupons\DataTransferObjects\CreateCouponData;
use RoundlyConsulting\Coupons\DataTransferObjects\RedemptionResult;
use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\ValueObjects\Money;

/**
 * A test double for CouponManager that records calls instead of writing to the
 * database, so consumers can assert coupon creation and redemption without a
 * real persistence layer.
 */
final class FakeCouponManager extends CouponManager
{
    /** @var list<Coupon> */
    private array $created = [];

    /** @var list<RedemptionResult> */
    private array $redeemed = [];

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
        $coupon = new Coupon([
            'type' => $data->type,
            'value' => $data->value,
            'code' => $data->code ?? 'FAKE-'.(count($this->created) + 1),
            'max_usage' => $data->maxUsage,
        ]);

        $this->created[] = $coupon;

        return $coupon;
    }

    public function createQuietly(CreateCouponData $data): Coupon
    {
        return $this->create($data);
    }

    /**
     * A DB-free empty builder, so callers can treat the fake like the real
     * manager without touching a database.
     *
     * @return Builder<Coupon>
     */
    public function redeemable(): Builder
    {
        return Coupon::query()->whereRaw('1 = 0');
    }

    public function exists(string $code): bool
    {
        foreach ($this->created as $coupon) {
            if ($coupon->code === $code) {
                return true;
            }
        }

        return false;
    }

    public function revoke(string $code): Coupon
    {
        $coupon = new Coupon(['code' => $code]);
        $coupon->expire();

        return $coupon;
    }

    public function redeem(Coupon|string $coupon, Money $price, ?Model $redeemer = null): RedemptionResult
    {
        $model = $coupon instanceof Coupon ? $coupon : new Coupon(['code' => $coupon]);

        $result = new RedemptionResult(
            coupon: $model,
            discount: Money::zero($price->getCurrency()),
            total: $price,
            redeemer: $redeemer,
            freeShipping: $model->isFreeShipping(),
        );

        $this->redeemed[] = $result;

        return $result;
    }

    public function assertRedeemed(?callable $callback = null): void
    {
        if ($callback === null) {
            Assert::assertNotEmpty($this->redeemed, 'Expected a coupon to be redeemed, but none were.');

            return;
        }

        $matched = array_filter($this->redeemed, static fn ($result): bool => $callback($result) === true);

        Assert::assertNotEmpty($matched, 'Expected a redemption matching the callback, but none did.');
    }

    public function assertNothingRedeemed(): void
    {
        Assert::assertEmpty($this->redeemed, 'Expected no redemptions, but some were recorded.');
    }

    public function assertCreated(?callable $callback = null): void
    {
        if ($callback === null) {
            Assert::assertNotEmpty($this->created, 'Expected a coupon to be created, but none were.');

            return;
        }

        $matched = array_filter($this->created, static fn (Coupon $coupon): bool => $callback($coupon) === true);

        Assert::assertNotEmpty($matched, 'Expected a created coupon matching the callback, but none did.');
    }
}
