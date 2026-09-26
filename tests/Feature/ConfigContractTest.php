<?php

declare(strict_types=1);

/**
 * The config contract coupons never had, pinned in both directions:
 *
 *  - forward — every key the code reads is shipped. This is shops #18, whose whole
 *    store-credit feature read `shops.payments.*` while the file shipped `payment.*`;
 *    330 tests stayed green because the suite set the same wrong key. Coupons is on the
 *    other side of that same checkout.
 *  - reverse — every shipped leaf is read. A documented key nothing reads is dead config
 *    that lies to the host: media #27's `max_file_size` cap that never applied, alerts
 *    #24's thrice-documented `escalation` key. `coupons.code.*` shipped exactly this
 *    way: read only by the `about` section while every generated code stayed 6 chars
 *    of A–Z0–9. A read is not proof of use — tests/Feature/CodeGenerationTest.php pins
 *    that generation honours both keys.
 */
it('ships exactly the config keys it reads', function (): void {
    expect(__DIR__.'/../../config/coupons.php')->toSatisfyConfigContract([__DIR__.'/../../src', __DIR__.'/../../database'], [
        // `coupons.model` is read through the toolkit's `ModelResolver::for('coupons.model',
        // …)` seam, and `coupons.key_type` through `KeyType::fromConfig('coupons.key_type')`
        // in the migration (hence `database` in the scanned dirs). Both are real reads — the
        // model key drives the swap, the key type decides the shipped morph column type — but
        // neither is a `config(` token, so the prefix is what makes them visible to the scraper.
        'extraReadPrefixes' => ['coupons.'],

        // Deliberately NO `excludeFromReverse` for the provider. The testing README's own
        // example excludes the service provider on the grounds that "a render is not a
        // read" — but CouponsServiceProvider's contributesToAbout() closure calls
        // config('coupons.…') for real, through currency()/routeKey(). It is the genuine
        // reader of default_currency; excluding it would discard readers and weaken the
        // reverse direction for nothing. (`coupons.code.*` is read by Support\CodeFormat.)
    ]);
});
