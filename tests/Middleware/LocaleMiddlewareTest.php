<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Api\tests\Middleware;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use YiiRocks\Voyti\Api\ApiConfig;
use YiiRocks\Voyti\Api\Middleware\LocaleMiddleware;
use Yiisoft\Http\Header;
use Yiisoft\I18n\Locale;
use Yiisoft\I18n\LocaleProvider;
use Yiisoft\Translator\Translator;

final class LocaleMiddlewareTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string, 1: list<string>, 2: string}>
     */
    public static function resolveLocaleProvider(): iterable
    {
        yield 'falls back to default when header is missing' => ['de', [], 'de'];
        yield 'falls back to default when no candidate is supported' => ['en', ['ja', 'ko'], 'en'];
        yield 'blank and wildcard candidates are ignored' => ['en', ['', '*;q=0.1', 'de'], 'de'];

        // "12345" isn't a valid BCP 47 tag - Locale() throws, unlike a tag that just doesn't match.
        yield 'candidate that fails BCP 47 parsing is skipped' => ['en', ['12345', 'de'], 'de'];

        // "i-klingon" is a valid grandfathered tag but has no language subtag to match against.
        yield 'grandfathered tag with no language subtag is skipped' => ['en', ['i-klingon', 'de'], 'de'];

        yield 'malformed accept-language does not crash' => ['en', ['not-a-locale;q=abc', 'de'], 'de'];

        // en has implicit q=1, beating fr's explicit q=0.8.
        yield 'picks highest quality candidate' => ['en', ['fr;q=0.8', 'en'], 'en'];

        // Only the first ";q=" is the delimiter - "0.9;q=abc" is the whole (non-numeric) quality value.
        yield 'quality with embedded delimiter is treated as malformed' => [
            'ru', ['fr;q=0.9;q=abc', 'de;q=0.5'], 'de',
        ];

        yield 'resolves exact match from accept-language' => ['en', ['de-DE;q=0.9', 'fr;q=0.5'], 'de'];

        // The space between "de" and ";q=" would make Locale() reject the tag if left untrimmed.
        yield 'tag with trailing space before delimiter is trimmed' => ['ru', ['de ;q=0.9', 'en;q=0.1'], 'de'];
    }

    /**
     * @param list<string> $acceptLanguageValues
     */
    #[DataProvider('resolveLocaleProvider')]
    public function testResolveLocale(string $defaultLocale, array $acceptLanguageValues, string $expected): void
    {
        [$translator, $localeProvider, $middleware] = $this->createMiddleware($defaultLocale);

        $this->process($middleware, $acceptLanguageValues);

        self::assertSame($expected, $translator->getLocale());
        self::assertSame($expected, $localeProvider->get()->asString());
    }

    /**
     * @return array{0: Translator, 1: LocaleProvider, 2: LocaleMiddleware}
     */
    private function createMiddleware(string $defaultLocale): array
    {
        $translator = new Translator($defaultLocale);
        $localeProvider = new LocaleProvider(new Locale($defaultLocale));
        $config = new ApiConfig(apiTokenLifespan: 0, defaultLocale: $defaultLocale);
        $middleware = new LocaleMiddleware($config, $translator, $localeProvider);

        return [$translator, $localeProvider, $middleware];
    }

    /**
     * @param list<string> $acceptLanguageValues
     */
    private function process(LocaleMiddleware $middleware, array $acceptLanguageValues): ResponseInterface
    {
        $request = new ServerRequest('GET', '/');
        if ($acceptLanguageValues !== []) {
            $request = $request->withHeader(Header::ACCEPT_LANGUAGE, implode(', ', $acceptLanguageValues));
        }

        $response = $this->createStub(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())
            ->method('handle')
            ->with(self::isInstanceOf(ServerRequestInterface::class))
            ->willReturn($response);

        return $middleware->process($request, $handler);
    }
}
