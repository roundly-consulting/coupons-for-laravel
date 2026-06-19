<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use RoundlyConsulting\Coupons\Actions\RedeemCouponAction;
use RoundlyConsulting\Coupons\Database\Factories\CouponFactory;
use RoundlyConsulting\Coupons\DataTransferObjects\RedeemCouponData;
use RoundlyConsulting\Coupons\DataTransferObjects\RedemptionResult;
use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Coupons\Exceptions\InvalidMoney;
use RoundlyConsulting\Coupons\ValueObjects\Money;

/**
 * @property int $id
 * @property DiscountType $type
 * @property string $code
 * @property int $value
 * @property int $usage
 * @property int $max_usage
 * @property int $max_usage_per_redeemer
 * @property ?string $currency
 * @property ?int $minimum_spend
 * @property ?int $max_discount
 * @property ?CarbonInterface $activated_at
 * @property ?CarbonInterface $expires_at
 * @property ?Collection<string, mixed> $meta
 * @property ?CarbonInterface $created_at
 * @property ?CarbonInterface $updated_at
 * @property ?CarbonInterface $deleted_at
 */
final class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    protected static function newFactory(): CouponFactory
    {
        return CouponFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => DiscountType::class,
            'value' => 'integer',
            'usage' => 'integer',
            'max_usage' => 'integer',
            'max_usage_per_redeemer' => 'integer',
            'minimum_spend' => 'integer',
            'max_discount' => 'integer',
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
            'meta' => 'collection',
        ];
    }

    public function canBeApplied(): bool
    {
        if ($this->isAtMaximumUsage()) {
            return false;
        }

        return $this->isActive() && ! $this->isExpired();
    }

    /**
     * A coupon is at maximum usage once a positive cap is set and reached.
     * A zero cap means unlimited usage.
     */
    public function isAtMaximumUsage(): bool
    {
        return $this->max_usage > 0 && $this->usage >= $this->max_usage;
    }

    /** @return HasMany<CouponRedemption, $this> */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    /**
     * How many times the given redeemer has used this coupon.
     */
    public function usageBy(Model $redeemer): int
    {
        return $this->redemptions()
            ->where('redeemer_type', $redeemer->getMorphClass())
            ->where('redeemer_id', $redeemer->getKey())
            ->count();
    }

    /**
     * Whether the given redeemer has reached their per-redeemer cap. A zero cap
     * means unlimited per redeemer.
     */
    public function isAtMaximumUsageFor(Model $redeemer): bool
    {
        return $this->max_usage_per_redeemer > 0
            && $this->usageBy($redeemer) >= $this->max_usage_per_redeemer;
    }

    public function hasBeenUsedAtLeastOnce(): bool
    {
        return $this->usage > 0;
    }

    public function isExpired(): bool
    {
        return $this->expires_at?->isPast() ?? false;
    }

    public function isActive(): bool
    {
        return $this->activated_at?->isPast() ?? false;
    }

    /**
     * Apply the coupon to a price, returning the new total.
     *
     * @throws InvalidMoney when the coupon is currency-locked to another currency.
     */
    public function apply(Money $price): Money
    {
        $this->assertCurrency($price);

        return $this->type->apply($price, $this->value, $this->max_discount);
    }

    /**
     * The amount this coupon saves off the given price (in minor units),
     * respecting the optional discount cap.
     *
     * @throws InvalidMoney when the coupon is currency-locked to another currency.
     */
    public function discountFor(Money $price): Money
    {
        $this->assertCurrency($price);

        return $this->type->discount($price, $this->value, $this->max_discount);
    }

    public function isFreeShipping(): bool
    {
        return $this->type === DiscountType::FreeShipping;
    }

    /**
     * Whether the host should zero its own shipping total for this coupon.
     */
    public function appliesToShipping(): bool
    {
        return $this->isFreeShipping();
    }

    /**
     * A coupon with no currency lock applies to any currency; otherwise the
     * price's currency must match.
     */
    public function appliesToCurrency(Money $price): bool
    {
        return $this->currency === null
            || $this->currency === $price->getCurrency();
    }

    /**
     * A coupon with no minimum spend always qualifies; otherwise the price must
     * meet or exceed the threshold (compared in minor units).
     */
    public function meetsMinimumSpend(Money $price): bool
    {
        return $this->minimum_spend === null
            || $price->getAmount() >= $this->minimum_spend;
    }

    /**
     * Redeem this coupon for the given redeemer and price: validates, increments
     * usage atomically, records a redemption row when tracking is on, and fires
     * CouponRedeemed.
     */
    public function redeemBy(?Model $redeemer, Money $price): RedemptionResult
    {
        return app(RedeemCouponAction::class)->execute(
            new RedeemCouponData(coupon: $this, price: $price, redeemer: $redeemer),
        );
    }

    /**
     * @throws InvalidMoney when the coupon is currency-locked to another currency.
     */
    private function assertCurrency(Money $price): void
    {
        if (! $this->appliesToCurrency($price)) {
            throw InvalidMoney::currencyMismatch((string) $this->currency, $price->getCurrency());
        }
    }

    public function setMaxUsageTo(int $maxUsage): self
    {
        $this->max_usage = $maxUsage;

        return $this;
    }

    public function activate(?CarbonInterface $at = null): self
    {
        $this->activated_at = $at ?? CarbonImmutable::now();

        return $this;
    }

    public function expire(?CarbonInterface $at = null): self
    {
        $this->expires_at = $at ?? CarbonImmutable::now();

        return $this;
    }
}
