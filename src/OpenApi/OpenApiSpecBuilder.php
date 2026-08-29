<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Api\OpenApi;

use Yiisoft\Router\RouteCollectionInterface;

/**
 * Builds the merged openapi.json spec from every installed resource package's
 * {@see OpenApiSpecContributorInterface}, tagged `voyti-api.openapi-contributor`. Generic and
 * version-agnostic: it walks every registered route and asks each contributor whether it recognizes
 * that route's name, so it has no built-in knowledge of any specific resource or API version.
 */
final readonly class OpenApiSpecBuilder
{
    /**
     * @param iterable<OpenApiSpecContributorInterface> $contributors
     */
    public function __construct(
        private RouteCollectionInterface $routeCollection,
        private iterable $contributors,
    ) {}

    public function buildSpec(string $serverUrl): array
    {
        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'Voyti API',
                'version' => '1.0.0',
                'description' => 'REST API for the Voyti Yii3 user-management extension.',
            ],
            'servers' => [
                ['url' => $serverUrl, 'description' => 'REST API'],
            ],
            'paths' => $this->buildPathsFromRoutes(),
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
        $schemas = [
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
        ];

        foreach ($this->contributors as $contributor) {
            $schemas = array_merge($schemas, $contributor->schemas());
        }

        return [
            'schemas' => $schemas,
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
            $pathSpec = $this->buildPathSpec(
                $route->getData('pattern'),
                $route->getData('methods'),
                $route->getData('name'),
            );

            if ($pathSpec === null) {
                continue;
            }

            [$path, $methodSpec] = $pathSpec;

            $paths[$path] ??= [];

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
            $spec = $this->getMethodSpec($name, $methodLower);

            if ($spec !== null) {
                $specs[$methodLower] = $spec;
            }
        }

        return $specs !== [] ? [$path, $specs] : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getMethodSpec(string $name, string $method): ?array
    {
        foreach ($this->contributors as $contributor) {
            $spec = $contributor->getMethodSpec($name, $method);
            if ($spec !== null) {
                return $spec;
            }
        }

        return null;
    }

    private function patternToOpenApiPath(string $pattern): string
    {
        $path = preg_replace('/\{(\w+):[^}]+\}/', '{$1}', $pattern) ?? $pattern;
        /** @infection-ignore-all ltrim is necessary to normalize patterns that might have leading slashes */
        $path = ltrim($path, '/');
        $path = preg_replace('#^v\d+/#', '', $path) ?? $path;

        return '/' . $path;
    }
}
