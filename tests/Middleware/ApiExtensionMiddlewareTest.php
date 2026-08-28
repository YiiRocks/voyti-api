<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Api\tests\Middleware;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use YiiRocks\Voyti\Api\Middleware\ApiExtensionMiddleware;

#[AllowMockObjectsWithoutExpectations]
final class ApiExtensionMiddlewareTest extends TestCase
{
    public function testProcess(): void
    {
        // Nothing tagged: delegates straight to the handler.
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $middleware = new ApiExtensionMiddleware([]);

        self::assertSame($response, $middleware->process($request, $handler));

        // Tagged middlewares are chained in order, then the handler runs last.
        $chainedRequest = $this->createMock(ServerRequestInterface::class);
        $chainedResponse = $this->createMock(ResponseInterface::class);
        $chainedHandler = $this->createMock(RequestHandlerInterface::class);
        $chainedHandler->method('handle')->willReturn($chainedResponse);

        $log = [];
        $chainedMiddleware = new ApiExtensionMiddleware([
            $this->createRecordingMiddleware('rateLimit', $log),
            $this->createRecordingMiddleware('other', $log),
        ]);

        self::assertSame($chainedResponse, $chainedMiddleware->process($chainedRequest, $chainedHandler));
        self::assertSame(['rateLimit', 'other'], $log);

        // A tagged middleware returning early short-circuits the chain: the handler and any later
        // tagged middleware must never run.
        $rejectedRequest = $this->createMock(ServerRequestInterface::class);
        $tooManyRequests = $this->createMock(ResponseInterface::class);
        $neverRunHandler = $this->createMock(RequestHandlerInterface::class);
        $neverRunHandler->expects(self::never())->method('handle');

        $shortCircuitLog = [];
        $shortCircuitMiddleware = new ApiExtensionMiddleware([
            $this->createRejectingMiddleware($tooManyRequests),
            $this->createRecordingMiddleware('neverRun', $shortCircuitLog),
        ]);

        self::assertSame(
            $tooManyRequests,
            $shortCircuitMiddleware->process($rejectedRequest, $neverRunHandler),
        );
        self::assertSame([], $shortCircuitLog);
    }

    private function createRecordingMiddleware(string $label, array &$log): MiddlewareInterface
    {
        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->method('process')->willReturnCallback(
            static function (ServerRequestInterface $request, RequestHandlerInterface $handler) use ($label, &$log): ResponseInterface {
                $log[] = $label;

                return $handler->handle($request);
            },
        );

        return $middleware;
    }

    private function createRejectingMiddleware(ResponseInterface $response): MiddlewareInterface
    {
        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->method('process')->willReturn($response);

        return $middleware;
    }
}
