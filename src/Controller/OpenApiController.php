<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Api\Controller;

use Psr\Http\Message\ResponseInterface;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Router\RouteCollectionInterface;
use Yiisoft\Router\UrlGeneratorInterface;

final readonly class OpenApiController
{
    public function __construct(
        private DataResponseFactoryInterface $responseFactory,
        private RouteCollectionInterface $routeCollection,
        private UrlGeneratorInterface $url,
    ) {}

    public function index(): ResponseInterface
    {
        $serverUrl = dirname($this->url->generate('voyti/api-v1-users-index'));
        $builder = new OpenApiSpecBuilder($this->routeCollection);
        $spec = $builder->buildSpec($serverUrl);

        return $this->responseFactory->createResponse($spec);
    }
}
