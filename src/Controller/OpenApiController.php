<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Api\Controller;

use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Api\OpenApi\OpenApiSpecBuilder;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Router\UrlGeneratorInterface;

final readonly class OpenApiController
{
    public function __construct(
        private DataResponseFactoryInterface $responseFactory,
        private OpenApiSpecBuilder $specBuilder,
        private UrlGeneratorInterface $url,
    ) {}

    public function index(): ResponseInterface
    {
        $serverUrl = dirname($this->url->generate('voyti/api-openapi'));

        return $this->responseFactory->createResponse($this->specBuilder->buildSpec($serverUrl));
    }
}
