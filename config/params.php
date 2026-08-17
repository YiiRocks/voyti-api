<?php

declare(strict_types=1);

use YiiRocks\Voyti\Api\Console\GenerateApiTokenCommand;
use YiiRocks\Voyti\Api\Console\RevokeApiTokenCommand;

return [
    'yiirocks/voyti-api' => [
        // How long (in seconds) an API access token stays valid; 0 means it never expires. Enforced
        // by ApiTokenIdentityAdapter when resolving a bearer token.
        'apiTokenLifespan' => 0,
    ],

    'yiisoft/yii-console' => [
        'commands' => [
            'voyti:api-token:generate' => GenerateApiTokenCommand::class,
            'voyti:api-token:revoke' => RevokeApiTokenCommand::class,
        ],
    ],
];
