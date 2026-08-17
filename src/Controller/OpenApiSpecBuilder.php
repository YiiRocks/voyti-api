<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Api\Controller;

use Yiisoft\Router\RouteCollectionInterface;

final readonly class OpenApiSpecBuilder
{
    public function __construct(
        private RouteCollectionInterface $routeCollection,
    ) {}

    public function buildSpec(string $serverUrl): array
    {
        $paths = $this->buildPathsFromRoutes();

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'User Management API',
                'version' => '1.0.0',
                'description' => 'User management, authentication, and authorization REST API.',
            ],
            'servers' => [
                ['url' => $serverUrl, 'description' => 'REST API'],
            ],
            'paths' => $paths,
            'components' => $this->buildComponents(),
            'security' => [
                ['bearerAuth' => []],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function buildComponents(): array
    {
        return [
            'schemas' => [
                'User' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'username' => ['type' => 'string'],
                        'email' => ['type' => 'string', 'format' => 'email'],
                        'createdAt' => ['type' => 'integer'],
                        'confirmedAt' => ['type' => ['integer', 'null']],
                        'blockedAt' => ['type' => ['integer', 'null']],
                    ],
                ],
                'UserCreateRequest' => [
                    'type' => 'object',
                    'required' => ['username', 'email'],
                    'properties' => [
                        'username' => ['type' => 'string'],
                        'email' => ['type' => 'string', 'format' => 'email'],
                        'password' => ['type' => 'string', 'description' => 'Generated if omitted'],
                    ],
                ],
                'UserUpdateRequest' => [
                    'type' => 'object',
                    'properties' => [
                        'username' => ['type' => 'string'],
                        'email' => ['type' => 'string', 'format' => 'email'],
                        'password' => ['type' => 'string'],
                    ],
                ],
                'ErrorResponse' => [
                    'type' => 'object',
                    'required' => ['error'],
                    'properties' => [
                        'error' => ['type' => 'string'],
                    ],
                ],
                'MessageResponse' => [
                    'type' => 'object',
                    'required' => ['message'],
                    'properties' => [
                        'message' => ['type' => 'string'],
                    ],
                ],
                'UserCreatedResponse' => [
                    'type' => 'object',
                    'required' => ['id', 'username', 'email', 'message'],
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'username' => ['type' => 'string'],
                        'email' => ['type' => 'string', 'format' => 'email'],
                        'message' => ['type' => 'string'],
                    ],
                ],
                'UserUpdatedResponse' => [
                    'type' => 'object',
                    'required' => ['id', 'username', 'email', 'message'],
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'username' => ['type' => 'string'],
                        'email' => ['type' => 'string', 'format' => 'email'],
                        'message' => ['type' => 'string'],
                    ],
                ],
                'PaginatedUsers' => [
                    'type' => 'object',
                    'required' => ['items', 'totalCount', 'currentPage', 'pageSize', 'totalPages'],
                    'properties' => [
                        'items' => [
                            'type' => 'array',
                            'items' => ['$ref' => '#/components/schemas/User'],
                        ],
                        'totalCount' => ['type' => 'integer'],
                        'currentPage' => ['type' => 'integer'],
                        'pageSize' => ['type' => 'integer'],
                        'totalPages' => ['type' => 'integer'],
                    ],
                ],
            ],
            'securitySchemes' => [
                'bearerAuth' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'JWT',
                ],
            ],
        ];
    }

    private function buildPathsFromRoutes(): array
    {
        /** @var array<string, array<string, array<string, mixed>>> $paths */
        $paths = [];

        foreach ($this->routeCollection->getRoutes() as $route) {
            $name = $route->getData('name');
            if (!str_starts_with($name, 'voyti/api-v1-')) {
                continue;
            }

            $pattern = $route->getData('pattern');
            $methods = $route->getData('methods');

            $pathSpec = $this->buildPathSpec($pattern, $methods, $name);

            if ($pathSpec === null) {
                continue;
            }

            [$path, $methodSpec] = $pathSpec;

            if (!isset($paths[$path])) {
                $paths[$path] = [];
            }

            $paths[$path] = array_merge($paths[$path], $methodSpec);
        }

        return $paths;
    }

    /**
     * @param array<mixed, mixed> $methods
     * @return array{string, array<string, array<string, mixed>>}|null
     */
    private function buildPathSpec(string $pattern, array $methods, string $name): ?array
    {
        $path = $this->patternToOpenApiPath($pattern);

        /** @var array<string, array<string, mixed>> $specs */
        $specs = [];
        foreach ($methods as $method) {
            if (!is_string($method)) {
                continue;
            }

            $methodLower = strtolower($method);
            $spec = $this->getMethodSpec($methodLower, $name);

            if ($spec !== null) {
                $specs[$methodLower] = $spec;
            }
        }

        return $specs !== [] ? [$path, $specs] : null;
    }

    /**
     * @return array<string, mixed>|null
     * @infection-ignore-all default case is unreachable: only known routes are processed, unknown routes return null for all methods
     */
    private function getMethodSpec(string $method, string $name): ?array
    {
        return match ($name) {
            'voyti/api-v1-users-index' => $method === 'get' ? $this->specListUsers() : null,
            'voyti/api-v1-users-view' => $method === 'get' ? $this->specGetUser() : null,
            'voyti/api-v1-users-create' => $method === 'post' ? $this->specCreateUser() : null,
            'voyti/api-v1-users-update' => $method === 'patch' ? $this->specUpdateUser() : null,
            'voyti/api-v1-users-delete' => $method === 'delete' ? $this->specDeleteUser() : null,
            default => null,
        };
    }

    private function patternToOpenApiPath(string $pattern): string
    {
        $path = preg_replace('/\{(\w+):[^}]+\}/', '{$1}', $pattern) ?? $pattern;
        /** @infection-ignore-all ltrim is necessary to normalize patterns that might have leading slashes */
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'v1/')) {
            $path = substr($path, 3);
        }

        return '/' . $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function specCreateUser(): array
    {
        return [
            'operationId' => 'createUser',
            'summary' => 'Create a user',
            'tags' => ['Users'],
            'requestBody' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/UserCreateRequest'],
                    ],
                ],
            ],
            'responses' => [
                '201' => [
                    'description' => 'User created',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/UserCreatedResponse'],
                        ],
                    ],
                ],
                '400' => [
                    'description' => 'Validation error',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function specDeleteUser(): array
    {
        return [
            'operationId' => 'deleteUser',
            'summary' => 'Delete a user',
            'tags' => ['Users'],
            'parameters' => [
                ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
            ],
            'responses' => [
                '200' => [
                    'description' => 'User deleted',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/MessageResponse'],
                        ],
                    ],
                ],
                '404' => [
                    'description' => 'User not found',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function specGetUser(): array
    {
        return [
            'operationId' => 'getUser',
            'summary' => 'Get a user by ID',
            'tags' => ['Users'],
            'parameters' => [
                ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
            ],
            'responses' => [
                '200' => [
                    'description' => 'User details',
                    'content' => [
                        'application/json' => ['schema' => ['$ref' => '#/components/schemas/User']],
                    ],
                ],
                '404' => [
                    'description' => 'User not found',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function specListUsers(): array
    {
        return [
            'operationId' => 'listUsers',
            'summary' => 'List users',
            'tags' => ['Users'],
            'parameters' => [
                ['name' => 'username', 'in' => 'query', 'schema' => ['type' => 'string']],
                ['name' => 'email', 'in' => 'query', 'schema' => ['type' => 'string']],
                ['name' => 'status', 'in' => 'query', 'schema' => ['type' => 'string']],
                ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 1]],
                [
                    'name' => 'perPage',
                    'in' => 'query',
                    'schema' => ['type' => 'integer', 'default' => 25, 'maximum' => 100],
                ],
            ],
            'responses' => [
                '200' => [
                    'description' => 'Paginated list of users',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/PaginatedUsers'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function specUpdateUser(): array
    {
        return [
            'operationId' => 'updateUser',
            'summary' => 'Update a user',
            'tags' => ['Users'],
            'parameters' => [
                ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
            ],
            'requestBody' => [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/UserUpdateRequest'],
                    ],
                ],
            ],
            'responses' => [
                '200' => [
                    'description' => 'User updated',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/UserUpdatedResponse'],
                        ],
                    ],
                ],
                '400' => [
                    'description' => 'Validation error',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
                        ],
                    ],
                ],
                '404' => [
                    'description' => 'User not found',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
