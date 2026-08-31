<?php

declare(strict_types=1);

use YiiRocks\Voyti\Api\Adapter\ApiTokenIdentityAdapter;
use YiiRocks\Voyti\Api\ApiConfig;
use YiiRocks\Voyti\Api\Console\GenerateApiTokenCommand;
use YiiRocks\Voyti\Api\Console\RevokeApiTokenCommand;
use YiiRocks\Voyti\Api\Middleware\AccessRuleMiddleware;
use YiiRocks\Voyti\Api\Middleware\ApiExtensionMiddleware;
use YiiRocks\Voyti\Api\Middleware\ApiTokenAuthenticationMiddleware;
use YiiRocks\Voyti\Api\Middleware\LocaleMiddleware;
use YiiRocks\Voyti\Api\OpenApi\OpenApiSpecBuilder;
use YiiRocks\Voyti\Api\Service\User\ApiTokenService;
use Yiisoft\Auth\IdentityWithTokenRepositoryInterface;
use Yiisoft\Di\Reference\TagReference;
use Yiisoft\I18n\Locale;
use Yiisoft\I18n\LocaleProvider;

/** @var array $params */

return [
    ApiConfig::class => static fn() => new ApiConfig(
        apiTokenLifespan: $params['yiirocks/voyti']['api']['apiTokenLifespan'] ?? 0,
        defaultLocale: $params['yiirocks/voyti']['api']['defaultLocale'] ?? 'en',
    ),

    // Overrides only the bearer-token path; core still owns IdentityRepositoryInterface for web/ID lookups.
    IdentityWithTokenRepositoryInterface::class => ApiTokenIdentityAdapter::class,
    ApiTokenIdentityAdapter::class => ApiTokenIdentityAdapter::class,

    // Bearer auth, then every middleware tagged voyti-api.extension-middleware, then the admin check.
    ApiTokenAuthenticationMiddleware::class => ApiTokenAuthenticationMiddleware::class,
    ApiExtensionMiddleware::class => [
        'class' => ApiExtensionMiddleware::class,
        '__construct()' => [
            'extensionMiddlewares' => TagReference::to('voyti-api.extension-middleware'),
        ],
    ],
    AccessRuleMiddleware::class => AccessRuleMiddleware::class,
    LocaleMiddleware::class => LocaleMiddleware::class,

    // Locale has no default constructor, so this can't be autowired; built from the same defaultLocale.
    LocaleProvider::class => static fn(ApiConfig $config): LocaleProvider => new LocaleProvider(
        new Locale($config->defaultLocale),
    ),

    // Merges every installed OpenApiSpecContributorInterface tagged voyti-api.openapi-contributor.
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
