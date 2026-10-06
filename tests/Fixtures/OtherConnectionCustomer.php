<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Coupons\Concerns\HasCoupons;

/**
 * A host redeemer model that lives on a non-default connection while coupons stay on the
 * default one — the mirror of {@see OtherConnectionCoupon}.
 *
 * @property int $id
 * @property ?string $name
 */
final class OtherConnectionCustomer extends Model
{
    use HasCoupons;

    protected $connection = 'users_other';

    protected $table = 'customers';

    protected $guarded = [];

    public $timestamps = false;
}
