<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Api\tests\Middleware;

use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use YiiRocks\Voyti\Api\Middleware\ApiTokenAuthenticationMiddleware;
use YiiRocks\Voyti\Api\tests\Support\CurrentUserTrait;
use YiiRocks\Voyti\Model\User;
use Yiisoft\Auth\IdentityWithTokenRepositoryInterface;
use Yiisoft\User\CurrentUser;

#[AllowMockObjectsWithoutExpectations]
final class ApiTokenAuthenticationMiddlewareTest extends TestCase
{
    use CurrentUserTrait;

    public static function processProvider(): iterable
    {
        yield 'valid token overrides identity and delegates' => ['valid-token', true];
        yield 'invalid token challenges with 401' => ['invalid-token', false];
    }

    #[DataProvider('processProvider')]
    public function testProcess(string $token, bool $valid): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::once())->method('getHeader')->with('Authorization')->willReturn(['Bearer ' . $token]);

        $identity = $valid ? new User() : null;
        $identityRepository = $this->createMock(IdentityWithTokenRepositoryInterface::class);
        $identityRepository->expects(self::once())
            ->method('findIdentityByToken')
            ->with($token, null)
            ->willReturn($identity);

        $currentUser = $this->createCurrentUser();
        $handler = $this->createMock(RequestHandlerInterface::class);

        if ($valid) {
            $response = $this->createMock(ResponseInterface::class);
            $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

            $middleware = $this->createMiddleware(
                identityRepository: $identityRepository,
                currentUser: $currentUser,
            );

            $result = $middleware->process($request, $handler);

            self::assertSame($response, $result);
            self::assertSame($identity, $currentUser->getIdentity());
        } else {
            $handler->expects(self::never())->method('handle');

            $responseFactory = $this->createMock(ResponseFactoryInterface::class);
            $responseFactory->expects(self::once())->method('createResponse')->with(401)->willReturn(new Response(401));

            $middleware = $this->createMiddleware(
                identityRepository: $identityRepository,
                responseFactory: $responseFactory,
                currentUser: $currentUser,
            );

            $result = $middleware->process($request, $handler);

            self::assertSame(401, $result->getStatusCode());
            self::assertStringContainsString(
                'Bearer realm="api"',
                (string) ($result->getHeader('WWW-Authenticate')[0] ?? ''),
            );
            self::assertTrue($currentUser->isGuest());
        }
    }

    private function createMiddleware(
        ?IdentityWithTokenRepositoryInterface $identityRepository = null,
        ?ResponseFactoryInterface $responseFactory = null,
        ?CurrentUser $currentUser = null,
    ): ApiTokenAuthenticationMiddleware {
        return new ApiTokenAuthenticationMiddleware(
            $identityRepository ?? $this->createMock(IdentityWithTokenRepositoryInterface::class),
            $responseFactory ?? $this->createMock(ResponseFactoryInterface::class),
            $currentUser ?? $this->createCurrentUser(),
        );
    }
}
