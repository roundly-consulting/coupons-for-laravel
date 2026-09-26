<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Coupons\Concerns\HasCoupons;

/**
 * A redeemer keyed by a UUID, for hosts that set `coupons.key_type` to `uuid`.
 *
 * @property string $id
 * @property ?string $name
 */
final class UuidCustomer extends Model
{
    use HasCoupons;
    use HasUuids;

    protected $table = 'uuid_customers';

    protected $guarded = [];

    public $timestamps = false;
}
