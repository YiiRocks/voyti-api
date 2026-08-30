<?php

declare(strict_types=1);

use YiiRocks\Voyti\Api\Controller\OpenApiController;
use YiiRocks\Voyti\Api\Middleware\AccessRuleMiddleware;
use YiiRocks\Voyti\Api\Middleware\ApiExtensionMiddleware;
use YiiRocks\Voyti\Api\Middleware\ApiTokenAuthenticationMiddleware;
use Yiisoft\DataResponse\Middleware\JsonDataResponseMiddleware;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;

/** @var array $params */

return [
    // JsonDataResponseMiddleware wraps every API response, authenticated or not, so it's registered
    // once on this outer group and inherited by every nested group below (middleware propagates down
    // through Group nesting, parent first).
    Group::create()
        ->middleware(JsonDataResponseMiddleware::class)
        ->routes(
            // Resource packages contribute their own Route objects via the `yiirocks/voyti.api.routes`
            // param instead of declaring this group themselves, so the auth + extension + admin-access
            // middleware chain is defined once, here. Not scoped to any one API version: each
            // contributed Route carries its own version segment in its path/name, same as
            // ApiExtensionMiddleware already applies across every version.
            Group::create()
                ->middleware(
                    ApiTokenAuthenticationMiddleware::class,
                    ApiExtensionMiddleware::class,
                    AccessRuleMiddleware::class,
                )
                ->routes(...$params['yiirocks/voyti']['api']['routes']),

            // Same middleware, minus AccessRuleMiddleware — any authenticated bearer-token holder.
            // Contributed via `yiirocks/voyti.api.authenticatedRoutes`.
            Group::create()
                ->middleware(
                    ApiTokenAuthenticationMiddleware::class,
                    ApiExtensionMiddleware::class,
                )
                ->routes(...$params['yiirocks/voyti']['api']['authenticatedRoutes']),

            // No authentication at all, still passes through ApiExtensionMiddleware (e.g. per-IP
            // rate limiting). Contributed via `yiirocks/voyti.api.publicRoutes`.
            Group::create()
                ->middleware(ApiExtensionMiddleware::class)
                ->routes(...$params['yiirocks/voyti']['api']['publicRoutes']),

            // openapi.json merges every installed resource package's OpenApiSpecContributorInterface
            // (see OpenApiSpecBuilder), so it's registered here rather than contributed by a resource
            // package, and stays outside every authenticated group above since the spec itself must be
            // reachable without a bearer token.
            Group::create()
                ->routes(
                    Route::get('openapi.json')->name('voyti/api-openapi')->action([OpenApiController::class, 'index']),
                ),
        ),
];
