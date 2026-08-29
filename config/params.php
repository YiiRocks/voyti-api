<?php

declare(strict_types=1);

use YiiRocks\Voyti\Api\Console\GenerateApiTokenCommand;
use YiiRocks\Voyti\Api\Console\RevokeApiTokenCommand;

return [
    'yiirocks/voyti' => [
        'api' => [
            // How long (in seconds) an API access token stays valid; 0 means it never expires. Enforced
            // by ApiTokenIdentityAdapter when resolving a bearer token.
            'apiTokenLifespan' => 0,

            // Route objects contributed by resource packages, wrapped in the shared auth + admin-access
            // middleware group this package owns in config/routes.php. Not version-scoped: each
            // package's own Route paths/names carry their own version segment. Cross-package list
            // merge, same pattern as core's `accountMenuItems`.
            'routes' => [],
        ],
    ],

    'yiisoft/yii-console' => [
        'commands' => [
            'voyti:api-token:generate' => GenerateApiTokenCommand::class,
            'voyti:api-token:revoke' => RevokeApiTokenCommand::class,
        ],
    ],
];
