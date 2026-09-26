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
use RoundlyConsulting\Coupons\Support\CouponModel;
use RoundlyConsulting\Money\Casts\AsMoney;
use RoundlyConsulting\Money\Money;

/**
 * @property int $id
 * @property int $coupon_id
 * @property ?string $redeemer_type
 * @property int|string|null $redeemer_id
 * @property Money $amount_discounted
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
            // No cast on redeemer_id: `coupons.key_type` may make it a uuid/ulid string.
            'amount_discounted' => AsMoney::currencyColumn('currency'),
        ];
    }

    /**
     * The redeemed coupon, hydrated as the `coupons.model` class.
     *
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(CouponModel::class(), 'coupon_id');
    }

    /** @return MorphTo<Model, $this> */
    public function redeemer(): MorphTo
    {
        return $this->morphTo();
    }

    public function discount(): Money
    {
        return $this->amount_discounted;
    }
}
