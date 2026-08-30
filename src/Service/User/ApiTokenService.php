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

    /**
     * @return list<UserToken> Identified only by their stored hash - the raw token is never
     * retrievable after {@see self::generate()} returns it once.
     */
    public function listActive(User $user): array
    {
        return array_values(array_filter(
            UserToken::findByUserId((int) $user->getId()),
            static fn(UserToken $token): bool => $token->getType() === UserToken::TYPE_API_ACCESS,
        ));
    }

    public function revokeAll(User $user): int
    {
        return UserToken::deleteAllByUserIdAndType((int) $user->getId(), UserToken::TYPE_API_ACCESS);
    }

    public function revokeByHash(User $user, string $hash): bool
    {
        /** @var ?UserToken $userToken */
        $userToken = UserToken::query()
            ->where(['user_id' => $user->getId(), 'code' => $hash, 'type' => UserToken::TYPE_API_ACCESS])
            ->one();

        if ($userToken === null) {
            return false;
        }

        $userToken->delete();

        return true;
    }

    public function revokeCurrent(User $user, string $rawToken): bool
    {
        $userToken = UserToken::findByUserIdAndCodeAndType((int) $user->getId(), $rawToken, UserToken::TYPE_API_ACCESS);

        if ($userToken === null) {
            return false;
        }

        $userToken->delete();

        return true;
    }
}
