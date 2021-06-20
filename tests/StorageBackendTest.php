<?php

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;
use Keboola\StorageApi\Workspaces;

class StorageBackendTest extends ClientTestCase
{
    /**
     * @dataProvider storageBackendOptionsProvider
     */
    public function testCreateStorageBackend(array $options)
    {
        $maintainerName = self::TESTS_MAINTAINER_PREFIX . sprintf(' - test managing %s storage backend', $options['backend']);

        $newBackend = $this->client->createStorageBackend($options);

        $this->assertSame($newBackend['backend'], 'snowflake');
        $this->assertBackendExist($newBackend['id']);

        $newMaintainer = $this->client->createMaintainer([
            'name' => $maintainerName,
            'defaultConnectionSnowflakeId' => $newBackend['id'],
        ]);

        $name = 'My org';
        $organization = $this->client->createOrganization($newMaintainer['id'], [
            'name' => $name,
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
        ]);

        try {
            $this->client->removeStorageBackend($newBackend['id']);
            $this->fail('Should fail because backend is used in project');
        } catch (ClientException $e) {
            $this->assertSame(
                sprintf(
                    'Storage backend is still used: in project(s) with id(s) "%d". Please delete and purge these projects.',
                    $project['id']
                ),
                $e->getMessage()
            );
        }

        $token = $this->client->createProjectStorageToken($project['id'], [
            'description' => 'test',
            'expiresIn' => 60,
            'canManageBuckets' => true,
        ]);

        $sapiClient = new \Keboola\StorageApi\Client([
            'url' => getenv('KBC_MANAGE_API_URL'),
            'token' => $token['token'],
        ]);

        $sapiClient->createBucket('test', 'in');

        try {
            $this->client->removeStorageBackend($newBackend['id']);
            $this->fail('should fail because backend is used in project with bucket');
        } catch (ClientException $e) {
            $this->assertSame(
                sprintf(
                    'Storage backend is still used: in project(s) with id(s) "%d". Please delete and purge these projects.',
                    $project['id']
                ),
                $e->getMessage()
            );
        }

        $workspace = new Workspaces($sapiClient);
        $workspace = $workspace->createWorkspace();

        try {
            $this->client->removeStorageBackend($newBackend['id']);
            $this->fail('should fail because backend is used in project and workspace');
        } catch (ClientException $e) {
            $this->assertSame(
                sprintf(
                    'Storage backend is still used: in project(s) with id(s) "%d" in workspace(s) with id(s) "%d". Please delete and purge these projects.',
                    $project['id'],
                    $workspace['id']
                ),
                $e->getMessage()
            );
        }

        $this->client->deleteProject($project['id']);
        $this->waitForProjectPurge($project['id']);

        $this->client->removeStorageBackend($newBackend['id']);

        $this->assertBackendNotExist($newBackend['id']);

        $maintainer = $this->client->getMaintainer($newMaintainer['id']);
        $this->assertNull($maintainer['defaultConnectionSnowflakeId']);
    }

    public function storageBackendOptionsProvider(): \Generator
    {
        yield 'snowflake' => [
            [
                'backend' => 'snowflake',
                'host' => getenv('KBC_TEST_SNOWFLAKE_HOST'),
                'warehouse' => getenv('KBC_TEST_SNOWFLAKE_WAREHOUSE'),
                'username' => getenv('KBC_TEST_SNOWFLAKE_BACKEND_NAME'),
                'password' => getenv('KBC_TEST_SNOWFLAKE_BACKEND_PASSWORD'),
                'region' => getenv('KBC_TEST_SNOWFLAKE_BACKEND_REGION'),
                'owner' => 'keboola',
            ],
        ];
    }

    /**
     * @dataProvider storageBackendOptionsProvider
     */
    public function testUpdateStorageBackend(array $options)
    {
        $backend = $this->client->createStorageBackend($options);

        $wrongOptions = [
            'password' => 'invalid',
        ];

        try {
            $this->client->updateStorageBackend($backend['id'], $wrongOptions);
            $this->fail('Should fail!');
        } catch (ClientException $e) {
            $this->assertSame('Failed to connect using the supplied credentials', $e->getMessage());
        }

        $options = [
            'password' => getenv('KBC_TEST_SNOWFLAKE_BACKEND_PASSWORD'),
        ];

        $this->client->updateStorageBackend($backend['id'], $options);
    }

    public function testStorageBackendList()
    {
        $backends = $this->client->listStorageBackend();

        $this->assertNotEmpty($backends);

        $backend = reset($backends);
        $this->assertInternalType('int', $backend['id']);
        $this->assertArrayHasKey('host', $backend);
        $this->assertArrayHasKey('backend', $backend);
        $this->assertArrayNotHasKey('login', $backend);
    }

    public function testStorageBackendListWithLogins()
    {
        $backends = $this->client->listStorageBackend([
            'logins' => true,
        ]);

        $this->assertNotEmpty($backends);

        $backend = reset($backends);
        $this->assertInternalType('int', $backend['id']);
        $this->assertArrayHasKey('host', $backend);
        $this->assertArrayHasKey('backend', $backend);
        $this->assertArrayHasKey('login', $backend);
        $this->assertArrayHasKey('region', $backend);
    }

    private function assertBackendExist(int $backendId): void
    {
        $backends = $this->client->listStorageBackend();

        $hasBackend = false;
        foreach ($backends as $backend) {
            if ($backend['id'] === $backendId) {
                $hasBackend = true;
            }
        }
        $this->assertTrue($hasBackend);
    }

    private function assertBackendNotExist(int $backendId): void
    {
        $backends = $this->client->listStorageBackend();

        $hasBackend = false;
        foreach ($backends as $backend) {
            if ($backend['id'] === $backendId) {
                $hasBackend = true;
            }
        }
        $this->assertFalse($hasBackend);
    }
}
