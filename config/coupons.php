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
    | type; set this to match them. Any unrecognized value falls back to "bigint".
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
    | The ISO 4217 currency code assumed when one is not supplied explicitly.
    | A coupon may also lock itself to a single currency via its own column.
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
    | skip writing redemption rows; the global usage cap still applies.
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
    | Controls auto-generated coupon codes: how many characters and which
    | alphabet they are drawn from.
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
