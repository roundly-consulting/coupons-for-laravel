<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A minimal redeemer model used by the test suite to exercise the per-redeemer
 * tracking morph relationship.
 *
 * @property int $id
 * @property ?string $name
 */
final class Customer extends Model
{
    protected $guarded = [];

    public $timestamps = false;
}
