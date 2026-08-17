<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Api;

/**
 * Single source of truth for this package's settings: an immutable value object injected into
 * services instead of raw params.
 */
final readonly class ApiConfig
{
    public function __construct(
        /**
         * How long (in seconds) a generated API access token remains valid. `0` (the default) means
         * tokens never expire.
         */
        public int $apiTokenLifespan,
    ) {}
}
