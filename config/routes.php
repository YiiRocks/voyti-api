<?php

declare(strict_types=1);

use YiiRocks\Voyti\Api\Controller\OpenApiController;
use YiiRocks\Voyti\Api\Controller\V1\User\UserController;
use YiiRocks\Voyti\Api\Middleware\AccessRuleMiddleware;
use YiiRocks\Voyti\Api\Middleware\ApiExtensionMiddleware;
use YiiRocks\Voyti\Api\Middleware\ApiTokenAuthenticationMiddleware;
use Yiisoft\DataResponse\Middleware\JsonDataResponseMiddleware;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;

return [
    Group::create()
        ->middleware(JsonDataResponseMiddleware::class)
        ->routes(
            Route::get('openapi.json')->name('voyti/api-openapi')->action([OpenApiController::class, 'index']),
            Group::create('v1/')
                ->middleware(
                    ApiTokenAuthenticationMiddleware::class,
                    ApiExtensionMiddleware::class,
                    AccessRuleMiddleware::class,
                )
                ->routes(
                    Route::get('users')->name('voyti/api-v1-users-index')->action([UserController::class, 'index']),
                    Route::get('users/{id:\d+}')->name('voyti/api-v1-users-view')->action([UserController::class, 'view']),
                    Route::post('users')->name('voyti/api-v1-users-create')->action([UserController::class, 'create']),
                    Route::patch('users/{id:\d+}')->name('voyti/api-v1-users-update')->action([UserController::class, 'update']),
                    Route::delete('users/{id:\d+}')->name('voyti/api-v1-users-delete')->action([UserController::class, 'delete']),
                ),
        ),
];
