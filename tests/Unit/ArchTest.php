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

arch('the package never depends on acme')
    ->expect('RoundlyConsulting\Coupons')
    ->not->toUse('Acme');

arch('strict types are declared everywhere')
    ->expect('RoundlyConsulting\Coupons')
    ->toUseStrictTypes();
