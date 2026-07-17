<?php

declare(strict_types=1);

namespace RoundlyConsulting\Coupons\Tests;

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Coupons\CouponsServiceProvider;
use RoundlyConsulting\Testing\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
    /**
     * Every provider coupons hard-requires, in registration order. A host auto-discovers
     * these; the suite must list them or the test environment is a fiction.
     *
     * @return list<class-string<ServiceProvider>>
     */
    protected function packageProviders(): array
    {
        return [CouponsServiceProvider::class];
    }

    /**
     * The two coupon migrations, named by provider class (never by filename), plus the
     * host-owned `customers` fixture table the redeemer lives in.
     *
     * @return list<class-string<ServiceProvider>|string>
     */
    protected function migrationSources(): array
    {
        return [
            CouponsServiceProvider::class,
            __DIR__.'/database/migrations',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function configBeforeBoot(): array
    {
        return [
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ];
    }
}
