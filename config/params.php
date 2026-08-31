<?php

declare(strict_types=1);

use YiiRocks\Voyti\Api\Console\GenerateApiTokenCommand;
use YiiRocks\Voyti\Api\Console\RevokeApiTokenCommand;

return [
    'yiirocks/voyti' => [
        'api' => [
            // Seconds an API access token stays valid; 0 = never expires. Enforced by ApiTokenIdentityAdapter.
            'apiTokenLifespan' => 0,

            // Falls back to this locale when `Accept-Language` is missing or matches nothing discovered.
            'defaultLocale' => 'en',

            // Route objects from resource packages, wrapped in the shared auth+admin middleware group in
            // routes.php.
            'routes' => [],

            // Same as `routes`, but no admin-access check
            'authenticatedRoutes' => [],

            // Same as `routes`, but no authentication - reachable before a token exists
            'publicRoutes' => [],
        ],
    ],

    'yiisoft/yii-console' => [
        'commands' => [
            'voyti:api-token:generate' => GenerateApiTokenCommand::class,
            'voyti:api-token:revoke' => RevokeApiTokenCommand::class,
        ],
    ],
];
