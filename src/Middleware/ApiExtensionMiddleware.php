<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Api\Middleware;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Chains every middleware tagged `voyti-api.extension-middleware` in front of an authenticated route
 * group, in tag order, delegating straight to $handler when nothing is tagged. Deliberately not scoped
 * to `v1/` by name: wire this same middleware into every versioned route group (v1 today, v2+ later)
 * that should honor installed extension packages, so an extension package tags itself once and covers
 * every current and future API version, rather than needing a per-version tag. Lets an installed
 * extension package (e.g. rate limiting) join the chain with no host wiring, and without voyti-api ever
 * needing to know the extension's class name. Mirrors core's
 * `\YiiRocks\Voyti\Middleware\VoytiMiddleware` enforcement-chain pattern.
 */
final readonly class ApiExtensionMiddleware implements MiddlewareInterface
{
    /**
     * @param iterable<MiddlewareInterface> $extensionMiddlewares
     */
    public function __construct(
        private iterable $extensionMiddlewares,
    ) {}

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $middlewares = [];
        foreach ($this->extensionMiddlewares as $extensionMiddleware) {
            $middlewares[] = $extensionMiddleware;
        }

        $handler = array_reduce(
            array_reverse($middlewares),
            static fn(
                RequestHandlerInterface $next,
                MiddlewareInterface $middleware,
            ): RequestHandlerInterface => new class ($middleware, $next) implements RequestHandlerInterface {
                public function __construct(
                    private MiddlewareInterface $middleware,
                    private RequestHandlerInterface $next,
                ) {}

                #[Override]
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->middleware->process($request, $this->next);
                }
            },
            $handler,
        );

        return $handler->handle($request);
    }
}
