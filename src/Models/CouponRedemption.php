<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Coupons\Database\Factories\CouponRedemptionFactory;
use RoundlyConsulting\Coupons\ValueObjects\Money;

/**
 * @property int $id
 * @property int $coupon_id
 * @property ?string $redeemer_type
 * @property ?int $redeemer_id
 * @property int $amount_discounted
 * @property string $currency
 * @property ?CarbonInterface $created_at
 * @property ?CarbonInterface $updated_at
 * @property ?CarbonInterface $deleted_at
 * @property Coupon $coupon
 * @property ?Model $redeemer
 */
final class CouponRedemption extends Model
{
    /** @use HasFactory<CouponRedemptionFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    protected static function newFactory(): CouponRedemptionFactory
    {
        return CouponRedemptionFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'coupon_id' => 'integer',
            'redeemer_id' => 'integer',
            'amount_discounted' => 'integer',
        ];
    }

    /** @return BelongsTo<Coupon, $this> */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /** @return MorphTo<Model, $this> */
    public function redeemer(): MorphTo
    {
        return $this->morphTo();
    }

    public function discount(): Money
    {
        return new Money($this->amount_discounted, $this->currency);
    }
}
