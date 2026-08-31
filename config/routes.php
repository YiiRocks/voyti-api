<?php

declare(strict_types=1);

use YiiRocks\Voyti\Api\Controller\OpenApiController;
use YiiRocks\Voyti\Api\Middleware\AccessRuleMiddleware;
use YiiRocks\Voyti\Api\Middleware\ApiExtensionMiddleware;
use YiiRocks\Voyti\Api\Middleware\ApiTokenAuthenticationMiddleware;
use YiiRocks\Voyti\Api\Middleware\LocaleMiddleware;
use Yiisoft\DataResponse\Middleware\JsonDataResponseMiddleware;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;

/** @var array $params */

return [
    // LocaleMiddleware + JsonDataResponseMiddleware run once here and propagate to every nested group
    // below, so every response - including from public routes - is localized and JSON-wrapped.
    Group::create()
        ->middleware(LocaleMiddleware::class, JsonDataResponseMiddleware::class)
        ->routes(
            // Resource packages contribute Route objects instead of declaring this middleware chain
            // themselves. Not version-scoped - each Route carries its own version segment.
            Group::create()
                ->middleware(
                    ApiTokenAuthenticationMiddleware::class,
                    ApiExtensionMiddleware::class,
                    AccessRuleMiddleware::class,
                )
                ->routes(...$params['yiirocks/voyti']['api']['routes']),

            // Same middleware minus AccessRuleMiddleware.
            Group::create()
                ->middleware(
                    ApiTokenAuthenticationMiddleware::class,
                    ApiExtensionMiddleware::class,
                )
                ->routes(...$params['yiirocks/voyti']['api']['authenticatedRoutes']),

            // No authentication, still passes through ApiExtensionMiddleware (e.g. rate limiting).
            Group::create()
                ->middleware(ApiExtensionMiddleware::class)
                ->routes(...$params['yiirocks/voyti']['api']['publicRoutes']),

            // Registered here, not via a resource package, since openapi.json merges every contributor
            // and must stay reachable without a bearer token - outside every group above.
            Group::create()
                ->routes(
                    Route::get('openapi.json')->name('voyti/api-openapi')->action([OpenApiController::class, 'index']),
                ),
        ),
];
