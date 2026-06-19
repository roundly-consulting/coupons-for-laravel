# Coupons for Laravel

Create, manage, redeem, and apply discount coupons in Laravel. Coupons support fixed-amount,
percentage (optionally capped), and free-shipping discounts, an optional currency lock and
minimum spend, activation and expiry windows, global and per-redeemer usage limits, a
validated **atomic** redemption flow, query scopes, route-model binding, console commands, a
fluent `Coupons` facade, and a native immutable `Money` value object — with no third-party
runtime dependencies.

## Requirements

- PHP 8.3 or 8.4
- Laravel 12 or 13

## Installation

```bash
composer require roundly-consulting/coupons-for-laravel
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="coupons-migrations"
php artisan migrate
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="coupons-config"
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

### Money & the minor-unit convention

`Money` stores an integer amount in the currency's **minor unit** (e.g. cents) plus an ISO
4217 code, matching the convention used across the org's packages. Formatting and
`Money::fromMajor()` assume **two minor digits** (÷100), which covers the common currencies;
currencies with a different exponent are out of scope.

## Usage

### Money value object

Discounts operate on an immutable `Money` value object — an integer amount in the currency's
minor unit (e.g. cents) plus an ISO 4217 currency code.

```php
use RoundlyConsulting\Coupons\ValueObjects\Money;

$price = new Money(1000, 'EUR'); // €10.00

$price->getAmount();        // 1000
$price->getCurrency();      // 'EUR'
$price->add(new Money(500, 'EUR'));      // €15.00
$price->subtract(new Money(250, 'EUR')); // €7.50 (clamped at zero)
$price->multiply(0.5);                    // €5.00
$price->format('en_US');                  // "€10.00"

Money::zero('EUR');                       // €0.00
Money::fromMajor(10.50, 'EUR');           // €10.50 (1050 minor units)
```

### Discount types

The `DiscountType` enum decides how a coupon's value is applied to a price:

```php
use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Coupons\ValueObjects\Money;

// Subtract a fixed amount in minor units.
DiscountType::Fixed->apply(new Money(1000, 'EUR'), 250);      // €7.50

// Subtract a percentage (value is whole percent).
DiscountType::Percentage->apply(new Money(1000, 'EUR'), 25);  // €7.50

// Cap the discount at a maximum (minor units).
DiscountType::Percentage->apply(new Money(1000, 'EUR'), 50, maxDiscount: 300); // €7.00

// Free shipping is a marker: it discounts nothing from the price. The host
// zeroes its own shipping total when the redemption reports free shipping.
DiscountType::FreeShipping->apply(new Money(1000, 'EUR'), 0); // €10.00 (unchanged)
```

### The `Coupons` facade

The `Coupons` facade is the discoverable entry point — generate, find, and redeem in one call.

```php
use RoundlyConsulting\Coupons\Facades\Coupons;
use RoundlyConsulting\Coupons\Enums\DiscountType;
use RoundlyConsulting\Coupons\ValueObjects\Money;

$coupon = Coupons::generate(DiscountType::Percentage, value: 20, code: 'SAVE20', maxUsage: 100);

$coupon = Coupons::find('SAVE20');        // ?Coupon
$coupon = Coupons::findOrFail('SAVE20');  // throws CouponNotFound

$result = Coupons::redeem('SAVE20', new Money(5000, 'EUR'), redeemer: $user);
```

### Creating coupons

Use the facade, or `CreateCouponAction` with a `CreateCouponData` DTO. A unique code is
generated when you don't supply one, and a `CouponCreated` event is dispatched.

```php
use RoundlyConsulting\Coupons\Actions\CreateCouponAction;
use RoundlyConsulting\Coupons\DataTransferObjects\CreateCouponData;
use RoundlyConsulting\Coupons\Enums\DiscountType;

$coupon = app(CreateCouponAction::class)->execute(
    new CreateCouponData(
        type: DiscountType::Percentage,
        value: 20,           // 20% off
        code: 'SAVE20',      // optional — auto-generated when omitted
        maxUsage: 100,       // optional — 0 means unlimited
    ),
);
```

### Redeeming a coupon

Redemption validates eligibility, increments usage **atomically** (a DB transaction with
`lockForUpdate`, so concurrent redemptions can never exceed `max_usage`), records a
per-redeemer row when tracking is on, and dispatches `CouponRedeemed`. It returns a
`RedemptionResult { Coupon $coupon, Money $discount, Money $total, ?Model $redeemer, bool $freeShipping }`.

```php
use RoundlyConsulting\Coupons\Facades\Coupons;
use RoundlyConsulting\Coupons\ValueObjects\Money;

$result = Coupons::redeem('SAVE20', new Money(5000, 'EUR'), redeemer: $user);

$result->discount;      // Money saved off the price
$result->total;         // new total after the discount
$result->freeShipping;  // true for a free-shipping coupon — zero your own shipping line

// Fluent equivalent on the model (redeemer may be null for guest checkout):
$result = $coupon->redeemBy($user, new Money(5000, 'EUR'));
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
$coupon->currency;                 // null = any currency, or a locked ISO code
$coupon->minimum_spend;            // null = no minimum, or a threshold in minor units
$coupon->max_usage_per_redeemer;   // 0 = unlimited per redeemer

$coupon->appliesToCurrency($price);     // bool
$coupon->meetsMinimumSpend($price);     // bool
$coupon->usageBy($user);                // int
$coupon->isAtMaximumUsageFor($user);    // bool — "one per customer" when cap is 1
```

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

$fake = Coupons::fake();

Coupons::redeem('SAVE20', new Money(5000, 'EUR'), redeemer: $user);

$fake->assertRedeemed();
$fake->assertRedeemed(fn ($result) => $result->coupon->code === 'SAVE20');
$fake->assertNothingRedeemed();
$fake->assertCreated();
```

### Working with a coupon

```php
use RoundlyConsulting\Coupons\Models\Coupon;
use RoundlyConsulting\Coupons\ValueObjects\Money;

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
$discounted = $coupon->apply(new Money(5000, 'EUR')); // €40.00 for 20% off
```

### Listening for events

The package dispatches `CouponCreated` when a coupon is created and `CouponRedeemed` when one
is redeemed:

```php
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Coupons\Events\CouponCreated;
use RoundlyConsulting\Coupons\Events\CouponRedeemed;

Event::listen(function (CouponCreated $event): void {
    logger()->info('Coupon created', ['code' => $event->coupon->code]);
});

Event::listen(function (CouponRedeemed $event): void {
    logger()->info('Coupon redeemed', [
        'code' => $event->coupon->code,
        'discount' => $event->result->discount->getAmount(),
    ]);
});
```

### Per-redeemer tracking migration

Run the published migrations to add the `coupon_redemptions` table that powers per-redeemer
caps. The redeemer is a nullable morph, so guest (redeemer-less) redemptions are supported;
per-redeemer caps apply only when a redeemer is supplied.

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
