<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;

/**
 * Records every pessimistic row lock the package takes, together with the
 * transaction depth at that moment.
 *
 * @extends Builder<LockRecordingCoupon>
 */
class LockRecordingBuilder extends Builder
{
    /** @return $this */
    public function lockForUpdate(): self
    {
        LockRecordingCoupon::$lockedAtDepth[] = $this->getQuery()->getConnection()->transactionLevel();

        $this->getQuery()->lockForUpdate();

        return $this;
    }
}
