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
use RoundlyConsulting\Coupons\Enums\RedemptionFailureReason;
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\Support\RedemptionGuard;
use RoundlyConsulting\Money\Currency;
use RoundlyConsulting\Money\Money;

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

    /** @var list<array{code: string, reason: RedemptionFailureReason}> */
    private array $failed = [];

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
        $coupon = new Coupon([
            'type' => $data->type,
            'value' => $data->value,
            'code' => $data->code ?? 'FAKE-'.(count($this->created) + 1),
            'max_usage' => $data->maxUsage,
            'currency' => $data->lockedCurrency(),
            'minimum_spend' => $data->minimumSpend,
            'max_discount' => $data->maxDiscount,
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

        $reason = $this->failureFor($model, $price, $redeemer);

        if ($reason !== null) {
            $this->failed[] = ['code' => (string) $model->code, 'reason' => $reason];
        }

        $result = new RedemptionResult(
            coupon: $model,
            discount: Money::zero($price->currency()),
            total: $price,
            redeemer: $redeemer,
            freeShipping: $model->isFreeShipping(),
        );

        $this->redeemed[] = $result;

        return $result;
    }

    /**
     * Assert a coupon was redeemed — any redemption, one with the given code, and/or
     * one the callback accepts (`assertRedeemed(callback: fn ($result) => …)`).
     */
    public function assertRedeemed(?string $code = null, ?callable $callback = null): void
    {
        if ($code === null && $callback === null) {
            Assert::assertNotEmpty($this->redeemed, 'Expected a coupon to be redeemed, but none were.');

            return;
        }

        $matched = array_filter($this->redeemed, static function (RedemptionResult $result) use ($code, $callback): bool {
            if ($code !== null && $result->coupon->code !== $code) {
                return false;
            }

            return $callback === null || $callback($result) === true;
        });

        Assert::assertNotEmpty($matched, 'Expected a matching redemption, but none were recorded.');
    }

    /**
     * The guard reason the fake should record, or null. The fake never throws —
     * it is a recording double — and it deliberately ignores the "inactive"
     * reason for a coupon that simply has no activation date (a freshly faked
     * coupon), so a plain redeem of such a coupon still counts as a success.
     */
    private function failureFor(Coupon $coupon, Money $price, ?Model $redeemer): ?RedemptionFailureReason
    {
        $reason = app(RedemptionGuard::class)->firstFailure($coupon, $price, $redeemer);

        if ($reason === RedemptionFailureReason::Expired
            && $coupon->activated_at === null
            && $coupon->expires_at === null) {
            return null;
        }

        return $reason;
    }

    public function assertNotRedeemed(string $code): void
    {
        $matched = array_filter($this->redeemed, static fn (RedemptionResult $result): bool => $result->coupon->code === $code);

        Assert::assertEmpty($matched, "Expected coupon \"{$code}\" not to be redeemed, but it was.");
    }

    public function assertRedemptionFailed(string $code, ?string $reason = null): void
    {
        $matched = array_filter($this->failed, static function (array $entry) use ($code, $reason): bool {
            if ($entry['code'] !== $code) {
                return false;
            }

            return $reason === null || $entry['reason']->value === $reason;
        });

        Assert::assertNotEmpty($matched, "Expected a failed redemption for coupon \"{$code}\", but none were recorded.");
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
