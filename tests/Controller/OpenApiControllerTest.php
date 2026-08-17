<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Api\tests\Controller;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Api\Controller\OpenApiController;
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
        $this->url->setUrl('voyti/api-v1-users-index', '/api/v1/users');

        $this->routeCollection->method('getRoutes')
            ->willReturn($this->createApiRoutes());
    }

    public function testIndexDefinesEndpoints(): void
    {
        $spec = $this->captureSpec();

        $listGet = $spec['paths']['/users']['get'];
        self::assertSame('listUsers', $listGet['operationId']);
        self::assertSame('List users', $listGet['summary']);
        self::assertSame(['Users'], $listGet['tags']);
        self::assertSame(5, count($listGet['parameters']));
        $paramNames = array_map(fn($p) => $p['name'], $listGet['parameters']);
        self::assertContains('username', $paramNames);
        self::assertContains('email', $paramNames);
        self::assertContains('status', $paramNames);
        self::assertContains('page', $paramNames);
        self::assertContains('perPage', $paramNames);
        foreach ($listGet['parameters'] as $param) {
            self::assertArrayHasKey('schema', $param, "Parameter {$param['name']} must have schema");
            self::assertArrayHasKey('type', $param['schema'], "Schema for {$param['name']} must have type");
        }
        self::assertSame('Paginated list of users', $listGet['responses']['200']['description']);
        self::assertSame('#/components/schemas/PaginatedUsers', $listGet['responses']['200']['content']['application/json']['schema']['$ref']);
        $pageParam = null;
        $perPageParam = null;
        foreach ($listGet['parameters'] as $param) {
            if ($param['name'] === 'page') {
                $pageParam = $param;
            } elseif ($param['name'] === 'perPage') {
                $perPageParam = $param;
            }
        }
        self::assertNotNull($pageParam, 'page parameter must be defined');
        self::assertSame('query', $pageParam['in']);
        self::assertSame('integer', $pageParam['schema']['type']);
        self::assertSame(1, $pageParam['schema']['default']);
        self::assertNotNull($perPageParam, 'perPage parameter must be defined');
        self::assertSame('query', $perPageParam['in']);
        self::assertSame('integer', $perPageParam['schema']['type']);
        self::assertSame(25, $perPageParam['schema']['default']);
        self::assertSame(100, $perPageParam['schema']['maximum']);

        $createPost = $spec['paths']['/users']['post'];
        self::assertSame('createUser', $createPost['operationId']);
        self::assertSame('Create a user', $createPost['summary']);
        self::assertSame(['Users'], $createPost['tags']);
        self::assertArrayHasKey('requestBody', $createPost, 'POST /users must have requestBody');
        self::assertTrue($createPost['requestBody']['required'], 'POST /users requestBody must be required');
        self::assertSame('#/components/schemas/UserCreateRequest', $createPost['requestBody']['content']['application/json']['schema']['$ref']);
        self::assertSame('User created', $createPost['responses']['201']['description']);
        self::assertSame('#/components/schemas/UserCreatedResponse', $createPost['responses']['201']['content']['application/json']['schema']['$ref']);
        self::assertSame('Validation error', $createPost['responses']['400']['description']);
        self::assertSame('#/components/schemas/ErrorResponse', $createPost['responses']['400']['content']['application/json']['schema']['$ref']);

        $getUser = $spec['paths']['/users/{id}']['get'];
        self::assertSame('getUser', $getUser['operationId']);
        self::assertSame('Get a user by ID', $getUser['summary']);
        self::assertSame(['Users'], $getUser['tags']);
        self::assertSame('id', $getUser['parameters'][0]['name']);
        self::assertSame('path', $getUser['parameters'][0]['in']);
        self::assertTrue($getUser['parameters'][0]['required']);
        self::assertSame('integer', $getUser['parameters'][0]['schema']['type']);
        self::assertSame('User details', $getUser['responses']['200']['description']);
        self::assertSame('#/components/schemas/User', $getUser['responses']['200']['content']['application/json']['schema']['$ref']);
        self::assertSame('User not found', $getUser['responses']['404']['description']);
        self::assertSame('#/components/schemas/ErrorResponse', $getUser['responses']['404']['content']['application/json']['schema']['$ref']);

        $updatePatch = $spec['paths']['/users/{id}']['patch'];
        self::assertSame('updateUser', $updatePatch['operationId']);
        self::assertSame('Update a user', $updatePatch['summary']);
        self::assertSame(['Users'], $updatePatch['tags']);
        self::assertSame('id', $updatePatch['parameters'][0]['name']);
        self::assertSame('path', $updatePatch['parameters'][0]['in']);
        self::assertTrue($updatePatch['parameters'][0]['required']);
        self::assertSame('integer', $updatePatch['parameters'][0]['schema']['type']);
        self::assertArrayHasKey('requestBody', $updatePatch, 'PATCH /users/{id} must have requestBody');
        self::assertTrue($updatePatch['requestBody']['required'], 'PATCH requestBody must be required');
        self::assertSame('#/components/schemas/UserUpdateRequest', $updatePatch['requestBody']['content']['application/json']['schema']['$ref']);
        self::assertSame('User updated', $updatePatch['responses']['200']['description']);
        self::assertSame('#/components/schemas/UserUpdatedResponse', $updatePatch['responses']['200']['content']['application/json']['schema']['$ref']);
        self::assertSame('Validation error', $updatePatch['responses']['400']['description']);
        self::assertSame('#/components/schemas/ErrorResponse', $updatePatch['responses']['400']['content']['application/json']['schema']['$ref']);
        $update404 = $updatePatch['responses']['404'];
        self::assertArrayHasKey('description', $update404, 'PATCH 404 response must have description');
        self::assertSame('User not found', $update404['description']);
        self::assertArrayHasKey('application/json', $update404['content'], 'PATCH 404 content must have application/json');
        self::assertArrayHasKey('schema', $update404['content']['application/json'], 'PATCH 404 schema must be defined');
        self::assertSame('#/components/schemas/ErrorResponse', $update404['content']['application/json']['schema']['$ref']);

        $deleteOp = $spec['paths']['/users/{id}']['delete'];
        self::assertSame('deleteUser', $deleteOp['operationId']);
        self::assertSame('Delete a user', $deleteOp['summary']);
        self::assertSame(['Users'], $deleteOp['tags']);
        self::assertSame('id', $deleteOp['parameters'][0]['name']);
        self::assertSame('path', $deleteOp['parameters'][0]['in']);
        self::assertTrue($deleteOp['parameters'][0]['required']);
        self::assertSame('integer', $deleteOp['parameters'][0]['schema']['type']);
        self::assertSame('User deleted', $deleteOp['responses']['200']['description']);
        self::assertSame('#/components/schemas/MessageResponse', $deleteOp['responses']['200']['content']['application/json']['schema']['$ref']);
        self::assertArrayHasKey('404', $deleteOp['responses'], 'DELETE must have 404 response');
        $delete404 = $deleteOp['responses']['404'];
        self::assertIsArray($delete404);
        self::assertCount(2, $delete404, '404 response must have exactly description and content');
        self::assertArrayHasKey('description', $delete404, '404 response must have description');
        self::assertSame('User not found', $delete404['description']);
        self::assertArrayHasKey('content', $delete404, '404 response must have content');
        $deleteContent = $delete404['content'];
        self::assertIsArray($deleteContent);
        self::assertCount(1, $deleteContent);
        self::assertArrayHasKey('application/json', $deleteContent, '404 content must have application/json');
        $deleteAppJson = $deleteContent['application/json'];
        self::assertIsArray($deleteAppJson);
        self::assertCount(1, $deleteAppJson);
        self::assertArrayHasKey('schema', $deleteAppJson, '404 schema must be defined');
        self::assertSame('#/components/schemas/ErrorResponse', $deleteAppJson['schema']['$ref']);
    }

    public function testIndexDefinesMetadata(): void
    {
        $spec = $this->captureSpec();

        self::assertSame('3.1.0', $spec['openapi']);
        self::assertSame('User Management API', $spec['info']['title']);
        self::assertSame('1.0.0', $spec['info']['version']);
        self::assertSame('User management, authentication, and authorization REST API.', $spec['info']['description']);

        self::assertSame('/api/v1', $spec['servers'][0]['url']);
        self::assertSame('REST API', $spec['servers'][0]['description']);

        self::assertSame('http', $spec['components']['securitySchemes']['bearerAuth']['type']);
        self::assertSame('bearer', $spec['components']['securitySchemes']['bearerAuth']['scheme']);
        self::assertSame('JWT', $spec['components']['securitySchemes']['bearerAuth']['bearerFormat']);
        self::assertSame([['bearerAuth' => []]], $spec['security']);
    }

    public function testIndexDefinesSchemas(): void
    {
        $spec = $this->captureSpec();

        $errorSchema = $spec['components']['schemas']['ErrorResponse'];
        self::assertSame('object', $errorSchema['type']);
        self::assertSame(['error'], $errorSchema['required']);
        self::assertSame('string', $errorSchema['properties']['error']['type']);

        $msgSchema = $spec['components']['schemas']['MessageResponse'];
        self::assertSame('object', $msgSchema['type']);
        self::assertSame(['message'], $msgSchema['required']);
        self::assertSame('string', $msgSchema['properties']['message']['type']);

        $userSchema = $spec['components']['schemas']['User'];
        self::assertSame('object', $userSchema['type']);
        self::assertSame('integer', $userSchema['properties']['id']['type']);
        self::assertSame('string', $userSchema['properties']['username']['type']);
        self::assertSame('string', $userSchema['properties']['email']['type']);
        self::assertSame('email', $userSchema['properties']['email']['format']);
        self::assertSame('integer', $userSchema['properties']['createdAt']['type']);
        self::assertSame(['integer', 'null'], $userSchema['properties']['confirmedAt']['type']);
        self::assertSame(['integer', 'null'], $userSchema['properties']['blockedAt']['type']);

        $createReqSchema = $spec['components']['schemas']['UserCreateRequest'];
        self::assertSame('object', $createReqSchema['type']);
        self::assertSame(['username', 'email'], $createReqSchema['required']);
        self::assertSame('string', $createReqSchema['properties']['username']['type']);
        self::assertSame('string', $createReqSchema['properties']['email']['type']);
        self::assertSame('email', $createReqSchema['properties']['email']['format']);
        self::assertSame('string', $createReqSchema['properties']['password']['type']);
        self::assertSame('Generated if omitted', $createReqSchema['properties']['password']['description']);

        $createRespSchema = $spec['components']['schemas']['UserCreatedResponse'];
        self::assertSame('object', $createRespSchema['type']);
        self::assertSame(['id', 'username', 'email', 'message'], $createRespSchema['required']);
        self::assertSame('integer', $createRespSchema['properties']['id']['type']);
        self::assertSame('string', $createRespSchema['properties']['username']['type']);
        self::assertSame('string', $createRespSchema['properties']['email']['type']);
        self::assertSame('email', $createRespSchema['properties']['email']['format']);
        self::assertSame('string', $createRespSchema['properties']['message']['type']);

        $updateReqSchema = $spec['components']['schemas']['UserUpdateRequest'];
        self::assertSame('object', $updateReqSchema['type']);
        self::assertArrayNotHasKey('required', $updateReqSchema);
        self::assertSame('string', $updateReqSchema['properties']['username']['type']);
        self::assertSame('string', $updateReqSchema['properties']['email']['type']);
        self::assertSame('email', $updateReqSchema['properties']['email']['format']);
        self::assertSame('string', $updateReqSchema['properties']['password']['type']);

        $updateRespSchema = $spec['components']['schemas']['UserUpdatedResponse'];
        self::assertSame('object', $updateRespSchema['type']);
        self::assertSame(['id', 'username', 'email', 'message'], $updateRespSchema['required']);
        self::assertSame('integer', $updateRespSchema['properties']['id']['type']);
        self::assertSame('string', $updateRespSchema['properties']['username']['type']);
        self::assertSame('string', $updateRespSchema['properties']['email']['type']);
        self::assertSame('email', $updateRespSchema['properties']['email']['format']);
        self::assertSame('string', $updateRespSchema['properties']['message']['type']);

        $paginatedSchema = $spec['components']['schemas']['PaginatedUsers'];
        self::assertSame('object', $paginatedSchema['type']);
        self::assertSame(['items', 'totalCount', 'currentPage', 'pageSize', 'totalPages'], $paginatedSchema['required']);
        self::assertSame('array', $paginatedSchema['properties']['items']['type']);
        self::assertSame('#/components/schemas/User', $paginatedSchema['properties']['items']['items']['$ref']);
        self::assertSame('integer', $paginatedSchema['properties']['totalCount']['type']);
        self::assertSame('integer', $paginatedSchema['properties']['currentPage']['type']);
        self::assertSame('integer', $paginatedSchema['properties']['pageSize']['type']);
        self::assertSame('integer', $paginatedSchema['properties']['totalPages']['type']);
    }

    public function testIndexFiltersRoutes(): void
    {
        $unknownMethodRoute = new class {
            public function getData(string $key): mixed
            {
                return match ($key) {
                    'name' => 'voyti/api-v1-custom',
                    'pattern' => 'v1/custom',
                    'methods' => ['CUSTOMMETHOD'],
                    default => null,
                };
            }
        };

        $routeCollection = $this->createMock(RouteCollectionInterface::class);
        $routeCollection->method('getRoutes')->willReturn([
            Route::get('v1/users')->name('voyti/api-v1-users-index'),
            $unknownMethodRoute,
        ]);

        $spec = $this->captureSpec($this->createController($routeCollection));

        self::assertCount(1, $spec['paths']);
        self::assertArrayHasKey('/users', $spec['paths']);
        self::assertArrayNotHasKey('/custom', $spec['paths'], 'Unknown method should skip path');

        $nonStringMethodRoute = new class {
            public function getData(string $key): mixed
            {
                return match ($key) {
                    'name' => 'voyti/api-v1-users-view',
                    'pattern' => 'v1/users/{id:\d+}',
                    'methods' => [123, 'GET'],
                    default => null,
                };
            }
        };

        $routeCollection = $this->createMock(RouteCollectionInterface::class);
        $routeCollection->method('getRoutes')->willReturn([
            Route::get('v1/users')->name('voyti/api-v1-users-index'),
            Route::post('v1/users')->name('voyti/api-v1-users-create'),
            Route::patch('v1/users/{id:\d+}')->name('voyti/api-v1-users-update'),
            Route::delete('v1/users/{id:\d+}')->name('voyti/api-v1-users-delete'),
            Route::get('v1/unknown-endpoint')->name('voyti/api-v1-unknown-endpoint'),
            Route::get('debug/health')->name('voyti/debug-health'),
            $nonStringMethodRoute,
        ]);

        $spec = $this->captureSpec($this->createController($routeCollection));

        self::assertCount(2, $spec['paths'], 'Only known api-v1 paths should be included');
        self::assertArrayHasKey('/users', $spec['paths']);
        self::assertArrayHasKey('/users/{id}', $spec['paths']);
        self::assertArrayNotHasKey('/unknown-endpoint', $spec['paths']);
        self::assertArrayNotHasKey('/debug/health', $spec['paths']);
        self::assertSame('getUser', $spec['paths']['/users/{id}']['get']['operationId']);

        $routeCollection = $this->createMock(RouteCollectionInterface::class);
        $routeCollection->method('getRoutes')->willReturn([
            Route::get('health')->name('voyti/health-check'),
            Route::get('v1/users')->name('voyti/api-v1-users-index'),
            Route::post('v1/users')->name('voyti/api-v1-users-create'),
        ]);

        $spec = $this->captureSpec($this->createController($routeCollection));

        self::assertCount(1, $spec['paths']);
        self::assertArrayHasKey('/users', $spec['paths']);
        self::assertArrayNotHasKey('/health', $spec['paths']);
        self::assertSame(['get', 'post'], array_keys($spec['paths']['/users']));
    }

    private function captureSpec(?OpenApiController $controller = null): array
    {
        $captured = null;
        $response = $this->createMock(ResponseInterface::class);
        $this->responseFactory->method('createResponse')
            ->willReturnCallback(static function (array $data) use (&$captured, $response): ResponseInterface {
                $captured = $data;

                return $response;
            });

        ($controller ?? $this->createController())->index();

        self::assertNotNull($captured, 'createResponse was not called');

        return $captured;
    }

    private function createApiRoutes(): array
    {
        return [
            Route::get('v1/users')->name('voyti/api-v1-users-index'),
            Route::post('v1/users')->name('voyti/api-v1-users-create'),
            Route::get('v1/users/{id:\d+}')->name('voyti/api-v1-users-view'),
            Route::patch('v1/users/{id:\d+}')->name('voyti/api-v1-users-update'),
            Route::delete('v1/users/{id:\d+}')->name('voyti/api-v1-users-delete'),
        ];
    }

    private function createController(?RouteCollectionInterface $routeCollection = null): OpenApiController
    {
        return new OpenApiController(
            responseFactory: $this->responseFactory,
            routeCollection: $routeCollection ?? $this->routeCollection,
            url: $this->url,
        );
    }
}
