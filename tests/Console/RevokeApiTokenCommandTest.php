<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Api\tests\Console;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YiiRocks\Voyti\Api\Console\RevokeApiTokenCommand;
use YiiRocks\Voyti\Api\Service\User\ApiTokenService;
use YiiRocks\Voyti\Api\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\Api\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\Model\UserToken;
use Yiisoft\Yii\Console\ExitCode;

#[AllowMockObjectsWithoutExpectations]
final class RevokeApiTokenCommandTest extends DatabaseTestCase
{
    use UserFactoryTrait;

    public static function executeProvider(): iterable
    {
        yield 'no options' => [null, null, null, ExitCode::USAGE, 8];
        yield 'non-existent user' => [null, 'ghost@example.com', null, ExitCode::NOUSER, 1];
        yield 'existing user by username' => [null, null, 'apiuser', ExitCode::OK, 1];
    }

    public function testConfigureSetsCommandMetadata(): void
    {
        $command = $this->createCommand();

        self::assertSame('voyti:api-token:revoke', $command->getName());
        self::assertSame('Revoke all API access tokens for a user', $command->getDescription());
        self::assertTrue($command->getDefinition()->hasOption('email'));
        self::assertTrue($command->getDefinition()->hasOption('username'));
        self::assertTrue($command->getDefinition()->hasOption('id'));
    }

    #[DataProvider('executeProvider')]
    public function testExecute(?string $id, ?string $email, ?string $username, int $expectedCode, int $writelnCount): void
    {
        $apiTokenService = new ApiTokenService();
        $userId = null;

        if ($username !== null) {
            $user = $this->createUser($username, $username . '@example.com');
            $userId = (int) $user->getId();
            $apiTokenService->generate($user);
            $apiTokenService->generate($user);
        }

        $input = $this->createMock(InputInterface::class);
        $input->expects(self::exactly(3))->method('getOption')->willReturnMap([
            ['id', $id],
            ['email', $email],
            ['username', $username],
        ]);

        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::exactly($writelnCount))->method('writeln');

        $command = $this->createCommand($apiTokenService);
        $result = $command->run($input, $output);

        self::assertSame($expectedCode, $result);

        if ($userId !== null) {
            self::assertCount(
                0,
                array_filter(
                    UserToken::findByUserId($userId),
                    static fn(UserToken $token): bool => $token->getType() === UserToken::TYPE_API_ACCESS,
                ),
            );
        }
    }

    private function createCommand(?ApiTokenService $apiTokenService = null): RevokeApiTokenCommand
    {
        return new RevokeApiTokenCommand(
            $apiTokenService ?? new ApiTokenService(),
        );
    }
}
