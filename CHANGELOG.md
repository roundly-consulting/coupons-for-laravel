# Changelog

All notable changes to `coupons-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

Initial public release.

### Added

- Discount coupons of three types (`DiscountType`): fixed amount, percentage with an optional cap,
  and free shipping.
- Every amount is a money-for-laravel `Money` — exponent-correct for any currency, arbitrary
  precision, no floats — with an optional currency lock and minimum spend.
- Activation and expiry windows, a global usage limit and a per-redeemer limit ("one per customer").
- A `Coupons` facade to `generate()`, `find()`, `redeem()` and `revoke()` coupons, plus
  `CreateCouponAction` with `CreateCouponData::fixed()` / `percentage()` / `freeShipping()`.
- Atomic redemption that can never exceed `max_usage` under concurrent requests, returning a
  `RedemptionResult` with the discount, the new total and the free-shipping flag.
- Precise, catchable exceptions for every rejection (`CouponExpired`, `CouponAtMaxUsage`,
  `MinimumSpendNotMet`, …) and non-throwing display helpers such as `isRedeemableBy()`,
  `previewDiscount()` and `remainingUsage()`.
- A `HasCoupons` redeemer trait (`redeemCoupon()`, `hasRedeemed()`, redemption history) and a
  translatable `Redeemable` validation rule for coupon-code fields.
- Query scopes (`active()`, `expired()`, `exhausted()`, `redeemable()`) and route-model binding by
  coupon code.
- Events for creation, redemption, failed redemption, exhaustion and revocation.
- Artisan commands `coupons:expire` and `coupons:prune`.
- `Coupons::fake()` with assertions such as `assertRedeemed()` and `assertRedemptionFailed()`.
