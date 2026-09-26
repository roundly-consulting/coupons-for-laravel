# Changelog

All notable changes to `coupons-for-laravel` will be documented in this file.

## Unreleased

Pre-1.0: the package is unreleased, so the `create_coupons_table` migration was edited in
place rather than evolved with a follow-up migration. Re-run migrations on a fresh database.

### Fixed

- `coupons.code.length` / `coupons.code.charset` now drive generated codes (they were only
  shown by `about`; codes were always 6 uppercase letters/digits). Both are validated on use
  (length 4–64; an alphabet of 2+ distinct symbols, no whitespace/control characters) and a
  bad value throws `InvalidCouponConfiguration`. The alphabet is used as given — no more
  uppercasing. Generation now also skips codes held by soft-deleted coupons and gives up after
  10 collisions instead of looping forever.
- `coupons:expire` now revokes each coupon through the new `RevokeCouponAction` (shared with
  `Coupons::revoke()`), so `CouponRevoked` fires once per expired coupon; it was a silent bulk
  update.
- `coupons:expire` and `coupons:prune` now resolve the model from `coupons.model` (they queried
  the packaged `Coupon`, so a host subclass's model events and overrides never ran).
- With a host subclass in `coupons.model`, redeeming for a redeemer no longer fails: the
  redemptions relation derived its foreign key from the class name (`custom_coupon_id`), so
  recording a redemption and every per-redeemer cap check hit a missing column. A redemption's
  `coupon` now hydrates the configured model too.
- `CouponRedemption::$redeemer_id` is no longer cast to an integer, which turned a uuid/ulid
  redeemer key (`coupons.key_type`) into a number and broke `$redemption->redeemer`.

### Changed — money-for-laravel

- Every amount is `RoundlyConsulting\Money\Money` (money-for-laravel, now required, with
  `ext-bcmath`); the package's own `ValueObjects\Money` and `InvalidMoney` are removed.
- `DiscountType::apply()` / `discount()` are replaced by `toDiscount()` returning a money
  `Discount`; `Coupon::discount(Currency)` exposes it, labelled with the code.
- Percentage coupon `value` is in **basis points** (2500 = 25 %, 1250 = 12.5 %).
- A coupon must be locked to a currency when it is `Fixed` or has a minimum spend / cap
  (`InvalidCouponDefinition`); `minimum_spend`, `max_discount` and `amount_discounted` are
  `Money` (`decimal(38,0)` columns); `value` is a `bigint`.
- A fixed discount is clamped to the price (`Fixed 5000` on `1000` discounts and records `1000`).
- `CreateCouponData` gains `currency`, `minimumSpend`, `maxDiscount` and the `fixed()` /
  `percentage()` / `freeShipping()` constructors; `Coupons::generate()` takes a `currency`.
- `MinimumSpendNotMet::forCode()` takes the minimum spend as `Money` ("50.00 EUR").

### Added — DX, trait, rule, and richer events (1.2, additive)

All additive — no existing public signature changed. No new config keys and no new migration
(revoke reuses `expires_at`).

- Non-throwing coupon read helpers: `remainingUsage()`, `remainingUsageFor()`,
  `usagePercentage()`, `isRedeemableBy()`, and `previewDiscount()`.
- `Concerns\HasCoupons` trait for redeemer models: `redeemCoupon()`, `couponRedemptions()`,
  `hasRedeemed()` — all delegating to the same redemption action.
- `Rules\Redeemable` validation rule with per-reason, translatable messages, sharing the same
  eligibility checks as redemption. Ships `resources/lang/en/messages.php` (publish tag
  `coupons-translations`).
- New events: `CouponRedemptionFailed` (every rejected attempt), `CouponExhausted` (once, on
  the cap-reaching redemption), and `CouponRevoked`.
- `Money::isPositive()`, `percentageOf()`, and a penny-accurate `allocate()`; `DiscountType`
  `label()`, `description()`, and `requiresValue()`.
- Manager/facade additions: `redeemable()`, `exists()`, `revoke()` (reversible, expires now),
  and `createQuietly()`.
- Testing fake: `assertRedeemed($code)`, `assertNotRedeemed()`, `assertRedemptionFailed()`,
  plus `redeemable()`/`exists()`/`revoke()`/`createQuietly()` parity. `assertRedeemed()` now
  takes a code first; the legacy callback-only form still works via a shim.
- Shared `Support\RedemptionGuard` evaluator and `Enums\RedemptionFailureReason`, so the
  action, the model helpers, the rule, and the fake never duplicate eligibility logic.

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
