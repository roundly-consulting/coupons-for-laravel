<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Support;

use RoundlyConsulting\Coupons\Exceptions\InvalidCouponConfiguration;
use RoundlyConsulting\PackageToolkit\Support\Config;

/**
 * Strict reads of the `coupons.*` string settings. A key that is not set — absent, null or
 * blank (`''` or whitespace, a host's `KEY=`) — takes its default; a present value that is not
 * a string throws {@see InvalidCouponConfiguration} naming the key — a typo is never swapped
 * for the default.
 *
 * @internal
 */
final class CouponConfig
{
    /** The column `{coupon}` route parameters bind by. */
    public static function routeKey(): string
    {
        return self::string('coupons.route_key', 'code');
    }

    /** The currency the shipped factory locks fixed and capped coupons to. */
    public static function defaultCurrency(): string
    {
        return self::string('coupons.default_currency', 'USD');
    }

    /** Not set: absent, null or a blank string (`''` or whitespace — a host's `KEY=`). */
    public static function blank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    private static function string(string $key, string $default): string
    {
        return self::blank(config($key))
            ? $default
            : Config::using(InvalidCouponConfiguration::class)->requireString($key);
    }
}
