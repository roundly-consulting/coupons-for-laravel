<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\DataTransferObjects;

use RoundlyConsulting\Coupons\Enums\DiscountType;

final readonly class CreateCouponData
{
    public function __construct(
        public DiscountType $type,
        public int $value,
        public ?string $code = null,
        public int $maxUsage = 0,
    ) {}
}
