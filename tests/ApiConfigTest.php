<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Api\tests;

use YiiRocks\Voyti\Api\ApiConfig;

final class ApiConfigTest extends TestCase
{
    public function testCarriesApiTokenLifespan(): void
    {
        $config = new ApiConfig(apiTokenLifespan: 3600, defaultLocale: 'en');

        self::assertSame(3600, $config->apiTokenLifespan);

        $config = new ApiConfig(apiTokenLifespan: 0, defaultLocale: 'en');

        self::assertSame(0, $config->apiTokenLifespan);
    }

    public function testCarriesDefaultLocale(): void
    {
        $config = new ApiConfig(apiTokenLifespan: 0, defaultLocale: 'de');

        self::assertSame('de', $config->defaultLocale);
    }
}
