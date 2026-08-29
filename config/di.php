<?php

declare(strict_types=1);

use YiiRocks\Voyti\Api\Adapter\ApiTokenIdentityAdapter;
use YiiRocks\Voyti\Api\ApiConfig;
use YiiRocks\Voyti\Api\Console\GenerateApiTokenCommand;
use YiiRocks\Voyti\Api\Console\RevokeApiTokenCommand;
use YiiRocks\Voyti\Api\Middleware\AccessRuleMiddleware;
use YiiRocks\Voyti\Api\Middleware\ApiExtensionMiddleware;
use YiiRocks\Voyti\Api\Middleware\ApiTokenAuthenticationMiddleware;
use YiiRocks\Voyti\Api\OpenApi\OpenApiSpecBuilder;
use YiiRocks\Voyti\Api\Service\User\ApiTokenService;
use Yiisoft\Auth\IdentityWithTokenRepositoryInterface;
use Yiisoft\Di\Reference\TagReference;

/** @var array $params */

return [
    ApiConfig::class => static fn() => new ApiConfig(
        apiTokenLifespan: $params['yiirocks/voyti']['api']['apiTokenLifespan'] ?? 0,
    ),

    // Token resolution honors API-token expiry. Core still owns `IdentityRepositoryInterface`
    // (web lookups by ID); this package only overrides the bearer-token path.
    IdentityWithTokenRepositoryInterface::class => ApiTokenIdentityAdapter::class,
    ApiTokenIdentityAdapter::class => ApiTokenIdentityAdapter::class,

    // API route group wiring: bearer auth, then every middleware tagged
    // `voyti-api.extension-middleware` (installed extension packages, e.g. rate limiting - see
    // ApiExtensionMiddleware; wired into every versioned route group, not just v1/), then the admin check.
    ApiTokenAuthenticationMiddleware::class => ApiTokenAuthenticationMiddleware::class,
    ApiExtensionMiddleware::class => [
        'class' => ApiExtensionMiddleware::class,
        '__construct()' => [
            'extensionMiddlewares' => TagReference::to('voyti-api.extension-middleware'),
        ],
    ],
    AccessRuleMiddleware::class => AccessRuleMiddleware::class,

    // Merges every installed resource package's OpenApiSpecContributorInterface, tagged
    // `voyti-api.openapi-contributor`, into one openapi.json spec.
    OpenApiSpecBuilder::class => [
        'class' => OpenApiSpecBuilder::class,
        '__construct()' => [
            'contributors' => TagReference::to('voyti-api.openapi-contributor'),
        ],
    ],

    ApiTokenService::class => ApiTokenService::class,
    GenerateApiTokenCommand::class => GenerateApiTokenCommand::class,
    RevokeApiTokenCommand::class => RevokeApiTokenCommand::class,
];
