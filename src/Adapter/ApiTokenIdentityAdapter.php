<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Api\Adapter;

use Psr\Clock\ClockInterface;
use YiiRocks\Voyti\Adapter\IdentityAdapter;
use YiiRocks\Voyti\Api\ApiConfig;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserToken;
use Yiisoft\Auth\IdentityInterface;

/**
 * Extends core's {@see IdentityAdapter} to enforce API-token expiry ({@see ApiConfig::$apiTokenLifespan})
 * when resolving a bearer token to a {@see User}. An expired token is deleted as it is resolved, so
 * stale hashes don't accumulate. Binds to `IdentityWithTokenRepositoryInterface` in this package's
 * `config/di.php`, overriding core's plain token resolver; core's `IdentityRepositoryInterface`
 * binding (session/remember-me lookups by ID) is left untouched.
 */
final readonly class ApiTokenIdentityAdapter extends IdentityAdapter
{
    public function __construct(
        private ApiConfig $config,
        private ClockInterface $clock,
    ) {}

    public function findIdentityByToken(string $token, ?string $type = null): ?IdentityInterface
    {
        $userToken = UserToken::findByCodeAndType($token, UserToken::TYPE_API_ACCESS);

        if ($userToken === null) {
            return null;
        }

        if (
            $this->config->apiTokenLifespan > 0
            && ($this->clock->now()->getTimestamp() - $userToken->getCreatedAt()) > $this->config->apiTokenLifespan
        ) {
            $userToken->delete();
            return null;
        }

        return $userToken->getUser();
    }
}
