<?php

declare(strict_types=1);

arch('actions are final')
    ->expect('RoundlyConsulting\Coupons\Actions')
    ->toBeClasses()
    ->toBeFinal();

arch('data transfer objects are final and readonly')
    ->expect('RoundlyConsulting\Coupons\DataTransferObjects')
    ->toBeFinal()
    ->toBeReadonly();

// Allow-list the vendor roots src may touch. Anything outside this set — any
// non-whitelisted third-party vendor — fails the suite implicitly.
arch('src uses only allowed namespaces')
    ->expect('RoundlyConsulting\Coupons')
    ->toOnlyUse([
        'RoundlyConsulting\Coupons',
        'RoundlyConsulting\Coupons\Database\Factories',
        'Illuminate',
        'Carbon',
        'Closure',
        'NumberFormatter',
        'RuntimeException',
        'Stringable',
        // native/framework helpers used unqualified
        'app',
        'config',
        'config_path',
        'database_path',
        'now',
        'trans',
        'dispatch',
        'fake',
    ])
    // FakeCouponManager ships PHPUnit assertions for host-app tests.
    ->ignoring('PHPUnit\Framework\Assert');

arch('strict types are declared everywhere')
    ->expect('RoundlyConsulting\Coupons')
    ->toUseStrictTypes();
