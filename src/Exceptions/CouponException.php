<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Exceptions;

use RuntimeException;

/**
 * Base exception for every error thrown by the coupons package, so consumers can
 * catch the whole package surface with a single type.
 */
class CouponException extends RuntimeException {}
