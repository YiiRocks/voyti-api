<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Api\Middleware;

use Override;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use YiiRocks\Voyti\Helper\AuthHelper;
use Yiisoft\Http\Header;
use Yiisoft\Http\Status;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;

/**
 * Guards the `voyti-routes-api` user endpoints: only identities holding the configured administrator
 * permission pass through. Unlike core's web {@see \YiiRocks\Voyti\Middleware\AccessRuleMiddleware},
 * there is no session to redirect to, so both guests and non-admins get an API-native JSON error.
 */
final readonly class AccessRuleMiddleware implements MiddlewareInterface
{
    public function __construct(
        private CurrentUser $currentUser,
        private AuthHelper $authHelper,
        private ResponseFactoryInterface $responseFactory,
    ) {}

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $this->currentUser->getIdentity();

        if ($user instanceof GuestIdentityInterface) {
            return $this->jsonError($this->responseFactory->createResponse(Status::UNAUTHORIZED), 'Authentication required');
        }

        $userId = $user->getId();
        if (!$this->authHelper->isAdmin($userId)) {
            return $this->jsonError($this->responseFactory->createResponse(Status::FORBIDDEN), 'Forbidden');
        }

        return $handler->handle($request);
    }

    private function jsonError(ResponseInterface $response, string $message): ResponseInterface
    {
        $response->getBody()->write(json_encode(['error' => $message], JSON_THROW_ON_ERROR));

        return $response->withHeader(Header::CONTENT_TYPE, 'application/json');
    }
}
