<?php

declare(strict_types=1);

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;
use Keboola\StorageApi\Client;

final class CommandsTest extends ClientTestCase
{

    /**
     * @dataProvider  validParameters
     * @param $parameters
     */
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

    /**
     * @dataProvider  invalidParameters
     * @param $parameters
     */
    public function testInvalidParameters(array $parameters): void
    {
        try {
            $this->client->runCommand($parameters);
            $this->fail('Command should not be executed');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
        }
    }

    public function validParameters(): \Iterator
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

    public function invalidParameters(): \Iterator
    {
        yield [
            [
                'command' => 'test',
                'parameters' => 'unknown',
            ],
        ];
    }
}
