<?php

declare(strict_types=1);

use RoundlyConsulting\Coupons\Models\Coupon;

return [

    /*
    |--------------------------------------------------------------------------
    | Coupon Model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model used to represent a coupon. Swap this for your own
    | subclass of the package's Coupon model if you need to extend behaviour.
    |
    */

    'model' => Coupon::class,

    /*
    |--------------------------------------------------------------------------
    | Key Type
    |--------------------------------------------------------------------------
    |
    | The key type used for the polymorphic redeemer column on coupon redemptions.
    | Use "uuid" or "ulid" when the models that redeem coupons use UUID/ULID primary
    | keys, otherwise leave it as "bigint". Your redeemer models must share one key
    | type; set this to match them. Anything else throws an
    | InvalidConfigurationException naming the key.
    |
    | Supported: "bigint", "uuid", "ulid"
    |
    */

    'key_type' => env('COUPONS_KEY_TYPE', 'bigint'),

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | The ISO 4217 currency code the shipped CouponFactory locks fixed and capped
    | coupons to when a state names none. Redemption never assumes a currency:
    | it always takes the cart total's, and a coupon may lock itself to one.
    |
    */

    'default_currency' => env('COUPONS_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Redeemer Tracking
    |--------------------------------------------------------------------------
    |
    | When enabled, every redemption that carries a redeemer is recorded in the
    | coupon_redemptions table, which powers per-redeemer usage caps. Disable to
    | skip writing redemption rows; the global usage cap still applies. Env-style
    | values work: "1"/"true"/"on"/"yes" and "0"/"false"/"off"/"no". Anything else
    | throws an InvalidConfigurationException naming the key.
    |
    */

    'redeemer' => [
        'track' => env('COUPONS_TRACK_REDEEMERS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Generated Code Format
    |--------------------------------------------------------------------------
    |
    | Controls auto-generated coupon codes: how many characters (4-64) and
    | which alphabet they are drawn from. Codes are case-insensitive and stored
    | upper-cased, so the alphabet is upper-cased too; it needs at least 2
    | distinct symbols (case-insensitively) with no whitespace or control
    | characters. Explicit codes are never checked against it.
    |
    */

    'code' => [
        'length' => env('COUPONS_CODE_LENGTH', 6),
        'charset' => env('COUPONS_CODE_CHARSET', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Key
    |--------------------------------------------------------------------------
    |
    | The column used for route-model binding of {coupon}. Defaults to the
    | human-friendly code; set to 'id' to bind by primary key instead.
    |
    */

    'route_key' => env('COUPONS_ROUTE_KEY', 'code'),

];
