<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Api\tests\Middleware;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use YiiRocks\Voyti\Api\Middleware\AccessRuleMiddleware;
use YiiRocks\Voyti\Api\tests\Support\CurrentUserTrait;
use YiiRocks\Voyti\Api\tests\Support\SimpleAssignmentsStorage;
use YiiRocks\Voyti\Api\tests\Support\SimpleItemsStorage;
use YiiRocks\Voyti\Api\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\Helper\AuthHelper;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Rbac\Manager;
use Yiisoft\Rbac\Permission;
use Yiisoft\Rbac\Role;
use Yiisoft\User\CurrentUser;

#[AllowMockObjectsWithoutExpectations]
final class AccessRuleMiddlewareTest extends TestCase
{
    use CurrentUserTrait;

    /**
     * [user id, admin user id, expected status]. A null status means the request passes through.
     */
    public static function processProvider(): iterable
    {
        yield 'admin passes through' => ['1', '1', null];
        yield 'guest gets 401' => [null, null, 401];
        yield 'non-admin gets 403' => ['42', null, 403];
    }

    #[DataProvider('processProvider')]
    public function testProcess(?string $userId, ?string $adminUserId, ?int $expectedStatus): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        if ($expectedStatus === null) {
            $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);
        } else {
            $handler->expects(self::never())->method('handle');
        }

        $identity = null;
        if ($userId !== null) {
            $identity = $this->createMock(IdentityInterface::class);
            $identity->method('getId')->willReturn($userId);
        }

        $middleware = $this->createMiddleware(
            currentUser: $this->createCurrentUser($identity),
            authHelper: $this->createAuthHelper(adminUserId: $adminUserId),
        );

        $result = $middleware->process($request, $handler);

        if ($expectedStatus === null) {
            self::assertSame($response, $result);
        } else {
            self::assertSame($expectedStatus, $result->getStatusCode());
            self::assertSame('application/json', $result->getHeaderLine('Content-Type'));

            /** @var array{error: string} $body */
            $body = json_decode((string) $result->getBody(), true);
            $expectedBody = $expectedStatus === 401
                ? ['error' => 'Authentication required']
                : ['error' => 'Forbidden'];
            self::assertSame($expectedBody, $body);
        }
    }

    private function createAuthHelper(?string $adminUserId = null): AuthHelper
    {
        $config = VoytiConfigFactory::create();
        $itemsStorage = new SimpleItemsStorage();
        $assignmentsStorage = new SimpleAssignmentsStorage();
        $manager = new Manager($itemsStorage, $assignmentsStorage);

        if ($adminUserId !== null) {
            $itemsStorage->add(new Permission($config->administratorPermissionName));
            $itemsStorage->add(new Role('admin'));
            $manager->addChild('admin', $config->administratorPermissionName);
            $manager->assign('admin', $adminUserId);
        }

        return new AuthHelper($manager, $itemsStorage, $assignmentsStorage, $config, $this->createCurrentUser());
    }

    private function createMiddleware(
        ?CurrentUser $currentUser = null,
        ?AuthHelper $authHelper = null,
        ?ResponseFactoryInterface $responseFactory = null,
    ): AccessRuleMiddleware {
        return new AccessRuleMiddleware(
            $currentUser ?? $this->createCurrentUser(),
            $authHelper ?? $this->createAuthHelper(),
            $responseFactory ?? new Psr17Factory(),
        );
    }
}
