<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Exceptions;

/**
 * Base type for every reason a coupon could not be redeemed, so consumers can
 * catch the whole "not redeemable" surface with a single type or each cause
 * individually.
 */
class CouponNotRedeemable extends CouponException {}
