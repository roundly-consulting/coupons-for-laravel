<?php

declare(strict_types=1);

return [
    'not_found' => 'Kupón „:code“ neexistuje.',
    'currency_mismatch' => 'Tento kupón nie je možné použiť vo vybranej mene.',
    'minimum_spend_not_met' => 'Vaša objednávka nedosahuje minimálnu hodnotu potrebnú pre tento kupón.',
    'expired' => 'Tento kupón už nie je k dispozícii.',
    'at_max_usage' => 'Tento kupón už dosiahol maximálny počet použití.',
    'already_redeemed' => 'Tento kupón ste už uplatnili.',

    'type' => [
        'fixed' => [
            'label' => 'Pevná suma',
            'description' => 'Odpočíta z ceny pevnú sumu.',
        ],
        'percentage' => [
            'label' => 'Percentuálna zľava',
            'description' => 'Odpočíta z ceny zadané percento.',
        ],
        'free_shipping' => [
            'label' => 'Doprava zadarmo',
            'description' => 'Označí objednávku na dopravu zadarmo.',
        ],
    ],
];
