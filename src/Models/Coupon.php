<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use RoundlyConsulting\Coupons\CouponManager;
use RoundlyConsulting\Coupons\Database\Factories\CouponFactory;
use RoundlyConsulting\Coupons\DataTransferObjects\RedemptionResult;
use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Coupons\Exceptions\InvalidCouponDefinition;
use RoundlyConsulting\Coupons\Support\CodeFormat;
use RoundlyConsulting\Coupons\Support\CouponConfig;
use RoundlyConsulting\Money\Casts\AsCurrency;
use RoundlyConsulting\Money\Casts\AsMoney;
use RoundlyConsulting\Money\Currency;
use RoundlyConsulting\Money\Discounts\Discount;
use RoundlyConsulting\Money\Exceptions\CurrencyMismatch;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\PackageToolkit\Support\Config;

/**
 * @property int $id
 * @property DiscountType $type
 * @property string $code
 * @property int $value
 * @property int $usage
 * @property int $max_usage
 * @property int $max_usage_per_redeemer
 * @property ?Currency $currency
 * @property ?Money $minimum_spend
 * @property ?Money $max_discount
 * @property ?CarbonInterface $activated_at
 * @property ?CarbonInterface $expires_at
 * @property ?Collection<string, mixed> $meta
 * @property ?CarbonInterface $created_at
 * @property ?CarbonInterface $updated_at
 * @property ?CarbonInterface $deleted_at
 *
 * Not final: `coupons.model` documents swapping in a host subclass of this
 * model, which `final` would make impossible.
 */
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    /**
     * The database-generated column that keeps codes unique among live coupons — never
     * written by Eloquent, so it stays out of arrays and JSON too.
     *
     * @var list<string>
     */
    protected $hidden = ['undeleted_code'];

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
            'currency' => AsCurrency::class,
            'minimum_spend' => AsMoney::currencyColumn('currency'),
            'max_discount' => AsMoney::currencyColumn('currency'),
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
            'meta' => 'collection',
        ];
    }

    /**
     * A copy without the database-generated `undeleted_code`, which an insert must never set.
     *
     * @param  array<int, string>|null  $except
     */
    public function replicate(?array $except = null): static
    {
        return parent::replicate([...($except ?? []), 'undeleted_code']);
    }

    /**
     * Coupons whose activation window has started.
     *
     * @param  Builder<Coupon>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNotNull('activated_at')
            ->where('activated_at', '<=', CarbonImmutable::now());
    }

    /**
     * Coupons past their expiry.
     *
     * @param  Builder<Coupon>  $query
     */
    public function scopeExpired(Builder $query): void
    {
        $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', CarbonImmutable::now());
    }

    /**
     * Coupons that have reached a positive global usage cap.
     *
     * @param  Builder<Coupon>  $query
     */
    public function scopeExhausted(Builder $query): void
    {
        $query->where('max_usage', '>', 0)
            ->whereColumn('usage', '>=', 'max_usage');
    }

    /**
     * Coupons that can be redeemed right now: active, not expired, not exhausted.
     *
     * @param  Builder<Coupon>  $query
     */
    public function scopeRedeemable(Builder $query): void
    {
        $now = CarbonImmutable::now();

        $query->whereNotNull('activated_at')
            ->where('activated_at', '<=', $now)
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->where(function (Builder $query): void {
                $query->where('max_usage', '<=', 0)->orWhereColumn('usage', '<', 'max_usage');
            });
    }

    /**
     * The coupon holding the code, matched case-insensitively and whitespace-trimmed (the
     * form every code is stored in). A blank code matches nothing.
     *
     * @param  Builder<Coupon>  $query
     */
    public function scopeWhereCode(Builder $query, string $code): void
    {
        $code = CodeFormat::normalize($code);

        if ($code === '') {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where($query->qualifyColumn('code'), $code);
    }

    public function getRouteKeyName(): string
    {
        return CouponConfig::routeKey();
    }

    /**
     * Bind `{coupon}` by code the way every lookup matches it: case-insensitively.
     *
     * @param  Model|BuilderContract|Relation<Model, Model, mixed>  $query
     * @param  mixed  $value
     * @param  string|null  $field
     * @return BuilderContract
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $field ??= $this->getRouteKeyName();

        return $query->where($field, $field === 'code' && is_string($value) ? CodeFormat::normalize($value) : $value);
    }

    /**
     * Every code is stored in its canonical form, however it was written — through the
     * action, a factory, or straight onto the model.
     *
     * @return Attribute<string, string|null>
     */
    protected function code(): Attribute
    {
        return Attribute::make(
            set: static fn (?string $value): ?string => $value === null ? null : CodeFormat::normalize($value),
        );
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

    /**
     * The key is named explicitly: Eloquent would derive it from the class name, and a host
     * subclass swapped in through `coupons.model` (say `ShopCoupon`) would then look for a
     * `shop_coupon_id` column that does not exist.
     *
     * @return HasMany<CouponRedemption, $this>
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class, 'coupon_id');
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
     * means unlimited per redeemer. Never enforced while `coupons.redeemer.track` is off:
     * no rows are written then, and counting the ones from before the switch would refuse
     * only the redeemers who happen to have them (remainingUsageFor() answers null too).
     */
    public function isAtMaximumUsageFor(Model $redeemer): bool
    {
        return $this->max_usage_per_redeemer > 0
            && Config::boolean('coupons.redeemer.track', true)
            && $this->usageBy($redeemer) >= $this->max_usage_per_redeemer;
    }

    public function hasBeenUsedAtLeastOnce(): bool
    {
        return $this->usage > 0;
    }

    /**
     * Expired at or before now — the `expired()` scope's `<=`, so a coupon revoked this very
     * instant is already expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at?->lessThanOrEqualTo(CarbonImmutable::now()) ?? false;
    }

    /**
     * Activated at or before now — the `active()` scope's `<=`, so the stored instant itself
     * counts.
     */
    public function isActive(): bool
    {
        return $this->activated_at?->lessThanOrEqualTo(CarbonImmutable::now()) ?? false;
    }

    /**
     * The coupon as a money Discount, for a basket in `$for` (the coupon's own currency wins
     * when it is locked). Hosts compose it with other discounts in a money DiscountStack.
     * Route by its target: a free-shipping discount applies to shipping, never to goods.
     *
     * @throws InvalidCouponDefinition when a fixed coupon has no currency (e.g. a row written
     *                                 around CreateCouponAction).
     */
    public function discount(Currency $for): Discount
    {
        if ($this->type === DiscountType::Fixed && $this->currency === null) {
            throw InvalidCouponDefinition::fixedWithoutCurrency((string) $this->code);
        }

        $discount = $this->type->toDiscount((int) $this->value, $this->currency ?? $for, $this->max_discount);

        // An unsaved coupon may carry no code yet.
        $label = (string) $this->code;

        return $label === '' ? $discount : $discount->labelled($label);
    }

    /**
     * Apply the coupon to a price, returning the new total. Never negative: the discount
     * never exceeds the price. A free-shipping coupon leaves the goods price unchanged.
     *
     * @throws CurrencyMismatch when the coupon is currency-locked to another currency.
     */
    public function apply(Money $price): Money
    {
        return $price->subtract($this->discountFor($price));
    }

    /**
     * The amount this coupon saves off the given price: between zero and the price, never
     * above the optional cap. Free shipping removes nothing from the goods price.
     *
     * @throws CurrencyMismatch when the coupon is currency-locked to another currency.
     * @throws InvalidCouponDefinition when a fixed coupon has no currency.
     */
    public function discountFor(Money $price): Money
    {
        $this->assertCurrency($price);

        if ($this->type === DiscountType::FreeShipping) {
            return Money::zero($price->currency());
        }

        return $this->discount($price->currency())->amountFor($price);
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
            || $this->currency->equals($price->currency());
    }

    /**
     * A coupon with no minimum spend always qualifies; otherwise the price must
     * meet or exceed the threshold. The minimum spend shares the coupon's currency lock,
     * so check appliesToCurrency() first (the guard does).
     *
     * @throws CurrencyMismatch when the price is in another currency than the minimum spend.
     */
    public function meetsMinimumSpend(Money $price): bool
    {
        return $this->minimum_spend === null
            || $price->isGreaterThanOrEqualTo($this->minimum_spend);
    }

    /**
     * How many redemptions remain before the global cap is reached, or null when
     * the coupon has no positive cap (unlimited).
     */
    public function remainingUsage(): ?int
    {
        if ($this->max_usage <= 0) {
            return null;
        }

        return max(0, $this->max_usage - $this->usage);
    }

    /**
     * How many redemptions the given redeemer has left, or null when per-redeemer
     * caps cannot be enforced (tracking disabled) or are unlimited (cap <= 0).
     */
    public function remainingUsageFor(Model $redeemer): ?int
    {
        if (! Config::boolean('coupons.redeemer.track', true) || $this->max_usage_per_redeemer <= 0) {
            return null;
        }

        return max(0, $this->max_usage_per_redeemer - $this->usageBy($redeemer));
    }

    /**
     * The share of the global cap already consumed (0..100), or null when there
     * is no positive cap (unlimited).
     */
    public function usagePercentage(): ?float
    {
        if ($this->max_usage <= 0) {
            return null;
        }

        return round(min(100.0, max(0.0, $this->usage / $this->max_usage * 100)), 2);
    }

    /**
     * Whether this coupon could be redeemed right now by the given redeemer — the same
     * checks, in the same order, as redemption (`Coupons::check()` names the failing one).
     * Pass a price to also check currency and minimum spend; omit it to skip those checks.
     * Never throws.
     */
    public function isRedeemableBy(?Model $redeemer = null, ?Money $price = null): bool
    {
        return app(CouponManager::class)->check($this, $price, $redeemer) === null;
    }

    /**
     * The discount this coupon would apply to the given price, the display-safe
     * sibling of discountFor(): returns zero on a currency mismatch instead of
     * throwing, so it is safe to call from views.
     */
    public function previewDiscount(Money $price): Money
    {
        if (! $this->appliesToCurrency($price)) {
            return Money::zero($price->currency());
        }

        return $this->discountFor($price);
    }

    /**
     * Redeem this coupon for the given redeemer and price: validates, increments
     * usage atomically, records a redemption row when tracking is on, and fires
     * CouponRedeemed. Goes through the manager, so `Coupons::fake()` records it.
     */
    public function redeemBy(?Model $redeemer, Money $price): RedemptionResult
    {
        return app(CouponManager::class)->redeem($this, $price, $redeemer);
    }

    /**
     * @throws CurrencyMismatch when the coupon is currency-locked to another currency.
     */
    private function assertCurrency(Money $price): void
    {
        if ($this->currency !== null && ! $this->currency->equals($price->currency())) {
            throw CurrencyMismatch::between($this->currency, $price->currency());
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
