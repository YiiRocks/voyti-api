<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Api\tests\Service\User;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use YiiRocks\Voyti\Api\Service\User\ApiTokenService;
use YiiRocks\Voyti\Api\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\Api\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\Clock\SystemClock;
use YiiRocks\Voyti\Model\UserToken;

final class ApiTokenServiceTest extends DatabaseTestCase
{
    use UserFactoryTrait;

    public function testGenerate(): void
    {
        $user = $this->createUser('uniquser', 'uniq@example.com');
        $service = new ApiTokenService(new SystemClock());

        $first = $service->generate($user);
        $second = $service->generate($user);

        self::assertNotSame($first, $second);
        self::assertCount(2, UserToken::findByUserId((int) $user->getId()));

        $user = $this->createUser('tokuser', 'tok@example.com');
        $now = new class implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('@1750000000');
            }
        };
        $service = new ApiTokenService($now);

        $rawToken = $service->generate($user);

        self::assertSame(64, strlen($rawToken));
        self::assertNotSame($rawToken, hash('sha256', $rawToken));

        $tokens = UserToken::findByUserId((int) $user->getId());
        self::assertCount(1, $tokens);
        self::assertSame(UserToken::TYPE_API_ACCESS, $tokens[0]->getType());
        self::assertSame(hash('sha256', $rawToken), $tokens[0]->getCode());
        self::assertSame(1750000000, $tokens[0]->getCreatedAt());
    }

    public function testListActiveReturnsOnlyApiTokens(): void
    {
        $user = $this->createUser('listuser', 'list@example.com');
        $service = new ApiTokenService(new SystemClock());

        // Non-API token created first, so a broken re-indexing (e.g. dropping array_values) would
        // leave the filtered result keyed [1, 2] instead of [0, 1].
        $other = new UserToken();
        $other->setUserId((int) $user->getId());
        $other->setType(UserToken::TYPE_CONFIRMATION);
        $other->setCode(hash('sha256', 'some-code'));
        $other->setCreatedAt(time());
        $other->save();

        $service->generate($user);
        $service->generate($user);

        $active = $service->listActive($user);

        self::assertSame([0, 1], array_keys($active));
        foreach ($active as $token) {
            self::assertSame(UserToken::TYPE_API_ACCESS, $token->getType());
        }
    }

    public function testRevokeAllDeletesOnlyApiTokens(): void
    {
        $user = $this->createUser('revokeuser', 'revoke@example.com');
        $service = new ApiTokenService(new SystemClock());

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

    public function testRevokeByHash(): void
    {
        $user = $this->createUser('revokehashuser', 'revokehash@example.com');
        $otherUser = $this->createUser('otheruser', 'other@example.com');
        $service = new ApiTokenService(new SystemClock());

        $rawToken = $service->generate($user);
        $service->generate($user);
        $hash = hash('sha256', $rawToken);

        // Same hash value under a different user must not be reachable by this call.
        $otherToken = new UserToken();
        $otherToken->setUserId((int) $otherUser->getId());
        $otherToken->setType(UserToken::TYPE_API_ACCESS);
        $otherToken->setCode($hash);
        $otherToken->setCreatedAt(time());
        $otherToken->save();

        self::assertFalse($service->revokeByHash($user, 'not-a-real-hash'));
        self::assertTrue($service->revokeByHash($user, $hash));
        self::assertFalse($service->revokeByHash($user, $hash));
        self::assertNotNull(UserToken::findByUserIdAndCodeAndType((int) $otherUser->getId(), $rawToken, UserToken::TYPE_API_ACCESS));

        $remaining = UserToken::findByUserId((int) $user->getId());
        self::assertCount(1, $remaining);
    }

    public function testRevokeCurrent(): void
    {
        $user = $this->createUser('revokecurrentuser', 'revokecurrent@example.com');
        $service = new ApiTokenService(new SystemClock());

        $rawToken = $service->generate($user);
        $service->generate($user);

        self::assertTrue($service->revokeCurrent($user, $rawToken));
        self::assertFalse($service->revokeCurrent($user, $rawToken));

        $remaining = UserToken::findByUserId((int) $user->getId());
        self::assertCount(1, $remaining);
    }
}
