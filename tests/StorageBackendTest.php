<?php

namespace Keboola\ManageApiTest;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Keboola\ManageApi\ClientException;
use Keboola\ManageApiTest\Utils\EnvVariableHelper;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Workspaces;
use Keboola\TableBackendUtils\Connection\Snowflake\SnowflakeConnectionFactory;
use Keboola\TableBackendUtils\Escaping\Snowflake\SnowflakeQuote;

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

    public function testCreateStorageBackendWithCert()
    {
        $db = $this->prepareConnection();
        $kbcTestSnowflakeBackendName = EnvVariableHelper::getKbcTestSnowflakeBackendName();
        $this->cleanupRegisteredBackend($kbcTestSnowflakeBackendName, $db);

        $newBackend = $this->client->createSnowflakeStorageBackend([
            'host' => EnvVariableHelper::getKbcTestSnowflakeHost(),
            'warehouse' => EnvVariableHelper::getKbcTestSnowflakeWarehouse(),
            'username' => $kbcTestSnowflakeBackendName,
            'region' => EnvVariableHelper::getKbcTestSnowflakeBackendRegion(),
            'owner' => 'keboola',
            'technicalOwner' => 'keboola',
            'useDynamicBackends' => false,
            'useNetworkPolicies' => true,
            'useSso' => true,
        ]);

        $this->assertBackendExist($newBackend['id']);

        $statements = array_filter(array_map('trim', explode(';', $newBackend['sqlTemplate'])));
        foreach ($statements as $statement) {
            $db->executeStatement($statement);
        }

        $newMaintainer = $this->client->createMaintainer([
            'name' => self::TESTS_MAINTAINER_PREFIX . sprintf(' - test managing %s storage backend', 'snowflake'),
            'defaultConnectionSnowflakeId' => $newBackend['id'],
            'defaultFileStorageId' => 1,
        ]);

        $name = 'My org';
        $organization = $this->client->createOrganization($newMaintainer['id'], [
            'name' => $name,
        ]);

        try {
            $this->client->createProject($organization['id'], [
                'name' => 'My test',
                'dataRetentionTimeInDays' => 1,
            ]);
            $this->fail('Should fail');
        } catch (ClientException $e) {
            $this->assertSame(sprintf('Storage root credentials with id "%s" are not activated.', $newBackend['id']), $e->getMessage());
            $this->assertSame(400, $e->getCode());
        }

        $this->client->activateStorageBackend($newBackend['id']);

        try {
            $this->client->activateStorageBackend($newBackend['id']);
            $this->fail('Should fail');
        } catch (ClientException $e) {
            $this->assertSame(sprintf('Certificate for storage backend with id "%s" is already enabled.', $newBackend['id']), $e->getMessage());
            $this->assertSame(400, $e->getCode());
        }

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
            'dataRetentionTimeInDays' => 1,
        ]);
        $this->createTestProjectAndValidate($project['id'], $newBackend['useDynamicBackends'], $newBackend['id'], $newMaintainer['id']);
    }

    public function testCreateStorageBackendWithCertWithoutUserOnBackend()
    {
        $newBackend = $this->client->createSnowflakeStorageBackend([
            'host' => EnvVariableHelper::getKbcTestSnowflakeHost(),
            'warehouse' => EnvVariableHelper::getKbcTestSnowflakeWarehouse(),
            'username' => EnvVariableHelper::getKbcTestSnowflakeBackendName(),
            'region' => EnvVariableHelper::getKbcTestSnowflakeBackendRegion(),
            'owner' => 'keboola',
            'technicalOwner' => 'keboola',
            'useDynamicBackends' => false,
            'useNetworkPolicies' => false,
            'useSso' => false,
        ]);

        $this->assertBackendExist($newBackend['id']);

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage(sprintf(
            'Storage backend activation failed: Supplied credentials cannot use the supplied warehouse "%s"',
            EnvVariableHelper::getKbcTestSnowflakeWarehouse(),
        ));
        $this->expectExceptionCode(400);

        $this->client->activateStorageBackend($newBackend['id']);
    }

    /**
     * @dataProvider storageBackendOptionsProvider
     */
    public function testCreateStorageBackend(array $options)
    {
        $testMaintainer = $this->client->getMaintainer($this->testMaintainerId);
        $maintainerName = self::TESTS_MAINTAINER_PREFIX . sprintf(' - test managing %s storage backend', $options['backend']);

        $newBackend = $this->client->createStorageBackend($options);

        $this->assertSame($options['backend'], $newBackend['backend']);
        $this->assertSame($options['region'], $newBackend['region']);
        $this->assertSame($options['owner'], $newBackend['owner']);
        $this->assertSame($options['technicalOwner'], $newBackend['technicalOwner']);
        $this->assertSame($options['host'], $newBackend['host']);
        if (array_key_exists('useDynamicBackends', $options)) {
            $this->assertSame($options['useDynamicBackends'], $newBackend['useDynamicBackends']);
        }

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

        $this->createTestProjectAndValidate($project['id'], $newBackend['useDynamicBackends'], $newBackend['id'], $newMaintainer['id']);
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
                'password' => EnvVariableHelper::getKbcTestSnowflakeBackendPassword(),
            ],
            false,
        ];
        yield 'snowflake update username' => [
            $create,
            [
                'username' => EnvVariableHelper::getKbcTestSnowflakeBackendName(),
            ],
            false,
        ];
        yield 'snowflake update enable dynamic backends' => [
            $create,
            [
                'useDynamicBackends' => true,
            ],
            true,
        ];
        yield 'snowflake update owner' => [
            $create,
            [
                'owner' => 'client123',
            ],
            true,
        ];
        yield 'snowflake update tech owner' => [
            $create,
            [
                'technicalOwner' => 'kbdb',
            ],
            true,
        ];
        $createOptionsWithDynamicBackends = $this->getBackendCreateOptionsWithDynamicBackends();
        yield 'snowflake with dynamic backends update password' => [
            $createOptionsWithDynamicBackends,
            [
                'password' => EnvVariableHelper::getKbcTestSnowflakeBackendPassword(),
            ],
            false,
        ];
        yield 'snowflake disable dynamic backends' => [
            $createOptionsWithDynamicBackends,
            [
                'useDynamicBackends' => false,
            ],
            true,
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
    public function testUpdateStorageBackend(array $options, array $updateOptions, bool $checkResponse)
    {
        $maintainerName = self::TESTS_MAINTAINER_PREFIX . sprintf(' - test managing %s storage backend', $options['backend']);
        $backend = $this->client->createStorageBackend($options);

        $updatedBackend = $this->client->updateStorageBackend($backend['id'], $updateOptions);

        $this->assertIsInt($updatedBackend['id']);
        $this->assertArrayHasKey('host', $updatedBackend);
        $this->assertArrayHasKey('backend', $updatedBackend);
        $this->assertArrayHasKey('region', $updatedBackend);
        $this->assertArrayHasKey('owner', $updatedBackend);
        $this->assertArrayHasKey('useDynamicBackends', $updatedBackend);
        if (array_key_exists('useDynamicBackends', $updateOptions)) {
            $this->assertNotSame($backend['useDynamicBackends'], $updatedBackend['useDynamicBackends']);
        }
        if ($checkResponse) {
            foreach ($updateOptions as $key => $value) {
                $this->assertSame($value, $updatedBackend[$key]);
            }
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
        $this->assertArrayHasKey('owner', $backend);
        $this->assertArrayHasKey('technicalOwner', $backend);

        $backedDetail = $this->client->getStorageBackend($backend['id']);
        $this->assertArrayHasKey('host', $backedDetail);
        $this->assertArrayHasKey('username', $backend);
        $this->assertArrayHasKey('backend', $backedDetail);
        $this->assertArrayHasKey('owner', $backedDetail);
        $this->assertArrayHasKey('technicalOwner', $backedDetail);
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
            'host' => EnvVariableHelper::getKbcTestSnowflakeHost(),
            'warehouse' => EnvVariableHelper::getKbcTestSnowflakeWarehouse(),
            'username' => EnvVariableHelper::getKbcTestSnowflakeBackendName(),
            'password' => EnvVariableHelper::getKbcTestSnowflakeBackendPassword(),
            'region' => EnvVariableHelper::getKbcTestSnowflakeBackendRegion(),
            'owner' => 'keboola',
            'technicalOwner' => 'keboola',
            'useDynamicBackends' => true,
        ];
    }

    public function prepareConnection(): Connection
    {
        $host = EnvVariableHelper::getKbcTestSnowflakeHost();
        $user = EnvVariableHelper::getKbcTestMainSnowflakeBackendName();
        $privateKey = EnvVariableHelper::getKbcTestMainSnowflakeBackendPrivateKey();
        $params = [
            'database' => EnvVariableHelper::getKbcTestMainSnowflakeBackendDatabase(),
            'warehouse' => EnvVariableHelper::getKbcTestMainSnowflakeBackendWarehouse(),
        ];

        $connection = SnowflakeConnectionFactory::getConnectionWithCert($host, $user, $privateKey, $params);

        // Ensure sufficient privileges for account-level operations
        $connection->executeStatement('USE ROLE ACCOUNTADMIN');
        $connection->executeQuery('SELECT 1;');
        return $connection;
    }

    private function createTestProjectAndValidate(int $projectId, bool $useDynamicBackends, int $backendId, int $maintainerId): void
    {
        $projectDetail = $this->client->getProject($projectId);

        if ($useDynamicBackends) {
            $this->assertContains('workspace-snowflake-dynamic-backend-size', $projectDetail['features']);
        } else {
            $this->assertNotContains('workspace-snowflake-dynamic-backend-size', $projectDetail['features']);
        }

        try {
            $this->client->removeStorageBackend($backendId);
            $this->fail('Should fail because backend is used in project');
        } catch (ClientException $e) {
            $this->assertSame(
                sprintf(
                    'Storage backend is still used: in project(s) with id(s) "%d". Please delete and purge these projects.',
                    $projectId
                ),
                $e->getMessage()
            );
        }

        $token = $this->client->createProjectStorageToken($projectId, [
            'description' => 'test',
            'expiresIn' => 60,
            'canManageBuckets' => true,
        ]);

        $sapiClient = new Client([
            'url' => EnvVariableHelper::getKbcManageApiUrl(),
            'token' => $token['token'],
        ]);

        $sapiClient->createBucket('test', 'in');

        try {
            $this->client->removeStorageBackend($backendId);
            $this->fail('should fail because backend is used in project with bucket');
        } catch (ClientException $e) {
            $this->assertSame(
                sprintf(
                    'Storage backend is still used: in project(s) with id(s) "%d". Please delete and purge these projects.',
                    $projectId
                ),
                $e->getMessage()
            );
        }

        $workspace = new Workspaces($sapiClient);
        $workspace = $workspace->createWorkspace();

        try {
            $this->client->removeStorageBackend($backendId);
            $this->fail('should fail because backend is used in project and workspace');
        } catch (ClientException $e) {
            $this->assertSame(
                sprintf(
                    'Storage backend is still used: in project(s) with id(s) "%d" in workspace(s) with id(s) "%d". Please delete and purge these projects.',
                    $projectId,
                    $workspace['id']
                ),
                $e->getMessage()
            );
        }

        $this->client->deleteProject($projectId);
        $this->waitForProjectPurge($projectId);

        $this->client->removeStorageBackend($backendId);

        $this->assertBackendNotExist($backendId);

        $maintainer = $this->client->getMaintainer($maintainerId);
        $this->assertNull($maintainer['defaultConnectionSnowflakeId']);
    }

    private function cleanupRegisteredBackend(string $testUser, Connection $db): void
    {
        $dbName = $testUser . '_INTERNAL';
        $schemaName = $testUser . '_SCHEMA';
        $ruleName = $dbName . '_NETWORK_RULE';
        $roleName = $testUser . '_ROLE';
        $policyName = $dbName . '_NETWORK_POLICY';
        $ssoIntegrationName = strtoupper(EnvVariableHelper::getKbcTestSnowflakeBackendClientDbPrefix()) . '_SAML_INTEGRATION';
        $cleanupStatements = [
            'USE ROLE ACCOUNTADMIN;',
            sprintf('USE DATABASE %s;', SnowflakeQuote::quoteSingleIdentifier($dbName)),
            sprintf('USE SCHEMA %s.%s;', SnowflakeQuote::quoteSingleIdentifier($dbName), SnowflakeQuote::quoteSingleIdentifier($schemaName)),
            sprintf('ALTER USER %s UNSET NETWORK_POLICY;', SnowflakeQuote::quoteSingleIdentifier($testUser)),
            sprintf('DROP NETWORK POLICY IF EXISTS %s;', SnowflakeQuote::quoteSingleIdentifier($policyName)),
            sprintf('DROP NETWORK RULE IF EXISTS %s;', SnowflakeQuote::quoteSingleIdentifier($ruleName)),
            sprintf('DROP SECURITY INTEGRATION IF EXISTS %s;', SnowflakeQuote::quoteSingleIdentifier($ssoIntegrationName)),
            sprintf('DROP DATABASE IF EXISTS %s;', SnowflakeQuote::quoteSingleIdentifier(strtoupper($dbName))),
            sprintf('DROP WAREHOUSE IF EXISTS %s;', SnowflakeQuote::quoteSingleIdentifier(EnvVariableHelper::getKbcTestSnowflakeWarehouse())),
            sprintf('DROP USER IF EXISTS %s;', SnowflakeQuote::quoteSingleIdentifier($testUser)),
            sprintf('REVOKE ROLE %s FROM ROLE %s;', SnowflakeQuote::quoteSingleIdentifier($roleName), SnowflakeQuote::quoteSingleIdentifier('SYSADMIN')),
            sprintf('DROP ROLE IF EXISTS %s;', SnowflakeQuote::quoteSingleIdentifier($roleName)),
        ];
        foreach ($cleanupStatements as $cleanupSql) {
            try {
                $db->executeStatement($cleanupSql);
            } catch (Exception $e) {
            }
        }
    }
}
