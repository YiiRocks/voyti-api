<?php

declare(strict_types=1);

use YiiRocks\Voyti\Api\Adapter\ApiTokenIdentityAdapter;
use YiiRocks\Voyti\Api\ApiConfig;
use YiiRocks\Voyti\Api\Console\GenerateApiTokenCommand;
use YiiRocks\Voyti\Api\Console\RevokeApiTokenCommand;
use YiiRocks\Voyti\Api\Middleware\AccessRuleMiddleware;
use YiiRocks\Voyti\Api\Middleware\ApiTokenAuthenticationMiddleware;
use YiiRocks\Voyti\Api\Service\User\ApiTokenService;
use Yiisoft\Auth\IdentityWithTokenRepositoryInterface;

/** @var array $params */

return [
    ApiConfig::class => static fn() => new ApiConfig(
        apiTokenLifespan: $params['yiirocks/voyti-api']['apiTokenLifespan'] ?? 0,
    ),

    // Token resolution honors API-token expiry. Core still owns `IdentityRepositoryInterface`
    // (web lookups by ID); this package only overrides the bearer-token path.
    IdentityWithTokenRepositoryInterface::class => ApiTokenIdentityAdapter::class,
    ApiTokenIdentityAdapter::class => ApiTokenIdentityAdapter::class,

    // API route group wiring: bearer auth + admin check.
    ApiTokenAuthenticationMiddleware::class => ApiTokenAuthenticationMiddleware::class,
    AccessRuleMiddleware::class => AccessRuleMiddleware::class,

    ApiTokenService::class => ApiTokenService::class,
    GenerateApiTokenCommand::class => GenerateApiTokenCommand::class,
    RevokeApiTokenCommand::class => RevokeApiTokenCommand::class,
];
