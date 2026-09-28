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
- A `Coupons` facade over an injectable `CouponManager`, with one action class per operation:
  `generate()` / `create()` / `createQuietly()`, `find()` / `findOrFail()` / `exists()` /
  `redeemable()`, `check()`, `preview()`, `redeem()`, `revoke()` (a code or a `Coupon`),
  `expireAll()` and `prune()`. `CreateCouponData::fixed()` / `percentage()` / `freeShipping()`
  build the input.
- `Coupons::code('SUMMER')` handle: `check($cart, $user)` returns the first
  `RedemptionFailureReason` or `null` without throwing ("why won't this code work?"),
  `preview($cart)`, `redeem($cart, $user)` and `revoke()`.
- `CheckCouponAction`, `ExpireCouponsAction` and `PruneCouponsAction`. `coupons:expire` and
  `coupons:prune` now call `Coupons::expireAll()` and `Coupons::prune()`.
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
- `Coupons::fake()` returns a `CouponsFake`, a `CouponManager` subtype, so injected managers get
  it too. It records every mutation, including `$coupon->redeemBy()` and `$user->redeemCoupon()`,
  and asserts `assertCreated()` / `assertNothingCreated()`, `assertRedeemed()` /
  `assertNotRedeemed()` / `assertNothingRedeemed()`, `assertRedemptionFailed()` (with a reason
  enum or string), `assertRevoked()` / `assertExpiredAll()` / `assertNothingRevoked()` and
  `assertPruned()` / `assertNothingPruned()`.

### Changed

- The model (`redeemBy()`, `isRedeemableBy()`), the `HasCoupons` trait (`redeemCoupon()`) and
  the `Redeemable` rule go through `CouponManager` instead of calling actions directly.
- `Coupons::revoke()` accepts a `Coupon` as well as a code.
- `CouponManager` takes the container in its constructor and resolves each action on call.
- The fake is now `Testing\CouponsFake` (was `FakeCouponManager`) and is created by the
  facade's own `Coupons::fake()`. `CouponManager::fake()` is removed.

### Fixed

- `Coupons::fake()` recorded nothing for redemptions made through `$coupon->redeemBy()` or
  `$user->redeemCoupon()`. Those calls went straight to the real action.
- The fake counted a refused redemption as redeemed, so `assertRedeemed()` passed for a coupon
  the real manager would have rejected.
- `coupons:prune --force` never purged coupons that an earlier soft prune had trashed.
- `coupons:prune --days=<negative>` deleted coupons that were still live. It is now refused.
