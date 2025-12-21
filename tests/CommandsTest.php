<?php

declare(strict_types=1);

namespace Keboola\ManageApiTest;

use Iterator;
use Keboola\ManageApi\ClientException;
use Keboola\StorageApi\Client;
use PHPUnit\Framework\Attributes\DataProvider;

final class CommandsTest extends ClientTestCase
{

    #[DataProvider('validParameters')]
    public function testSuperAdminShouldBeAllowedToRunCommand(array $parameters): void
    {
        $response = $this->client->runCommand($parameters);
        $this->assertArrayHasKey('commandExecutionId', $response);
    }

    public function testNormalUserShouldNotBeAllowedToRunCommand(): void
    {
        try {
            $this->normalUserClient->runCommand([
                'command' => 'storage:workers-list',
                'parameters' => [
                    '--help',
                ],
            ]);
            $this->fail('Command should not be executed');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }
    }

    #[DataProvider('invalidParameters')]
    public function testInvalidParameters(array $parameters): void
    {
        try {
            $this->client->runCommand($parameters);
            $this->fail('Command should not be executed');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
        }
    }

    public static function validParameters(): Iterator
    {
        yield [
            [
                'command' => 'storage:workers-list',
                'parameters' => [
                    '--help',
                ],
            ],
        ];
        yield [
            [
                'command' => 'storage:workers-list',
            ],
        ];
    }

    public static function invalidParameters(): Iterator
    {
        yield [
            [
                'command' => 'test',
                'parameters' => 'unknown',
            ],
        ];
    }
}
