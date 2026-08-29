<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Api\tests\Controller;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Api\Controller\OpenApiController;
use YiiRocks\Voyti\Api\OpenApi\OpenApiSpecBuilder;
use YiiRocks\Voyti\Api\OpenApi\OpenApiSpecContributorInterface;
use YiiRocks\Voyti\Api\tests\Support\FakeUrlGenerator;
use YiiRocks\Voyti\Api\tests\TestCase;
use Yiisoft\DataResponse\ResponseFactory\DataResponseFactoryInterface;
use Yiisoft\Router\Route;
use Yiisoft\Router\RouteCollectionInterface;

#[AllowMockObjectsWithoutExpectations]
final class OpenApiControllerTest extends TestCase
{
    private DataResponseFactoryInterface&MockObject $responseFactory;
    private RouteCollectionInterface&MockObject $routeCollection;
    private FakeUrlGenerator $url;

    protected function setUp(): void
    {
        $this->responseFactory = $this->createMock(DataResponseFactoryInterface::class);
        $this->routeCollection = $this->createMock(RouteCollectionInterface::class);
        $this->url = new FakeUrlGenerator();
        $this->url->setUrl('voyti/api-openapi', '/api/openapi.json');
    }

    public function testIndexDefinesMetadataAndBaseSchemas(): void
    {
        $this->routeCollection->method('getRoutes')->willReturn([]);

        $spec = $this->captureSpec([]);

        self::assertSame('3.1.0', $spec['openapi']);
        self::assertSame('Voyti API', $spec['info']['title']);
        self::assertSame('/api', $spec['servers'][0]['url']);
        self::assertSame('REST API', $spec['servers'][0]['description']);

        self::assertSame('http', $spec['components']['securitySchemes']['bearerAuth']['type']);
        self::assertSame('bearer', $spec['components']['securitySchemes']['bearerAuth']['scheme']);
        self::assertSame('JWT', $spec['components']['securitySchemes']['bearerAuth']['bearerFormat']);
        self::assertSame([['bearerAuth' => []]], $spec['security']);

        $errorSchema = $spec['components']['schemas']['ErrorResponse'];
        self::assertSame('object', $errorSchema['type']);
        self::assertSame(['error'], $errorSchema['required']);
        self::assertSame('string', $errorSchema['properties']['error']['type']);

        $messageSchema = $spec['components']['schemas']['MessageResponse'];
        self::assertSame('object', $messageSchema['type']);
        self::assertSame(['message'], $messageSchema['required']);
        self::assertSame('string', $messageSchema['properties']['message']['type']);
    }

    public function testIndexMergesContributors(): void
    {
        $this->routeCollection->method('getRoutes')->willReturn([
            Route::get('v1/widgets')->name('widgets-index'),
            Route::post('v1/widgets')->name('widgets-create'),
            Route::get('v1/widgets/{id:\d+}')->name('widgets-view'),
        ]);

        $widgetContributor = new class implements OpenApiSpecContributorInterface {
            public function getMethodSpec(string $routeName, string $method): ?array
            {
                if ($method !== 'get') {
                    return null;
                }

                return match ($routeName) {
                    'widgets-index' => ['operationId' => 'listWidgets'],
                    'widgets-view' => ['operationId' => 'getWidget'],
                    default => null,
                };
            }

            public function schemas(): array
            {
                return ['Widget' => ['type' => 'object']];
            }
        };

        $gadgetContributor = new class implements OpenApiSpecContributorInterface {
            public function getMethodSpec(string $routeName, string $method): ?array
            {
                return $routeName === 'widgets-create' && $method === 'post'
                    ? ['operationId' => 'createWidget']
                    : null;
            }

            public function schemas(): array
            {
                return ['Gadget' => ['type' => 'object']];
            }
        };

        $spec = $this->captureSpec([$widgetContributor, $gadgetContributor]);

        self::assertSame('listWidgets', $spec['paths']['/widgets']['get']['operationId']);
        self::assertSame('createWidget', $spec['paths']['/widgets']['post']['operationId']);
        self::assertSame('getWidget', $spec['paths']['/widgets/{id}']['get']['operationId']);
        self::assertSame(['type' => 'object'], $spec['components']['schemas']['Widget']);
        self::assertSame(['type' => 'object'], $spec['components']['schemas']['Gadget']);
    }

    public function testIndexSkipsUnrecognizedRoutesAndNonStringMethods(): void
    {
        $nonStringMethodRoute = new class {
            public function getData(string $key): mixed
            {
                return match ($key) {
                    'name' => 'widgets-index',
                    'pattern' => 'v1/widgets',
                    'methods' => [123, 'GET'],
                    default => null,
                };
            }
        };

        $this->routeCollection->method('getRoutes')->willReturn([
            Route::get('v1/unknown')->name('unknown-index'),
            $nonStringMethodRoute,
        ]);

        $contributor = new class implements OpenApiSpecContributorInterface {
            public function getMethodSpec(string $routeName, string $method): ?array
            {
                return $routeName === 'widgets-index' && $method === 'get' ? ['operationId' => 'listWidgets'] : null;
            }

            public function schemas(): array
            {
                return [];
            }
        };

        $spec = $this->captureSpec([$contributor]);

        self::assertCount(1, $spec['paths']);
        self::assertArrayHasKey('/widgets', $spec['paths']);
        self::assertArrayNotHasKey('/unknown', $spec['paths']);
    }

    /**
     * @param iterable<OpenApiSpecContributorInterface> $contributors
     */
    private function captureSpec(iterable $contributors): array
    {
        $captured = null;
        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')
            ->willReturnCallback(static function (array $data) use (&$captured, $response): ResponseInterface {
                $captured = $data;

                return $response;
            });

        $builder = new OpenApiSpecBuilder($this->routeCollection, $contributors);
        $controller = new OpenApiController($this->responseFactory, $builder, $this->url);
        $controller->index();

        self::assertNotNull($captured, 'createResponse was not called');

        return $captured;
    }
}
