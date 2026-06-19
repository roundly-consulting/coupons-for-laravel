# Changelog

All notable changes to `coupons-for-laravel` will be documented in this file.

## Unreleased

Pre-1.0: the package is unreleased, so the `create_coupons_table` migration was edited in
place rather than evolved with a follow-up migration. Re-run migrations on a fresh database.

### Added

- Free-shipping and capped-percentage discount types; optional per-coupon currency lock and
  minimum spend.
- Atomic redemption flow: `RedeemCouponAction`, `RedemptionResult` DTO, `CouponRedeemed`
  event, and a typed exception tree (`CouponNotFound`, `CouponNotRedeemable`, `CouponExpired`,
  `CouponAtMaxUsage`, `CouponAlreadyRedeemed`, `MinimumSpendNotMet`, `CurrencyMismatch`).
  Usage is incremented inside a transaction with `lockForUpdate`, so concurrent redemptions
  can never exceed `max_usage`.
- Per-redeemer tracking: `coupon_redemptions` table, `CouponRedemption` model, a
  per-redeemer usage cap, and `Coupon::usageBy()` / `isAtMaximumUsageFor()`. Opt out via
  `coupons.redeemer.track`.
- Query scopes `active`, `expired`, `redeemable`, `exhausted`, `whereCode`, and route-model
  binding by `code` (configurable via `coupons.route_key`).
- `Coupons` facade + `CouponManager` (`generate`, `create`, `find`, `findOrFail`, `redeem`,
  `fake`) and a `FakeCouponManager` testing double.
- `coupons:expire` and `coupons:prune` console commands.
- `Money::zero()` and `Money::fromMajor()` convenience constructors; hardened `format()`
  fallback for invalid locales.
- Config keys: `default_currency`, `redeemer.track`, `code.length`, `code.charset`,
  `route_key`.

### Changed

- `create_coupons_table` gained `currency`, `minimum_spend`, `max_discount`, and
  `max_usage_per_redeemer` columns (in place; pre-1.0).
