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
use RoundlyConsulting\Coupons\Support\CodeFormat;
use RoundlyConsulting\Coupons\Support\RedemptionGuard;
use RoundlyConsulting\Money\Money;

/**
 * The recording double `Coupons::fake()` installs. It writes nothing — no row, no event —
 * and records every mutation, including calls made through `Coupons::code()`, the Coupon
 * model (`redeemBy()`) and the HasCoupons trait (`redeemCoupon()`), which all go through
 * the manager.
 *
 * Reads look at the coupons created on the fake first, then at the database. An unknown
 * code is treated as a fresh, unrestricted coupon, so `check()` answers null and `redeem()`
 * records a success — check-then-redeem flows work without seeding. A database row gets the
 * real checks. Seed a coupon (on the fake or as a row) to exercise a refusal.
 */
final class CouponsFake extends CouponManager
{
    /** @var list<Coupon> */
    private array $created = [];

    /** @var list<RedemptionResult> */
    private array $redeemed = [];

    /** @var list<array{code: string, reason: RedemptionFailureReason}> */
    private array $failed = [];

    /** @var list<Coupon> */
    private array $revoked = [];

    /** @var list<?string> */
    private array $expiredAll = [];

    /** @var list<array{days: int, force: bool}> */
    private array $pruned = [];

    /**
     * Builds the coupon in memory (nothing is saved), refusing the same invalid definitions
     * the real create does.
     */
    public function create(CreateCouponData $data): Coupon
    {
        $code = $data->code === null ? 'FAKE-'.(count($this->created) + 1) : CodeFormat::normalize($data->code);

        $data->assertValid($code);

        $coupon = new Coupon([
            'type' => $data->type,
            'value' => $data->value,
            'code' => $code,
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

    public function find(string $code): ?Coupon
    {
        $code = CodeFormat::normalize($code);

        foreach (array_reverse($this->created) as $coupon) {
            if ($coupon->code === $code) {
                return $coupon;
            }
        }

        return parent::find($code);
    }

    public function exists(string $code): bool
    {
        return $this->find($code) !== null;
    }

    /**
     * An empty builder that never matches: the coupons created on the fake have no rows.
     *
     * @return Builder<Coupon>
     */
    public function redeemable(): Builder
    {
        return Coupon::query()->whereRaw('1 = 0');
    }

    public function check(Coupon|string $coupon, ?Money $price = null, ?Model $redeemer = null): ?RedemptionFailureReason
    {
        return $this->failureFor($this->resolveOrMake($coupon), $price, $redeemer);
    }

    public function preview(Coupon|string $coupon, Money $price): Money
    {
        return $this->resolveOrMake($coupon)->previewDiscount($price);
    }

    /**
     * Records a success or — when the coupon would be refused — a failure, and never
     * throws. A success carries the discount and total the real redemption would compute;
     * a refusal carries a zero discount and the full price.
     */
    public function redeem(Coupon|string $coupon, Money $price, ?Model $redeemer = null): RedemptionResult
    {
        $model = $this->resolveOrMake($coupon);

        $reason = $this->failureFor($model, $price, $redeemer);

        $discount = $reason === null ? $model->discountFor($price) : Money::zero($price->currency());

        $result = new RedemptionResult(
            coupon: $model,
            discount: $discount,
            total: $price->subtract($discount),
            redeemer: $redeemer,
            freeShipping: $model->isFreeShipping(),
        );

        if ($reason !== null) {
            $this->failed[] = ['code' => $model->code, 'reason' => $reason];

            return $result;
        }

        $this->redeemed[] = $result;

        return $result;
    }

    /**
     * Expires the coupon in memory (nothing is saved) and records it.
     */
    public function revoke(Coupon|string $coupon): Coupon
    {
        $model = $this->resolveOrMake($coupon)->expire();

        $this->revoked[] = $model;

        return $model;
    }

    /**
     * Records the sweep and expires the matching live coupons created on the fake.
     */
    public function expireAll(?string $code = null): int
    {
        $this->expiredAll[] = $code;

        $code = $code === null ? null : CodeFormat::normalize($code);
        $count = 0;

        foreach ($this->created as $coupon) {
            if (($code === null || $coupon->code === $code) && ! $coupon->isExpired()) {
                $coupon->expire();
                $count++;
            }
        }

        return $count;
    }

    /**
     * Records the prune and deletes nothing.
     */
    public function prune(int $days = 30, bool $force = false): int
    {
        $this->pruned[] = ['days' => $days, 'force' => $force];

        return 0;
    }

    /**
     * Assert a coupon was created — any, or one the callback accepts.
     */
    public function assertCreated(?callable $callback = null): void
    {
        if ($callback === null) {
            Assert::assertNotEmpty($this->created, 'Expected a coupon to be created, but none were.');

            return;
        }

        $matched = array_filter($this->created, static fn (Coupon $coupon): bool => $callback($coupon) === true);

        Assert::assertNotEmpty($matched, 'Expected a created coupon matching the callback, but none did.');
    }

    public function assertNothingCreated(): void
    {
        Assert::assertEmpty($this->created, 'Expected no coupons to be created, but some were.');
    }

    /**
     * Assert a coupon was redeemed — any redemption, one with the given code, and/or
     * one the callback accepts (`assertRedeemed(callback: fn ($result) => …)`). A refused
     * redemption is recorded as a failure, never as redeemed.
     */
    public function assertRedeemed(?string $code = null, ?callable $callback = null): void
    {
        if ($code === null && $callback === null) {
            Assert::assertNotEmpty($this->redeemed, 'Expected a coupon to be redeemed, but none were.');

            return;
        }

        $code = $code === null ? null : CodeFormat::normalize($code);

        $matched = array_filter($this->redeemed, static function (RedemptionResult $result) use ($code, $callback): bool {
            if ($code !== null && $result->coupon->code !== $code) {
                return false;
            }

            return $callback === null || $callback($result) === true;
        });

        Assert::assertNotEmpty($matched, 'Expected a matching redemption, but none were recorded.');
    }

    public function assertNotRedeemed(string $code): void
    {
        $code = CodeFormat::normalize($code);

        $matched = array_filter($this->redeemed, static fn (RedemptionResult $result): bool => $result->coupon->code === $code);

        Assert::assertEmpty($matched, "Expected coupon \"{$code}\" not to be redeemed, but it was.");
    }

    public function assertNothingRedeemed(): void
    {
        Assert::assertEmpty($this->redeemed, 'Expected no redemptions, but some were recorded.');
    }

    /**
     * Assert a redemption of the code was refused — for any reason, or the given one.
     */
    public function assertRedemptionFailed(string $code, RedemptionFailureReason|string|null $reason = null): void
    {
        $code = CodeFormat::normalize($code);
        $expected = $reason instanceof RedemptionFailureReason ? $reason->value : $reason;

        $matched = array_filter($this->failed, static function (array $entry) use ($code, $expected): bool {
            if ($entry['code'] !== $code) {
                return false;
            }

            return $expected === null || $entry['reason']->value === $expected;
        });

        Assert::assertNotEmpty($matched, "Expected a failed redemption for coupon \"{$code}\", but none were recorded.");
    }

    /**
     * Assert a coupon was revoked — any, or the one holding the code.
     */
    public function assertRevoked(?string $code = null): void
    {
        $code = $code === null ? null : CodeFormat::normalize($code);

        $matched = array_filter($this->revoked, static fn (Coupon $coupon): bool => $code === null || $coupon->code === $code);

        Assert::assertNotEmpty($matched, $code === null
            ? 'Expected a coupon to be revoked, but none were.'
            : "Expected coupon \"{$code}\" to be revoked, but it was not.");
    }

    /**
     * Assert nothing was revoked — neither one coupon (`revoke()`) nor in bulk (`expireAll()`).
     */
    public function assertNothingRevoked(): void
    {
        Assert::assertEmpty($this->revoked, 'Expected no coupons to be revoked, but some were.');
        Assert::assertEmpty($this->expiredAll, 'Expected no coupons to be revoked, but expireAll() was called.');
    }

    public function assertExpiredAll(): void
    {
        Assert::assertNotEmpty($this->expiredAll, 'Expected expireAll() to be called, but it was not.');
    }

    /**
     * Assert coupons were pruned — any call, or one with the given window and/or mode.
     */
    public function assertPruned(?int $days = null, ?bool $force = null): void
    {
        $matched = array_filter($this->pruned, static fn (array $call): bool => ($days === null || $call['days'] === $days)
            && ($force === null || $call['force'] === $force));

        Assert::assertNotEmpty($matched, 'Expected a matching prune, but none were recorded.');
    }

    public function assertNothingPruned(): void
    {
        Assert::assertEmpty($this->pruned, 'Expected no prune, but one was recorded.');
    }

    /**
     * A known coupon — the model given, one created on the fake, or a row — or a fresh,
     * unrestricted, zero-value coupon holding the code.
     */
    private function resolveOrMake(Coupon|string $coupon): Coupon
    {
        if ($coupon instanceof Coupon) {
            return $coupon;
        }

        return $this->find($coupon) ?? new Coupon([
            'code' => $coupon,
            'type' => DiscountType::Percentage,
            'value' => 0,
            'usage' => 0,
            'max_usage' => 0,
            'max_usage_per_redeemer' => 0,
        ]);
    }

    /**
     * The guard reason the fake reports, or null. It waives the "inactive" reason only for an
     * in-memory coupon — created on the fake, or unknown — that has neither an activation
     * nor an expiry date, so a plain redeem of one still counts as a success. A database row
     * gets the real check: one nobody activated is refused, exactly as in production.
     */
    private function failureFor(Coupon $coupon, ?Money $price, ?Model $redeemer): ?RedemptionFailureReason
    {
        $reason = $this->container->make(RedemptionGuard::class)->firstFailure($coupon, $price, $redeemer);

        if ($reason === RedemptionFailureReason::Expired
            && ! $coupon->exists
            && $coupon->activated_at === null
            && $coupon->expires_at === null) {
            return null;
        }

        return $reason;
    }
}
