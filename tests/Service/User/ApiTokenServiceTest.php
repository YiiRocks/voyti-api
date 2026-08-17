<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Api\tests\Service\User;

use YiiRocks\Voyti\Api\Service\User\ApiTokenService;
use YiiRocks\Voyti\Api\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\Api\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\Model\UserToken;

final class ApiTokenServiceTest extends DatabaseTestCase
{
    use UserFactoryTrait;

    public function testGenerate(): void
    {
        $user = $this->createUser('uniquser', 'uniq@example.com');
        $service = new ApiTokenService();

        $first = $service->generate($user);
        $second = $service->generate($user);

        self::assertNotSame($first, $second);
        self::assertCount(2, UserToken::findByUserId((int) $user->getId()));

        $user = $this->createUser('tokuser', 'tok@example.com');
        $service = new ApiTokenService();

        $rawToken = $service->generate($user);

        self::assertSame(64, strlen($rawToken));
        self::assertNotSame($rawToken, hash('sha256', $rawToken));

        $tokens = UserToken::findByUserId((int) $user->getId());
        self::assertCount(1, $tokens);
        self::assertSame(UserToken::TYPE_API_ACCESS, $tokens[0]->getType());
        self::assertSame(hash('sha256', $rawToken), $tokens[0]->getCode());
        self::assertGreaterThan(0, $tokens[0]->getCreatedAt());
    }

    public function testRevokeAllDeletesOnlyApiTokens(): void
    {
        $user = $this->createUser('revokeuser', 'revoke@example.com');
        $service = new ApiTokenService();

        $service->generate($user);
        $service->generate($user);

        $other = new UserToken();
        $other->setUserId((int) $user->getId());
        $other->setType(UserToken::TYPE_CONFIRMATION);
        $other->setCode(hash('sha256', 'some-code'));
        $other->setCreatedAt(time());
        $other->save();

        $revoked = $service->revokeAll($user);

        self::assertSame(2, $revoked);
        $remaining = UserToken::findByUserId((int) $user->getId());
        self::assertCount(1, $remaining);
        self::assertSame(UserToken::TYPE_CONFIRMATION, $remaining[0]->getType());
    }
}
