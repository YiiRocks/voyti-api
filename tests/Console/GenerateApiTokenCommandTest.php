<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Api\tests\Console;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YiiRocks\Voyti\Api\Console\GenerateApiTokenCommand;
use YiiRocks\Voyti\Api\Service\User\ApiTokenService;
use YiiRocks\Voyti\Api\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\Api\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\Clock\SystemClock;
use YiiRocks\Voyti\Model\UserToken;
use Yiisoft\Yii\Console\ExitCode;

#[AllowMockObjectsWithoutExpectations]
final class GenerateApiTokenCommandTest extends DatabaseTestCase
{
    use UserFactoryTrait;

    public static function executeProvider(): iterable
    {
        yield 'no options' => [null, null, null, ExitCode::USAGE, 8];
        yield 'non-existent user' => [null, 'ghost@example.com', null, ExitCode::NOUSER, 1];
        yield 'existing user by username' => [null, null, 'apiuser', ExitCode::OK, 3];
    }

    public function testConfigureSetsCommandMetadata(): void
    {
        $command = $this->createCommand();

        self::assertSame('voyti:api-token:generate', $command->getName());
        self::assertSame('Generate an API access token for a user', $command->getDescription());
        self::assertTrue($command->getDefinition()->hasOption('email'));
        self::assertTrue($command->getDefinition()->hasOption('username'));
        self::assertTrue($command->getDefinition()->hasOption('id'));
    }

    #[DataProvider('executeProvider')]
    public function testExecute(?string $id, ?string $email, ?string $username, int $expectedCode, int $writelnCount): void
    {
        $userId = null;
        if ($username !== null) {
            $userId = (int) $this->createUser($username, $username . '@example.com')->getId();
        }

        $input = $this->createMock(InputInterface::class);
        $input->expects(self::exactly(3))->method('getOption')->willReturnMap([
            ['id', $id],
            ['email', $email],
            ['username', $username],
        ]);

        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::exactly($writelnCount))->method('writeln');

        $command = $this->createCommand(new ApiTokenService(new SystemClock()));
        $result = $command->run($input, $output);

        self::assertSame($expectedCode, $result);

        if ($userId !== null) {
            self::assertCount(1, UserToken::findByUserId($userId));
        }
    }

    private function createCommand(?ApiTokenService $apiTokenService = null): GenerateApiTokenCommand
    {
        return new GenerateApiTokenCommand(
            $apiTokenService ?? new ApiTokenService(new SystemClock()),
        );
    }
}
