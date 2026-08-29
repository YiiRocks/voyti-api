<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Api\OpenApi;

/**
 * Implemented by resource packages (e.g. yiirocks/voyti-api-user) and collected via the
 * `voyti-api.openapi-contributor` DI tag, so {@see OpenApiSpecBuilder} can assemble one merged
 * openapi.json spec from whatever resource packages are installed.
 */
interface OpenApiSpecContributorInterface
{
    /**
     * The OpenAPI operation object for a given route name + HTTP method, or null if this contributor
     * doesn't recognize the route.
     *
     * @return array<string, mixed>|null
     */
    public function getMethodSpec(string $routeName, string $method): ?array;

    /**
     * Schema objects to merge into `components.schemas`, keyed by schema name.
     *
     * @return array<string, array<string, mixed>>
     */
    public function schemas(): array;
}
