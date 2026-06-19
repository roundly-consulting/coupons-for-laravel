<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use RoundlyConsulting\Coupons\Database\Factories\CouponFactory;
use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Coupons\ValueObjects\Money;

/**
 * @property int $id
 * @property DiscountType $type
 * @property string $code
 * @property int $value
 * @property int $usage
 * @property int $max_usage
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

    public function apply(Money $price): Money
    {
        return $this->type->apply($price, $this->value);
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
