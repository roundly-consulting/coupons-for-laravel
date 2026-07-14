<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use RoundlyConsulting\Coupons\Models\Coupon;

/**
 * A host-style subclass of the packaged model — the `coupons.model` seam — whose
 * query builder records the transaction depth at which the package takes a row
 * lock. It exists so the redemption path's mutual exclusion can be asserted on
 * any driver: SQLite compiles `lockForUpdate()` to an empty string, so the lock
 * is invisible in the emitted SQL.
 */
class LockRecordingCoupon extends Coupon
{
    /**
     * The transaction depth at each `lockForUpdate()` the package applied.
     *
     * @var list<int>
     */
    public static array $lockedAtDepth = [];

    protected $table = 'coupons';

    /**
     * @param  QueryBuilder  $query
     * @return Builder<*>
     */
    public function newEloquentBuilder($query): Builder
    {
        return new LockRecordingBuilder($query);
    }
}
