<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Api\Middleware;

use Composer\InstalledVersions;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use YiiRocks\Voyti\Api\ApiConfig;
use Yiisoft\Files\FileHelper;
use Yiisoft\Http\Header;
use Yiisoft\I18n\Locale;
use Yiisoft\I18n\LocaleProvider;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Resolves the request's locale from `Accept-Language` and applies it to the shared
 * {@see TranslatorInterface} and {@see LocaleProvider} before the route handler runs. Registered on
 * the outermost route group so public routes get it too.
 *
 * Supported locales come from `yiirocks/voyti`'s installed message catalogs.
 */
final readonly class LocaleMiddleware implements MiddlewareInterface
{
    /** @var list<string> */
    private array $supportedLocales;

    public function __construct(
        private ApiConfig $config,
        private TranslatorInterface $translator,
        private LocaleProvider $localeProvider,
    ) {
        $installPath = InstalledVersions::getInstallPath('yiirocks/voyti');

        /** @var list<string> $directories */
        $directories = FileHelper::findDirectories("$installPath/resources/messages");

        $this->supportedLocales = array_map(basename(...), $directories);
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $locale = $this->resolveLocale($request->getHeaderLine(Header::ACCEPT_LANGUAGE));

        $this->translator->setLocale($locale);
        $this->localeProvider->set(new Locale($locale));

        return $handler->handle($request);
    }

    private function matchSupportedLocale(string $candidate): ?string
    {
        try {
            $language = (new Locale($candidate))->language();
        } catch (InvalidArgumentException) {
            return null;
        }

        if ($language === null) {
            return null;
        }

        // $supportedLocale is always a catalog directory name, already a bare language code.
        foreach ($this->supportedLocales as $supportedLocale) {
            if (strcasecmp($supportedLocale, $language) === 0) {
                return $supportedLocale;
            }
        }

        return null;
    }

    /**
     * @return list<string> Candidate tags, highest `q` first. A malformed `q` value is treated as
     * lowest priority rather than rejected, so garbage from a client doesn't break resolution entirely.
     */
    private function parseCandidates(string $acceptLanguage): array
    {
        $weighted = [];
        foreach (explode(',', $acceptLanguage) as $part) {
            [$tag, $quality] = explode(';q=', $part, 2) + [null, null];
            $tag = trim($tag);

            if ($tag === '' || $tag === '*') {
                continue;
            }

            $weighted[] = [$tag, $quality === null ? 1.0 : (is_numeric($quality) ? $quality : 0.0)];
        }

        usort($weighted, static fn(array $a, array $b): int => $b[1] <=> $a[1]);

        return array_column($weighted, 0);
    }

    private function resolveLocale(string $acceptLanguage): string
    {
        foreach ($this->parseCandidates($acceptLanguage) as $candidate) {
            $match = $this->matchSupportedLocale($candidate);
            if ($match !== null) {
                return $match;
            }
        }

        return $this->config->defaultLocale;
    }
}
