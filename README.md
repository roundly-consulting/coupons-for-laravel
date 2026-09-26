<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/coupons-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=coupons-for-laravel">
    <img src="art/hero.png" alt="Coupons for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

# Coupons for Laravel

Create, manage, redeem, and apply discount coupons in Laravel. Coupons support fixed-amount,
percentage (optionally capped), and free-shipping discounts, an optional currency lock and
minimum spend, activation and expiry windows, global and per-redeemer usage limits, a
validated **atomic** redemption flow, query scopes, route-model binding, console commands, a
fluent `Coupons` facade — with every amount a
[money-for-laravel](https://github.com/roundly-consulting/money-for-laravel) `Money`
(exponent-correct for JPY/BHD, arbitrary precision, no floats) and no third-party runtime
dependencies.

## Requirements

- PHP 8.4 with `ext-bcmath`
- Laravel 12 or 13

## Installation

```bash
composer require roundly-consulting/coupons-for-laravel
```

Publish and run the migrations. The package does **not** auto-load them — publishing copies
both migrations (`coupons`, then `coupon_redemptions`) into your `database/migrations`, where
you own them, so a bare `php artisan migrate` before publishing creates nothing:

```bash
php artisan vendor:publish --tag="coupons-migrations"
php artisan migrate
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="coupons-config"
```

Optionally publish the translation strings (the validation-rule and discount-type messages)
to customise or translate them:

```bash
php artisan vendor:publish --tag="coupons-translations"
```

## Configuration

The published `config/coupons.php` exposes:

```php
return [
    'model' => \RoundlyConsulting\Coupons\Models\Coupon::class,
    'default_currency' => env('COUPONS_CURRENCY', 'USD'),
    'redeemer' => [
        'track' => env('COUPONS_TRACK_REDEEMERS', true),
    ],
    'code' => [
        'length' => env('COUPONS_CODE_LENGTH', 6),
        'charset' => env('COUPONS_CODE_CHARSET', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'),
    ],
    'route_key' => env('COUPONS_ROUTE_KEY', 'code'),
];
```

| Key | Type | Default | Env | Purpose |
|---|---|---|---|---|
| `model` | `class-string` | `RoundlyConsulting\Coupons\Models\Coupon` | — | Coupon model. Point it at your own subclass to extend behaviour. |
| `default_currency` | `string` | `USD` | `COUPONS_CURRENCY` | ISO 4217 code assumed when one is not given explicitly. |
| `redeemer.track` | `bool` | `true` | `COUPONS_TRACK_REDEEMERS` | Record a `coupon_redemptions` row per redeemer (powers per-redeemer caps). |
| `code.length` | `int` | `6` | `COUPONS_CODE_LENGTH` | Length of auto-generated codes. |
| `code.charset` | `string` | `A–Z0–9` | `COUPONS_CODE_CHARSET` | Alphabet for auto-generated codes. |
| `route_key` | `string` | `code` | `COUPONS_ROUTE_KEY` | Column used for route-model binding of `{coupon}`. Set to `id` to bind by primary key. |

The package ships sensible defaults and works with zero configuration.

### Money, units & the currency lock

Every amount is a `RoundlyConsulting\Money\Money` from money-for-laravel — minor units as an
exact integer string, in a registered currency with its real exponent (`500 JPY` is ¥500,
`1500 BHD` is 1.500 BD). A coupon's `value` means:

| Type | `value` | Example |
|---|---|---|
| `Fixed` | minor units of the coupon's `currency` | `500` + `EUR` = €5.00 off |
| `Percentage` | **basis points** (0..10 000) | `2500` = 25 %, `1250` = 12.5 % |
| `FreeShipping` | ignored (`0`) | — |

A coupon **must** be locked to a currency when it is `Fixed` or has a `minimum_spend` /
`max_discount` — both are `Money` in the coupon's `currency` (they share that column).
`CreateCouponAction` enforces it (`InvalidCouponDefinition`), and a `Fixed` row written around
the action without a currency is refused when it is used. Percentage and free-shipping coupons
without a minimum spend or cap may stay unlocked and apply to any currency.

## Usage

### Discount types

The `DiscountType` enum names how a coupon's value applies; the math is money-for-laravel's
`Discount`:

```php
use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Money\Currency;
use RoundlyConsulting\Money\Money;

$eur = Currency::of('EUR');

DiscountType::Fixed->toDiscount(250, $eur)->applyTo(Money::ofMinor(1000, 'EUR'));        // 7.50 EUR
DiscountType::Percentage->toDiscount(1250, $eur)->amountFor(Money::ofMinor(999, 'EUR'));  // 1.25 EUR (12.5 %, half away from zero)
DiscountType::Percentage->toDiscount(5000, $eur, cap: Money::ofMinor(300, 'EUR'));       // 50 % capped at 3.00 EUR

// Free shipping is a discount on the SHIPPING target — route by $discount->target(); never
// apply it to the price of the goods.
DiscountType::FreeShipping->toDiscount(0, $eur)->target(); // DiscountTarget::Shipping

// Translatable labels and descriptions for admin UIs:
DiscountType::Percentage->label();         // "Percentage"
DiscountType::Percentage->description();   // "Subtracts a percentage of the price."
DiscountType::FreeShipping->requiresValue(); // false (Fixed/Percentage are true)
```

### The `Coupons` facade

The `Coupons` facade is the discoverable entry point — generate, find, and redeem in one call.

```php
use RoundlyConsulting\Coupons\Facades\Coupons;
use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Money\Money;

$coupon = Coupons::generate(DiscountType::Percentage, value: 2000, code: 'SAVE20', maxUsage: 100); // 20 %
$coupon = Coupons::generate(DiscountType::Fixed, value: 500, code: 'FIVE', currency: 'EUR');       // €5.00

$coupon = Coupons::find('SAVE20');        // ?Coupon
$coupon = Coupons::findOrFail('SAVE20');  // throws CouponNotFound

$result = Coupons::redeem('SAVE20', Money::ofMinor(5000, 'EUR'), redeemer: $user);

Coupons::exists('SAVE20');                // bool
Coupons::redeemable()->get();             // query builder of redeemable coupons
Coupons::revoke('SAVE20');                // expire it now (reversible), fires CouponRevoked
Coupons::createQuietly($data);            // create without dispatching CouponCreated
```

`revoke()` is a reversible kill-switch: it sets `expires_at` to now (the row is **not**
deleted) and fires `CouponRevoked`. Re-activate later with `$coupon->expire($future)->save()`
or by clearing `expires_at`. `createQuietly()` is for seeders and fixtures that don't want
`CouponCreated` listeners to fire.

### Creating coupons

Use the facade, or `CreateCouponAction` with a `CreateCouponData` DTO. A unique code is
generated when you don't supply one, and a `CouponCreated` event is dispatched.

```php
use RoundlyConsulting\Coupons\Actions\CreateCouponAction;
use RoundlyConsulting\Coupons\DataTransferObjects\CreateCouponData;
use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Money\Currency;
use RoundlyConsulting\Money\Money;

$create = app(CreateCouponAction::class);

// Named constructors pick the unit and the currency lock for you:
$create->execute(CreateCouponData::fixed(Money::ofMinor(500, 'EUR'), code: 'FIVE'));             // €5.00, locked to EUR
$create->execute(CreateCouponData::percentage('12.5', code: 'EIGHTH',
    maxDiscount: Money::ofMinor(1500, 'EUR')));                                                   // 12.5 %, capped, locked to EUR
$create->execute(CreateCouponData::freeShipping(code: 'SHIP', minimumSpend: Money::ofMinor(3000, 'EUR')));

// Or the full DTO:
$create->execute(new CreateCouponData(
    type: DiscountType::Percentage,
    value: 2000,                         // basis points: 20 % off
    currency: Currency::of('EUR'),       // optional lock (required for Fixed / min spend / cap)
    code: 'SAVE20',                      // optional — auto-generated when omitted
    maxUsage: 100,                       // optional — 0 means unlimited
    minimumSpend: Money::ofMinor(5000, 'EUR'),
    maxDiscount: Money::ofMinor(1000, 'EUR'),
));
```

`CreateCouponAction` throws `InvalidCouponDefinition` for a `Fixed` coupon without a currency,
a negative fixed value, or a percentage outside 0..10 000 basis points; a minimum spend or cap
in another currency than the lock throws money's `CurrencyMismatch`. `CreateCouponData::fixed()`
throws money's `AmountOverflow` for an amount beyond int64 minor units (`value` is a `bigint`).

### Redeeming a coupon

Redemption validates eligibility, increments usage **atomically** (a DB transaction with
`lockForUpdate`, so concurrent redemptions can never exceed `max_usage`), records a
per-redeemer row when tracking is on, and dispatches `CouponRedeemed`. It returns a
`RedemptionResult { Coupon $coupon, Money $discount, Money $total, ?Model $redeemer, bool $freeShipping }`.

```php
use RoundlyConsulting\Coupons\Facades\Coupons;
use RoundlyConsulting\Money\Money;

$result = Coupons::redeem('SAVE20', Money::ofMinor(5000, 'EUR'), redeemer: $user);

$result->discount;      // Money saved off the price — never more than the price
$result->total;         // new total after the discount
$result->freeShipping;  // true for a free-shipping coupon — zero your own shipping line

// Fluent equivalent on the model (redeemer may be null for guest checkout):
$result = $coupon->redeemBy($user, Money::ofMinor(5000, 'EUR'));
```

On failure it throws a precise, catchable exception — all extend `CouponNotRedeemable`
(except `CouponNotFound`), which extends `CouponException`:

```php
use RoundlyConsulting\Coupons\Exceptions\{CouponNotFound, CouponExpired, CouponAtMaxUsage,
    CouponAlreadyRedeemed, MinimumSpendNotMet, CurrencyMismatch};

try {
    $result = Coupons::redeem($code, $price, redeemer: $user);
} catch (CouponNotFound) {          // unknown code
} catch (CouponExpired) {           // not active / past expiry
} catch (CouponAtMaxUsage) {        // global cap reached
} catch (CouponAlreadyRedeemed) {   // per-redeemer cap reached
} catch (MinimumSpendNotMet) {      // price below the coupon's minimum
} catch (CurrencyMismatch) {        // coupon locked to another currency
}
```

### Currency lock, minimum spend & per-redeemer caps

```php
$coupon->currency;                 // ?Currency — null = any currency, or the lock
$coupon->minimum_spend;            // ?Money   — null = no minimum
$coupon->max_discount;             // ?Money   — null = uncapped
$coupon->max_usage_per_redeemer;   // 0 = unlimited per redeemer

$coupon->discountFor($price);           // Money — 0 ≤ discount ≤ price, capped; throws money's
                                        //         CurrencyMismatch on a locked mismatch
$coupon->apply($price);                 // Money — the price minus the discount (never negative)
$coupon->discount($currency);           // money Discount labelled with the code, for a DiscountStack

$coupon->appliesToCurrency($price);     // bool
$coupon->meetsMinimumSpend($price);     // bool
$coupon->usageBy($user);                // int
$coupon->isAtMaximumUsageFor($user);    // bool — "one per customer" when cap is 1
```

### Display helpers (non-throwing)

For views and APIs, read coupon state without manual math or try/catch. Each is safe to call
anywhere:

```php
$coupon->remainingUsage();            // ?int  — null when unlimited (max_usage <= 0)
$coupon->remainingUsageFor($user);    // ?int  — null when untracked or per-redeemer unlimited
$coupon->usagePercentage();           // ?float (0..100) — null when unlimited
$coupon->isRedeemableBy($user, $cart); // bool — no exceptions; pass a price to also check
                                       //        currency + minimum spend (both optional)
$coupon->previewDiscount($cart);      // Money — never throws; zero on a currency mismatch
```

`isRedeemableBy()` and the validation rule below both run the **same** eligibility checks as
`redeem()`, so they never drift out of sync.

### Redeemer trait

Add `HasCoupons` to your redeemer model (typically `User`) for a first-class redeemer API:

```php
use RoundlyConsulting\Coupons\Concerns\HasCoupons;

class User extends Authenticatable
{
    use HasCoupons;
}
```

```php
$result  = $user->redeemCoupon('SAVE20', Money::ofMinor(5000, 'EUR')); // RedemptionResult
$history = $user->couponRedemptions;                              // morphMany history
$used    = $user->hasRedeemed('SAVE20');                          // bool (tracked only)
```

With no cart total, `redeemCoupon()` uses a zero amount in `coupons.default_currency`, so a
coupon with a minimum spend correctly rejects an empty basket. `hasRedeemed()` reflects only
tracked redemptions (`coupons.redeemer.track = true`, the default).

### Validation rule

`Rules\Redeemable` validates a coupon-code form field and reports a precise, translatable
message for the first failing reason — no need to catch six exceptions:

```php
use RoundlyConsulting\Coupons\Rules\Redeemable;

$request->validate([
    'code' => ['required', new Redeemable(cartTotal: $cart, redeemer: $request->user())],
]);
```

Both `cartTotal` and `redeemer` are optional: omit the cart total to skip currency and
minimum-spend checks, omit the redeemer to skip per-redeemer caps. Messages live in
`resources/lang/en/messages.php` and are publishable via the `coupons-translations` tag.

### Query scopes & route-model binding

```php
use RoundlyConsulting\Coupons\Models\Coupon;

Coupon::query()->active()->get();
Coupon::query()->expired()->get();
Coupon::query()->exhausted()->get();
Coupon::query()->redeemable()->get();    // active AND not expired AND not at max usage
Coupon::query()->whereCode('SAVE20')->first();
```

`{coupon}` route parameters bind by `code` by default (set `coupons.route_key` to `id` to
bind by primary key).

### Console commands

```bash
# Immediately expire coupons (admin kill-switch). --code limits it to one coupon.
php artisan coupons:expire
php artisan coupons:expire --code=SAVE20

# Prune coupons expired more than N days ago (soft delete; --force hard-deletes).
php artisan coupons:prune --days=30
php artisan coupons:prune --days=30 --force
```

### Testing with the fake

`Coupons::fake()` swaps the container binding for a recorder that performs no database
writes, with assertion helpers:

```php
use RoundlyConsulting\Coupons\Facades\Coupons;
use RoundlyConsulting\Money\Money;

$fake = Coupons::fake();

Coupons::redeem('SAVE20', Money::ofMinor(5000, 'EUR'), redeemer: $user);

$fake->assertRedeemed('SAVE20');                                   // by code
$fake->assertRedeemed('SAVE20', fn ($result) => /* ... */ true);   // by code + callback
$fake->assertNotRedeemed('OTHER');
$fake->assertRedemptionFailed('OLD', 'expired');                   // reason optional
$fake->assertNothingRedeemed();
$fake->assertCreated();
```

> **Note:** `assertRedeemed()` now takes the coupon code as its first argument. The legacy
> callback-only form (`assertRedeemed(fn ($result) => ...)`) still works for backward
> compatibility.

### Working with a coupon

```php
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Money\Money;

$coupon = Coupon::query()->where('code', 'SAVE20')->firstOrFail();

$coupon->activate();          // activated_at = now (pass a CarbonInterface to schedule)
$coupon->expire($expiresAt);  // expires_at
$coupon->setMaxUsageTo(50);
$coupon->save();

$coupon->isActive();             // bool
$coupon->isExpired();            // bool
$coupon->isAtMaximumUsage();     // bool (a 0 cap is unlimited)
$coupon->hasBeenUsedAtLeastOnce();
$coupon->canBeApplied();         // active, not expired, not at max usage

// Apply the coupon to a price.
$discounted = $coupon->apply(Money::ofMinor(5000, 'EUR')); // €40.00 for 20% off
```

### Listening for events

A free-shipping coupon's `discountFor()` is zero and `apply()` returns the price unchanged:
free shipping is a flag (`$result->freeShipping`) the host applies to its own shipping line.

The package dispatches these events the host app can listen to:

| Event | When | Payload |
|---|---|---|
| `CouponCreated` | a coupon is created (not via `createQuietly`) | `Coupon $coupon` |
| `CouponRedeemed` | a redemption succeeds | `Coupon $coupon`, `RedemptionResult $result` |
| `CouponRedemptionFailed` | a redemption attempt is rejected | `string $code`, `RedemptionFailureReason $reason`, `?Model $redeemer` |
| `CouponExhausted` | a redemption consumes the final available use (fires **once**) | `Coupon $coupon` |
| `CouponRevoked` | a coupon is revoked via `Coupons::revoke()` | `Coupon $coupon` |

```php
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Coupons\Events\CouponCreated;
use RoundlyConsulting\Coupons\Events\CouponRedeemed;
use RoundlyConsulting\Coupons\Events\CouponRedemptionFailed;
use RoundlyConsulting\Coupons\Events\CouponExhausted;

Event::listen(function (CouponRedeemed $event): void {
    logger()->info('Coupon redeemed', [
        'code' => $event->coupon->code,
        'discount' => (string) $event->result->discount, // "12.50 EUR"
    ]);
});

Event::listen(function (CouponRedemptionFailed $event): void {
    logger()->warning('Coupon rejected', [
        'code' => $event->code,
        'reason' => $event->reason->value, // e.g. "expired", "at_max_usage"
    ]);
});

Event::listen(function (CouponExhausted $event): void {
    logger()->info('Coupon exhausted', ['code' => $event->coupon->code]);
});
```

`CouponRedemptionFailed` fires from the redemption attempt (the single mutating path), once
per rejected attempt, and the matching exception is still thrown. `CouponExhausted` fires
exactly once — on the redemption that brings `usage` up to `max_usage` — never for unlimited
coupons and never again on a later rejected attempt.

### Per-redeemer tracking migration

Publish the migrations (`vendor:publish --tag="coupons-migrations"`) and run `php artisan
migrate` to add the `coupon_redemptions` table that powers per-redeemer caps. The redeemer is
a nullable morph, so guest (redeemer-less) redemptions are supported; per-redeemer caps apply
only when a redeemer is supplied.

## Integrates with

This package hard-requires two lower-tier roundly packages (wired automatically):

- **[money-for-laravel](https://github.com/roundly-consulting/money-for-laravel)** — every
  amount is its `Money`; a coupon's value becomes its `Discount` (fixed, percentage with
  fractional basis points, free shipping, cap); `minimum_spend` / `max_discount` /
  `amount_discounted` use its `AsMoney` casts and `$table->money()` columns (`decimal(38,0)`);
  `Coupon::discount()` hands hosts a `Discount` to compose in a `DiscountStack`.

- **[package-toolkit-for-laravel](https://github.com/roundly-consulting/package-toolkit-for-laravel)**
  — the service-provider builder (config, migrations, translations, commands and publish tags)
  and the validated `coupons.model` resolver, which checks that a swapped-in model really is a
  coupon model before the package queries through it.

The package reports its configuration to Laravel's `about` command. The generated-code alphabet
is reported by size only — printing it would hand a brute-forcer the exact key space coupon codes
are drawn from:

```bash
php artisan about --only=coupons
```

## Testing

```bash
composer test
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for what has changed recently.

## Contributing

Contributions are welcome. Please open an issue or pull request on the repository.

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md) for details.
