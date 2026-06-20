<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Coupons\Concerns\HasCoupons;

/**
 * A minimal redeemer model used by the test suite to exercise the per-redeemer
 * tracking morph relationship and the HasCoupons trait.
 *
 * @property int $id
 * @property ?string $name
 */
final class Customer extends Model
{
    use HasCoupons;

    protected $guarded = [];

    public $timestamps = false;
}
