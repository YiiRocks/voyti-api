<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Api\tests\Adapter;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Clock\ClockInterface;
use YiiRocks\Voyti\Api\Adapter\ApiTokenIdentityAdapter;
use YiiRocks\Voyti\Api\ApiConfig;
use YiiRocks\Voyti\Api\Service\User\ApiTokenService;
use YiiRocks\Voyti\Api\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\Api\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\Clock\SystemClock;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Model\UserToken;

final class ApiTokenIdentityAdapterTest extends DatabaseTestCase
{
    use UserFactoryTrait;

    /**
     * [lifespan, token age in seconds, expected found]. A null age means no token exists.
     */
    public static function findIdentityByTokenProvider(): iterable
    {
        yield 'fresh token' => [0, 0, true];
        yield 'token within lifespan' => [3600, 60, true];
        yield 'token exactly at lifespan boundary' => [3600, 3600, true];
        yield 'expiry ignored when lifespan is zero' => [0, 86400, true];
        yield 'expired token is deleted' => [3600, 7200, false];
        yield 'unknown token' => [0, null, false];
    }

    #[DataProvider('findIdentityByTokenProvider')]
    public function testFindIdentityByToken(int $lifespan, ?int $ageSeconds, bool $found): void
    {
        $now = 1750000000;

        if ($ageSeconds === null) {
            $rawToken = 'no-such-token';
        } else {
            $user = $this->createUser('tokenuser', 'token@example.com');
            $rawToken = (new ApiTokenService(new SystemClock()))->generate($user);

            $token = UserToken::findByCodeAndType($rawToken, UserToken::TYPE_API_ACCESS);
            self::assertNotNull($token);
            $token->setCreatedAt($now - $ageSeconds);
            $token->save();
        }

        $adapter = new ApiTokenIdentityAdapter(
            new ApiConfig(apiTokenLifespan: $lifespan, defaultLocale: 'en'),
            $this->createClock(new DateTimeImmutable('@' . $now)),
        );

        if ($found) {
            self::assertInstanceOf(User::class, $adapter->findIdentityByToken($rawToken));
        } else {
            self::assertNull($adapter->findIdentityByToken($rawToken));

            if ($ageSeconds !== null) {
                self::assertNull(
                    UserToken::findByCodeAndType($rawToken, UserToken::TYPE_API_ACCESS),
                    'Expired token should be deleted as it is resolved.',
                );
            }
        }
    }

    public function testFindIdentityDelegatesToCoreResolvingByUserId(): void
    {
        $user = $this->createUser('byiduser', 'byid@example.com');

        $adapter = new ApiTokenIdentityAdapter(
            new ApiConfig(apiTokenLifespan: 0, defaultLocale: 'en'),
            $this->createClock(),
        );

        $identity = $adapter->findIdentity((string) $user->getId());

        self::assertInstanceOf(User::class, $identity);
        self::assertSame($user->getId(), $identity->getId());
    }

    private function createClock(?DateTimeImmutable $now = null): ClockInterface
    {
        $now ??= new DateTimeImmutable();

        return new class ($now) implements ClockInterface {
            public function __construct(private DateTimeImmutable $now) {}

            public function now(): DateTimeImmutable
            {
                return $this->now;
            }
        };
    }
}
