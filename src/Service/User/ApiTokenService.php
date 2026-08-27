<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Api\Service\User;

use Psr\Clock\ClockInterface;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserToken;
use Yiisoft\Security\Random;

/**
 * Issues and revokes API bearer tokens ({@see UserToken::TYPE_API_ACCESS}) for a user; only the
 * SHA-256 hash of the raw token is persisted.
 */
final readonly class ApiTokenService
{
    public function __construct(
        private ClockInterface $clock,
    ) {}

    public function generate(User $user): string
    {
        $rawToken = Random::string(64);

        $userToken = new UserToken();
        $userToken->setUserId((int) $user->getId());
        $userToken->setType(UserToken::TYPE_API_ACCESS);
        $userToken->setCode(hash('sha256', $rawToken));
        $userToken->setCreatedAt($this->clock->now()->getTimestamp());
        $userToken->save();

        return $rawToken;
    }

    public function revokeAll(User $user): int
    {
        return UserToken::deleteAllByUserIdAndType((int) $user->getId(), UserToken::TYPE_API_ACCESS);
    }
}
