<?php

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Workspaces;

class StorageBackendTest extends ClientTestCase
{
    use BackendConfigurationProviderTrait;

    public function testOnlySuperadminCanRegisterStorageBackend(): void
    {
        $newBackend = $this->client->createStorageBackend($this->getSnowflakeBackendCreateOptions());
        $this->assertSame($newBackend['backend'], 'snowflake');
        $this->assertBackendExist($newBackend['id']);
        $this->client->removeStorageBackend($newBackend['id']);

        $newBackend = $this->normalUserClient->createStorageBackend($this->getSnowflakeBackendCreateOptions());
        $this->assertSame($newBackend['backend'], 'snowflake');
        $this->assertBackendExist($newBackend['id']);
        $this->client->removeStorageBackend($newBackend['id']);
    }

    /**
     * @dataProvider storageBackendOptionsProvider
     */
    public function testCreateStorageBackend(array $options)
    {
        $testMaintainer = $this->client->getMaintainer($this->testMaintainerId);
        $maintainerName = self::TESTS_MAINTAINER_PREFIX . sprintf(' - test managing %s storage backend', $options['backend']);

        $newBackend = $this->client->createStorageBackend($options);

        $this->assertSame($newBackend['backend'], 'snowflake');
        $this->assertBackendExist($newBackend['id']);

        $newMaintainer = $this->client->createMaintainer([
            'name' => $maintainerName,
            'defaultConnectionSnowflakeId' => $newBackend['id'],
            'defaultFileStorageId' => $testMaintainer['defaultFileStorageId'],
        ]);

        $name = 'My org';
        $organization = $this->client->createOrganization($newMaintainer['id'], [
            'name' => $name,
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
            'dataRetentionTimeInDays' => 1,
        ]);

        $projectDetail = $this->client->getProject($project['id']);
        if ($options['useDynamicBackends'] ?? false) {
            $this->assertContains('workspace-snowflake-dynamic-backend-size', $projectDetail['features']);
        } else {
            $this->assertNotContains('workspace-snowflake-dynamic-backend-size', $projectDetail['features']);
        }

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

        $sapiClient = new Client([
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

    public function storageBackendOptionsProvider(): iterable
    {
        yield 'snowflake' => [
            $this->getSnowflakeBackendCreateOptions(),
        ];
        yield 'snowflake with dynamic backends' => [
            $this->getBackendCreateOptionsWithDynamicBackends(),
        ];
    }

    public function storageBackendOptionsProviderForUpdate(): iterable
    {
        $create = $this->getSnowflakeBackendCreateOptions();
        yield 'snowflake update password' => [
            $create,
            [
                'password' => getenv('KBC_TEST_SNOWFLAKE_BACKEND_PASSWORD'),
            ],
        ];
        yield 'snowflake update username' => [
            $create,
            [
                'username' => getenv('KBC_TEST_SNOWFLAKE_BACKEND_NAME'),
            ],
        ];
        yield 'snowflake update enable dynamic backends' => [
            $create,
            [
                'useDynamicBackends' => 1,
            ],
        ];
        $createOptionsWithDynamicBackends = $this->getBackendCreateOptionsWithDynamicBackends();
        yield 'snowflake with dynamic backends update password' => [
            $createOptionsWithDynamicBackends,
            [
                'password' => getenv('KBC_TEST_SNOWFLAKE_BACKEND_PASSWORD'),
            ],
        ];
        yield 'snowflake disable dynamic backends' => [
            $createOptionsWithDynamicBackends,
            [
                'useDynamicBackends' => 0,
            ],
        ];
    }

    /**
     * @dataProvider storageBackendOptionsProvider
     */
    public function testUpdateStorageBackendWithWrongPassword(array $options)
    {
        $backend = $this->client->createStorageBackend($options);

        $wrongOptions = [
            'password' => 'invalid',
        ];

        try {
            $this->client->updateStorageBackend($backend['id'], $wrongOptions);
            $this->fail('Should fail!');
        } catch (ClientException $e) {
            $this->assertContains('Supplied credentials cannot use the supplied warehouse', $e->getMessage());
        } finally {
            $this->client->removeStorageBackend($backend['id']);
        }
    }

    /**
     * @dataProvider storageBackendOptionsProviderForUpdate
     */
    public function testUpdateStorageBackend(array $options, array $updateOptions)
    {
        $maintainerName = self::TESTS_MAINTAINER_PREFIX . sprintf(' - test managing %s storage backend', $options['backend']);
        $backend = $this->client->createStorageBackend($options);

        $updatedBackend = $this->client->updateStorageBackend($backend['id'], $updateOptions);

        $this->assertIsInt($updatedBackend['id']);
        $this->assertArrayHasKey('host', $updatedBackend);
        $this->assertArrayHasKey('backend', $updatedBackend);
        $this->assertArrayHasKey('region', $updatedBackend);
        $this->assertArrayHasKey('useDynamicBackends', $updatedBackend);
        if (array_key_exists('useDynamicBackends', $updateOptions)) {
            $this->assertNotSame($backend['useDynamicBackends'], $updatedBackend['useDynamicBackends']);
        }
        $testMaintainer = $this->client->getMaintainer($this->testMaintainerId);

        $newMaintainer = $this->client->createMaintainer([
            'name' => $maintainerName,
            'defaultConnectionSnowflakeId' => $backend['id'],
            'defaultFileStorageId' => $testMaintainer['defaultFileStorageId'],
        ]);

        $name = 'My org';
        $organization = $this->client->createOrganization($newMaintainer['id'], [
            'name' => $name,
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
            'dataRetentionTimeInDays' => 1,
        ]);

        $this->client->deleteProject($project['id']);
        $this->waitForProjectPurge($project['id']);

        $this->client->deleteOrganization($organization['id']);
        $this->client->deleteMaintainer($newMaintainer['id']);

        $this->client->removeStorageBackend($backend['id']);
    }

    public function testStorageBackendListAndDetail(): void
    {
        $backends = $this->client->listStorageBackend();

        $this->assertNotEmpty($backends);

        $backend = reset($backends);
        $this->assertIsInt($backend['id']);
        $this->assertArrayHasKey('host', $backend);
        $this->assertArrayHasKey('username', $backend);
        $this->assertArrayHasKey('backend', $backend);

        $backedDetail = $this->client->getStorageBackend($backend['id']);
        $this->assertArrayHasKey('host', $backedDetail);
        $this->assertArrayHasKey('username', $backend);
        $this->assertArrayHasKey('backend', $backedDetail);
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

    public function getBackendCreateOptionsWithDynamicBackends(): array
    {
        return [
            'backend' => 'snowflake',
            'host' => getenv('KBC_TEST_SNOWFLAKE_HOST'),
            'warehouse' => getenv('KBC_TEST_SNOWFLAKE_WAREHOUSE'),
            'username' => getenv('KBC_TEST_SNOWFLAKE_BACKEND_NAME'),
            'password' => getenv('KBC_TEST_SNOWFLAKE_BACKEND_PASSWORD'),
            'region' => getenv('KBC_TEST_SNOWFLAKE_BACKEND_REGION'),
            'owner' => 'keboola',
            'useDynamicBackends' => '1',
        ];
    }

    public function testCreateExasolBackendWithInvalidHostname(): void
    {
        $options = [
            'backend' => 'exasol',
            'host' => 'invalid.example.com',
            'warehouse' => 'xxx',
            'username' => 'xxx',
            'password' => 'xxx',
            'region' => 'eu-central-1',
            'owner' => 'keboola',
        ];

        try {
            $this->client->createStorageBackend($options);
            self::fail('Should fail!');
        } catch (ClientException $e) {
            self::assertSame('Cannot establish connection with provided Exasol cluster. Is it running and accessible?', $e->getMessage());
        }
    }
}
