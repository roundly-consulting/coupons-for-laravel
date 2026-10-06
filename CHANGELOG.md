# Changelog

All notable changes to `coupons-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

### Changed

- `Coupons::fake()`: `generate()`, `create()` and `createQuietly()` now throw
  `CouponCodeTaken` for an explicit code already held by a coupon created on the fake or by a
  live database row, like the real manager (a soft-deleted row's code stays free). Before, the
  duplicate was accepted and shadowed the real row in `find()`. Tests that create the same
  explicit code twice under the fake need distinct codes.

### Fixed

- `coupons:expire` refuses a blank `--code` (`--code=`, whitespace, a bare `--code`) with an
  error and a failure exit instead of expiring every live coupon. Only an absent `--code`
  means "all".
- `CouponRedemption` always uses the coupon model's connection (`coupons.model`'s, else the
  default). `HasCoupons::couponRedemptions()` and `hasRedeemed()` read the rows where
  redemption wrote them, whether the coupon model or the redeemer model is on a non-default
  connection.
- `Coupon::isActive()` and `isExpired()` count the stored instant itself (`<= now`), like the
  `active()`, `expired()` and `redeemable()` scopes. At that exact instant a coupon
  `redeemable()` lists is no longer refused as expired, and a coupon revoked that instant is
  already refused.
- With `coupons.redeemer.track` off, the per-redeemer cap is no longer enforced from rows
  written while tracking was on: `check()`, `redeem()` and `Coupon::isAtMaximumUsageFor()` now
  agree with `remainingUsageFor()`, which already reported the cap as not enforced.
- `Coupons::fake()` builds and queries the `coupons.model` class: coupons from `generate()` /
  `create()`, an unknown code's coupon and `redeemable()` are your subclass, not the packaged
  `Coupon`.
- `Coupons::fake()` consumes usage like production: every recorded successful redemption
  counts against the coupon's `max_usage` and, while `coupons.redeemer.track` is on, the
  redeemer's `max_usage_per_redeemer`. A second redemption of a single-use coupon is now
  recorded as a failure (`AtMaxUsage` / `AlreadyRedeemed`), and `check()` reports the caps of
  an undated in-memory coupon instead of stopping at the waived activation check.

## 1.0.1 - 2026-10-04

### Changed

- Maintenance: `composer.json` `homepage` and `support.docs` now point to the documentation site.

### Fixed

- Slovak (`sk`) translations now ship alongside English for every language file.

## 1.0.0 - 2026-10-03

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
- Coupon codes are case-insensitive: stored trimmed and upper-cased, and matched the same way by
  every lookup (`find()`, `check()`, `redeem()`, the rule, `whereCode()`, route binding). The
  `code.charset` alphabet is upper-cased too and may not repeat a symbol case-insensitively.
- A code is unique among coupons that are not soft-deleted (a unique index on the generated
  `undeleted_code` column), so a pruned coupon's code can be issued again. A taken explicit code
  throws `CouponCodeTaken` instead of a raw database error.
- **Breaking:** `HasCoupons::redeemCoupon()` requires the cart total.
- `CouponCreated`, `CouponRedeemed`, `CouponExhausted` and `CouponRevoked` implement
  `ShouldDispatchAfterCommit`.

### Fixed

- `Coupons::fake()` recorded nothing for redemptions made through `$coupon->redeemBy()` or
  `$user->redeemCoupon()`. Those calls went straight to the real action.
- The fake counted a refused redemption as redeemed, so `assertRedeemed()` passed for a coupon
  the real manager would have rejected.
- `coupons:prune --force` never purged coupons that an earlier soft prune had trashed.
- `coupons:prune --days=<negative>` deleted coupons that were still live. It is now refused.
- Code lookup and uniqueness depended on the database collation (`summer` missed `SUMMER` on
  SQLite and PostgreSQL but matched on MySQL), and a pasted ` SUMMER ` matched nowhere.
- A code soft-deleted by `coupons:prune` could never be used again.
- The fake reported a seeded, never-activated coupon as redeemable, and its `redeem()` always
  returned a zero discount and the full price.
- `coupons.redeemer.track` only counted the exact `true`, so `COUPONS_TRACK_REDEEMERS=1` turned
  tracking — and per-redeemer caps — off.
- `redeemCoupon()` without a cart total threw `CurrencyMismatch` for a fixed coupon outside
  `default_currency`, and otherwise burned a use at a zero discount.
- Redemption locked the coupon row inside a transaction on the default connection, not the
  coupon model's, so a `coupons.model` on another connection held no lock.
- A blank explicit code was accepted; `check('')` and `redeem('')` disagreed about it.
- For a soft-deleted `Coupon` instance, `check()` and `isRedeemableBy()` said redeemable while
  `redeem()` threw `CouponNotFound`.
- `coupons:prune --days=thirty` read the window as 0 and pruned every expired coupon.
- Redemption events fired inside the transaction, so a host rollback still announced them.
