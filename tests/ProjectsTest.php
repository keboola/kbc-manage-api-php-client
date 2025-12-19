<?php

declare(strict_types=1);

namespace Keboola\ManageApiTest;

use Generator;
use InvalidArgumentException;
use Keboola\ManageApi\Backend;
use Keboola\ManageApi\Client;
use Keboola\ManageApi\ClientException;
use Keboola\ManageApi\ProjectRole;
use Keboola\ManageApiTest\Utils\EnvVariableHelper;
use Keboola\StorageApi\ClientException as StorageApiClientException;
use Throwable;

final class ProjectsTest extends ClientTestCase
{
    private const FILE_STORAGE_PROVIDER_S3 = 'aws';
    private const FILE_STORAGE_PROVIDER_ABS = 'azure';
    private const FILE_STORAGE_PROVIDER_GCS = 'gcs';
    private const PAY_AS_YOU_GO_CREDITS_ADMIN_FEATURE_NAME = 'pay-as-you-go-credits-admin';
    private const PAY_AS_YOU_GO_PROJECT_FEATURE_NAME = 'pay-as-you-go';
    private const CAN_MANAGE_DELETED_PROJECTS_FEATURE_NAME = 'can-manage-deleted-projects';

    public function setUp(): void
    {
        parent::setUp();

        $featuresToRemoveFromUsers = [
            self::CAN_MANAGE_PROJECT_SETTINGS_FEATURE_NAME,
            self::CAN_MANAGE_DELETED_PROJECTS_FEATURE_NAME,
        ];

        foreach ($featuresToRemoveFromUsers as $feature) {
            $this->client->removeUserFeature($this->normalUser['email'], $feature);
        }
    }

    public function supportedBackends(): \Iterator
    {
        yield [Backend::SNOWFLAKE];
        yield [Backend::REDSHIFT];
        yield [Backend::SYNAPSE];
        yield [Backend::TERADATA];
    }

    public function unsupportedBackendFileStorageCombinations(): \Iterator
    {
        yield [
            Backend::REDSHIFT,
            self::FILE_STORAGE_PROVIDER_GCS,
            'Redshift does not support other file storage than S3.',
        ];
    }

    /**
     * @group skipOnGcp
     * @dataProvider unsupportedBackendFileStorageCombinations
     */
    public function testUnsupportedFileStorageForBackend(
        string $backend,
        string $unsupportedFileStorageProvider,
        string $expectedMessage
    ): void {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        // create with snflk backend
        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
            'defaultBackend' => Backend::SNOWFLAKE,
            'dataRetentionTimeInDays' => 1,
        ]);
        switch ($unsupportedFileStorageProvider) {
            case self::FILE_STORAGE_PROVIDER_S3:
                $storage = $this->client->listS3FileStorage()[0];
                break;
            case self::FILE_STORAGE_PROVIDER_GCS:
                $storage = $this->client->listGcsFileStorage()[0];
                break;
        }

        $backends = $this->client->listStorageBackend();
        $backendToAssign = null;
        foreach ($backends as $item) {
            if ($item['backend'] === $backend) {
                $backendToAssign = $item;
            }
        }

        $this->client->assignFileStorage(
            $project['id'],
            $storage['id']
        );

        try {
            $this->client->assignProjectStorageBackend(
                $project['id'],
                $backendToAssign['id']
            );
            $this->fail('Exception should be thrown.');
        } catch (Throwable $e) {
            $this->assertSame(
                $expectedMessage,
                $e->getMessage()
            );
        } finally {
            $this->client->deleteProject($project['id']);
            $this->client->purgeDeletedProject($project['id']);
        }
    }

    /**
     * @group skipOnGcp
     * @dataProvider unsupportedBackendFileStorageCombinations
     */
    public function testUnsupportedBackendForFileStorage(
        string $backend,
        string $unsupportedFileStorageProvide,
        string $expectedMessage
    ): void {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        // create with snflk backend
        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
            'defaultBackend' => Backend::SNOWFLAKE,
            'dataRetentionTimeInDays' => 1,
        ]);

        $s3Storage = $this->client->listS3FileStorage()[0];
        $AbsStorage = $this->client->listGcsFileStorage()[0];

        $backends = $this->client->listStorageBackend();
        $backendToAssign = null;
        foreach ($backends as $item) {
            if ($item['backend'] === $backend) {
                $backendToAssign = $item;
            }
        }

        switch ($unsupportedFileStorageProvide) {
            case self::FILE_STORAGE_PROVIDER_S3:
                $unsupportedFileStorage = $s3Storage;
                $supportedFileStorage = $AbsStorage;
                break;
            case self::FILE_STORAGE_PROVIDER_GCS:
                $unsupportedFileStorage = $AbsStorage;
                $supportedFileStorage = $s3Storage;
                break;
        }

        // assign supported storage
        $this->client->assignFileStorage(
            $project['id'],
            $supportedFileStorage['id']
        );
        // assign backend
        $this->client->assignProjectStorageBackend(
            $project['id'],
            $backendToAssign['id']
        );

        try {
            $this->client->assignFileStorage(
                $project['id'],
                $unsupportedFileStorage['id']
            );
            $this->fail('Exception should be thrown.');
        } catch (Throwable $e) {
            $this->assertSame(
                $expectedMessage,
                $e->getMessage()
            );
        } finally {
            $this->client->deleteProject($project['id']);
            $this->client->purgeDeletedProject($project['id']);
        }
    }

    private function initTestOrganization(): array
    {
        $name = 'My org';
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => $name,
        ]);

        $this->assertArrayHasKey('id', $organization);
        $this->assertArrayHasKey('name', $organization);

        $this->assertEquals($organization['name'], $name);

        return $organization;
    }

    /**
     * @param $organizationId
     * @return array
     */
    private function initTestProject($organizationId): array
    {
        $project = $this->client->createProject($organizationId, [
            'name' => 'My test',
            'dataRetentionTimeInDays' => 1,
        ]);
        $this->assertArrayHasKey('id', $project);
        $this->assertEquals('My test', $project['name']);

        $foundProject = $this->client->getProject($project['id']);
        $this->assertEquals($project['id'], $foundProject['id']);
        $this->assertEquals($project['name'], $foundProject['name']);
        $this->assertArrayHasKey('organization', $foundProject);
        $this->assertArrayHasKey('isBYODB', $foundProject);
        $this->assertFalse($foundProject['isBYODB']);
        $this->assertEquals($organizationId, $foundProject['organization']['id']);
        $this->assertArrayHasKey('limits', $foundProject);
        $this->assertGreaterThan(1, count($foundProject['limits']));
        $this->assertArrayHasKey('metrics', $foundProject);
        $this->assertEquals('snowflake', $foundProject['defaultBackend']);
        $this->assertArrayHasKey('isDisabled', $foundProject);
        $this->assertEquals('production', $project['type']);
        $this->assertNull($project['expires']);
        $this->assertNotEmpty($project['region']);

        $firstLimit = reset($foundProject['limits']);
        $limitKeys = array_keys($foundProject['limits']);
        $this->assertArrayHasKey('name', $firstLimit);
        $this->assertArrayHasKey('value', $firstLimit);
        $this->assertIsInt($firstLimit['value']);
        $this->assertEquals($firstLimit['name'], $limitKeys[0]);

        $this->assertArrayHasKey('fileStorage', $project);

        $fileStorage = $project['fileStorage'];
        $this->assertIsInt($fileStorage['id']);
        $this->assertArrayHasKey('region', $fileStorage);
        switch ($fileStorage['provider']) {
            case self::FILE_STORAGE_PROVIDER_S3:
                $this->assertArrayHasKey('awsKey', $fileStorage);
                $this->assertArrayHasKey('filesBucket', $fileStorage);
                break;
            case 'gcp':
                $this->assertArrayHasKey('gcsCredentials', $fileStorage);
                $this->assertArrayHasKey('gcsSnowflakeIntegrationName', $fileStorage);
                $this->assertArrayHasKey('filesBucket', $fileStorage);
                break;
            case self::FILE_STORAGE_PROVIDER_ABS:
                $this->assertArrayHasKey('accountName', $fileStorage);
                $this->assertArrayHasKey('containerName', $fileStorage);
                $this->assertArrayHasKey('containerName', $fileStorage);
                break;
        }

        $this->assertArrayHasKey('backends', $project);

        $backends = $project['backends'];
        $this->assertArrayHasKey('snowflake', $backends);

        $snowflake = $backends['snowflake'];
        $this->assertIsInt($snowflake['id']);
        $this->assertArrayHasKey('host', $snowflake);

        return $foundProject;
    }

    public function addUserToProjectWithRoleData(): \Iterator
    {
        yield [
            ProjectRole::ADMIN,
        ];
        yield [
            ProjectRole::GUEST,
        ];
        yield [
            ProjectRole::READ_ONLY,
        ];
        yield [
            ProjectRole::SHARE,
        ];
    }

    public function testSuperManageTokenWithScopeCanSeeProjectDetail(): void
    {
        $organization = $this->initTestOrganization();
        $organizationId = $organization['id'];
        $project = $this->initTestProject($organizationId);

        $client = $this->createSuperManageTokenWithProjectsReadScopeClient();

        $actual = $client->getProject($project['id']);
        $expected = $this->client->getProject($project['id']);

        $this->assertSame($expected, $actual, 'Client with scope should see the same response');

        $this->expectExceptionMessage('You don\'t have access to the resource.');
        $this->expectException(ClientException::class);
        $clientWithoutScopes = $this->createSuperManageTokenWithoutScopesClient();
        $clientWithoutScopes->getProject($project['id']);
    }

    public function testSuperAdminCannotCreateProject(): void
    {
        $organization = $this->initTestOrganization();
        $organizationId = $organization['id'];

        $this->client->addUserToOrganization($organizationId, ['email' => $this->normalUser['email']]);
        $this->client->removeUserFromOrganization($organizationId, $this->superAdmin['id']);

        $member = $this->findOrganizationMember($organizationId, $this->superAdmin['email']);
        $this->assertNull($member);

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);
        $this->client->removeUserFromMaintainer($this->testMaintainerId, $this->superAdmin['id']);

        $member = $this->findMaintainerMember($this->testMaintainerId, $this->superAdmin['email']);
        $this->assertNull($member);

        $projects = $this->client->listOrganizationProjects($organizationId);
        $this->assertCount(0, $projects);

        try {
            $this->client->createProject($organizationId, [
                'name' => 'My test',
                'dataRetentionTimeInDays' => 1,
            ]);

            $this->fail('SuperAdmin should be not allowed to create project');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertEquals('manage.createProjectPermissionDenied', $e->getStringCode());
            $this->assertSame('Only organization members can create new projects', $e->getMessage());
        }

        $projects = $this->client->listOrganizationProjects($organizationId);
        $this->assertCount(0, $projects);
    }

    public function testMaintainerAdminCannotCreateProject(): void
    {
        $organization = $this->initTestOrganization();
        $organizationId = $organization['id'];

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $member = $this->findOrganizationMember($organizationId, $this->normalUser['email']);
        $this->assertNull($member);

        $member = $this->findMaintainerMember($this->testMaintainerId, $this->normalUser['email']);
        $this->assertNotNull($member);
        $this->assertEquals($this->normalUser['email'], $member['email']);

        $projects = $this->normalUserClient->listOrganizationProjects($organizationId);
        $this->assertCount(0, $projects);

        try {
            $this->normalUserClient->createProject($organizationId, [
                'name' => 'My test',
                'dataRetentionTimeInDays' => 1,
            ]);

            $this->fail('MaintainerAdmin should be not allowed to create project');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertEquals('manage.createProjectPermissionDenied', $e->getStringCode());
            $this->assertSame('Only organization members can create new projects', $e->getMessage());
        }

        $projects = $this->normalUserClient->listOrganizationProjects($organizationId);
        $this->assertCount(0, $projects);
    }

    public function testRandomAdminCannotCreateProject(): void
    {
        $organization = $this->initTestOrganization();
        $organizationId = $organization['id'];

        $member = $this->findOrganizationMember($organizationId, $this->normalUser['email']);
        $this->assertNull($member);

        $member = $this->findMaintainerMember($this->testMaintainerId, $this->normalUser['email']);
        $this->assertNull($member);

        $projects = $this->client->listOrganizationProjects($organizationId);
        $this->assertCount(0, $projects);

        try {
            $this->normalUserClient->createProject($organizationId, [
                'name' => 'My test',
                'dataRetentionTimeInDays' => 1,
            ]);

            $this->fail('RandomAdmin should be not allowed to create project');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
            $this->assertStringContainsString('You don\'t have access to the organization', $e->getMessage());
        }

        $projects = $this->client->listOrganizationProjects($organizationId);
        $this->assertCount(0, $projects);
    }

    public function testOrganizationAdminCanCreateProject(): void
    {
        $organization = $this->initTestOrganization();
        $organizationId = $organization['id'];

        $member = $this->findOrganizationMember($organizationId, $this->superAdmin['email']);
        $this->assertNotNull($member);
        $this->assertEquals($this->superAdmin['email'], $member['email']);

        $project = $this->initTestProject($organizationId);

        // check if the project is listed in organization projects
        $projects = $this->client->listOrganizationProjects($organizationId);

        $this->assertCount(1, $projects);
        $this->assertEquals($project['id'], $projects[0]['id']);
        $this->assertEquals($project['name'], $projects[0]['name']);

        // delete project
        $this->client->deleteProject($project['id']);

        $projects = $this->client->listOrganizationProjects($organizationId);
        $this->assertEmpty($projects);

        $this->client->deleteOrganization($organizationId);
    }

    public function testProductionProjectCreate(): void
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
            'type' => 'production',
            'dataRetentionTimeInDays' => 1,
        ]);

        $project = $this->client->getProject($project['id']);
        $this->assertEquals('production', $project['type']);
        $this->assertNull($project['expires']);

        $this->assertArrayHasKey('isBYODB', $project);
        $this->assertFalse($project['isBYODB']);
    }

    public function testDemoProjectCreate(): void
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->createRedshiftProjectForClient($this->client, $organization['id'], [
            'name' => 'My test',
            'type' => 'demo',
        ]);

        $project = $this->client->getProject($project['id']);
        $this->assertEquals('demo', $project['type']);
        $this->assertNotEmpty($project['expires']);
    }

    /**
     * @dataProvider supportedBackends
     * @param string $backend
     */
    public function testCreateProjectWithBackend(string $backend): void
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
            'defaultBackend' => $backend,
            'dataRetentionTimeInDays' => 1,
        ]);

        $this->assertEquals($backend, $project['defaultBackend']);
        if ($project['backends'] !== []) {
            $this->assertNotEmpty($project['backends'][$backend]['owner']);
            $this->assertEquals('keboola', $project['backends'][$backend]['owner']);
        }

        $this->client->deleteProject($project['id']);
        $this->client->purgeDeletedProject($project['id']);
    }

    public function testCreateProjectWithRedshiftBackendFromTemplate(): void
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
            'type' => 'productionRedshift',
        ]);

        $this->assertEquals('redshift', $project['defaultBackend']);
    }

    public function testCreateProjectWithInvalidBackendShouldFail(): void
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        try {
            $this->client->createProject($organization['id'], [
                'name' => 'My test',
                'defaultBackend' => 'file',
            ]);
            $this->fail('Project should not be created');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertEquals('storage.unsupportedBackend', $e->getStringCode());
        }
    }

    public function testUsersManagement(): void
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->createRedshiftProjectForClient($this->client, $organization['id'], [
            'name' => 'My test',
        ]);

        $admins = $this->client->listProjectUsers($project['id']);
        $this->assertCount(1, $admins);

        $admin = $admins[0];
        $this->assertNotEmpty($admin['id']);
        $this->assertNotEmpty($admin['name']);
        $this->assertNotEmpty($admin['email']);
        $this->assertNotEmpty($admin['status']);
        $this->assertNotEmpty($admin['created']);
        $this->assertEmpty($admin['expires']);
        $this->assertTrue(is_bool($admin['mfaEnabled']));

        $this->assertArrayHasKey('invitor', $admin);
        $this->assertNull($admin['invitor']);
        $this->assertArrayHasKey('approver', $admin);
        $this->assertNull($admin['approver']);

        // begin test of adding / removing user without expiration/reason
        $resp = $this->client->addUserToProject($project['id'], [
            'email' => 'devel-tests@keboola.com',
        ]);

        $admins = $this->client->listProjectUsers($project['id']);
        $this->assertCount(2, $admins);

        $foundUser = null;
        foreach ($admins as $user) {
            if ($user['email'] === 'devel-tests@keboola.com') {
                $foundUser = $user;
                break;
            }
        }
        if (!$foundUser) {
            $this->fail('User should be in list');
        } else {
            $this->assertNull($foundUser['expires']);
            $this->assertEmpty($foundUser['reason']);
        }

        $this->client->removeUserFromProject($project['id'], $foundUser['id']);

        $admins = $this->client->listProjectUsers($project['id']);
        $this->assertCount(1, $admins);
    }

    public function testUserManagementInvalidEmail(): void
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->createRedshiftProjectForClient($this->client, $organization['id'], [
            'name' => 'My test',
        ]);

        try {
            $this->client->addUserToProject($project['id'], [
                'email' => 'invalid email',
            ]);
            $this->fail('Email address is not valid');
        } catch (ClientException $e) {
            $this->assertEquals(422, $e->getCode());
        }

        // try to add user with empty email address
        try {
            $this->client->addUserToProject($project['id'], [
                'email' => '',
            ]);
            $this->fail('User with empty email cannot be created.');
        } catch (ClientException $e) {
            $this->assertEquals(422, $e->getCode());
        }
    }

    public function testExpiringUserDeletedProject(): void
    {
        // normal user in this case will be our maintainer.
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->createRedshiftProjectForClient($this->client, $organization['id'], [
            'name' => 'My test',
        ]);

        $admins = $this->client->listProjectUsers($project['id']);
        $this->assertCount(1, $admins);
        $this->assertEquals($this->superAdmin['email'], $admins[0]['email']);

        // test of adding / removing user with expiration/reason
        $resp = $this->client->addUserToProject($project['id'], [
            'email' => $this->normalUser['email'],
            'reason' => 'created by test',
            'expirationSeconds' => '20',
        ]);

        $admins = $this->normalUserClient->listProjectUsers($project['id']);
        $this->assertCount(2, $admins);

        foreach ($admins as $projUser) {
            $this->assertEquals('active', $projUser['status']);
            if ($projUser['email'] === $this->superAdmin['email']) {
                $this->assertEquals($projUser['id'], $this->superAdmin['id']);
            } else {
                $this->assertEquals($projUser['email'], $this->normalUser['email']);
            }
        }

        // now delete the project
        $this->client->deleteProject($project['id']);

        // now the next time the cron runs the user should be removed from the deleted project.
        sleep(120);

        // after undeleting the project, the user should be gone
        $this->client->undeleteProject($project['id']);

        // user should be gone
        $admins = $this->client->listProjectUsers($project['id']);

        $this->assertCount(1, $admins);
        $this->assertEquals($this->superAdmin['email'], $admins[0]['email']);

        // let's add the expired user back to the project
        $resp = $this->client->addUserToProject($project['id'], [
            'email' => $this->normalUser['email'],
        ]);

        // the project should have 2 users now
        $admins = $this->client->listProjectUsers($project['id']);
        $this->assertCount(2, $admins);
    }

    public function testTemporaryAccess(): void
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->createRedshiftProjectForClient($this->client, $organization['id'], [
            'name' => 'My test',
        ]);
        $admins = $this->client->listProjectUsers($project['id']);
        $this->assertCount(1, $admins);

        // test of adding / removing user with expiration/reason
        $resp = $this->client->addUserToProject($project['id'], [
            'email' => 'devel-tests@keboola.com',
            'reason' => 'created by test',
            'expirationSeconds' => '20',
        ]);

        $admins = $this->client->listProjectUsers($project['id']);
        $this->assertCount(2, $admins);

        $foundUser = null;
        foreach ($admins as $user) {
            if ($user['email'] === 'devel-tests@keboola.com') {
                $foundUser = $user;
                break;
            }
        }
        if (!$foundUser) {
            $this->fail('User should be in list');
        } else {
            $this->assertEquals('created by test', $foundUser['reason']);
            $this->assertGreaterThan(date('Y-m-d H:i:s', time()), $foundUser['expires']);
        }

        // wait for the new guy to get removed from the project during next cron run
        $tries = 0;

        while ($tries < 10) {
            $admins = $this->client->listProjectUsers($project['id']);
            if (count($admins) < 2) {
                break;
            }
            sleep(pow(2, $tries++));
        }
        $this->assertCount(1, $admins);
    }

    public function testProjectUpdate(): void
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
            'dataRetentionTimeInDays' => 1,
        ]);

        // update
        $newName = 'new name';
        $project = $this->client->updateProject($project['id'], [
            'name' => $newName,
        ]);
        $this->assertEquals($newName, $project['name']);

        // fetch again
        $project = $this->client->getProject($project['id']);
        $this->assertEquals($newName, $project['name']);
        $this->assertEquals('snowflake', $project['defaultBackend']);

        // update - change backend
        $project = $this->client->updateProject($project['id'], [
            'defaultBackend' => 'redshift',
        ]);
        $this->assertEquals('redshift', $project['defaultBackend']);

        // fetch again
        $project = $this->client->getProject($project['id']);
        $this->assertEquals('redshift', $project['defaultBackend']);

        $this->assertNull($project['expires']);
        // update - project type and expiration
        $project = $this->client->updateProject($project['id'], [
            'type' => 'demo',
            'expirationDays' => 22, // reset expiration
            'billedMonthlyPrice' => 100000,
        ]);

        $this->assertEquals('demo', $project['type']);
        $this->assertNotEmpty($project['expires']);
        $this->assertEquals(100000, $project['billedMonthlyPrice']);
    }

    public function testNormalUserCannotUpdateProject(): void
    {
        $this->client->removeUserFeature($this->normalUser['email'], self::CAN_MANAGE_PROJECT_SETTINGS_FEATURE_NAME);

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
            'dataRetentionTimeInDays' => 1,
        ]);
        $currentProjectType = $project['type'];

        $templates = $this->client->getProjectTemplates();
        $differentProjectType = null;
        foreach ($templates as $template) {
            if ($template['id'] !== $currentProjectType) {
                $differentProjectType = $template['id'];
                break;
            }
        }
        $this->assertNotNull($differentProjectType);

        try {
            $this->normalUserClient->updateProject(
                $project['id'],
                [
                    'name' => 'Super duper Keboola project',
                ],
            );
            $this->fail('This should fail.');
        } catch (ClientException $e) {
            $this->assertSame(sprintf("You don't have access to project %d", $project['id']), $e->getMessage());
            $this->assertSame(403, $e->getCode());
        }

        try {
            $this->normalUserClient->updateProject(
                $project['id'],
                [
                    'defaultBackend' => 'bigquery',
                ],
            );
            $this->fail('This should fail.');
        } catch (ClientException $e) {
            $this->assertSame(sprintf("You don't have access to project %d", $project['id']), $e->getMessage());
            $this->assertSame(403, $e->getCode());
        }

        try {
            $this->normalUserClient->updateProject(
                $project['id'],
                [
                    'hasTryModeOn' => true,
                ],
            );
            $this->fail('This should fail.');
        } catch (ClientException $e) {
            $this->assertSame(sprintf("You don't have access to project %d", $project['id']), $e->getMessage());
            $this->assertSame(403, $e->getCode());
        }

        try {
            $this->normalUserClient->updateProject(
                $project['id'],
                [
                    'type' => $differentProjectType,
                ],
            );
            $this->fail('This should fail.');
        } catch (ClientException $e) {
            $this->assertSame(sprintf("You don't have access to project %d", $project['id']), $e->getMessage());
            $this->assertSame(403, $e->getCode());
        }

        try {
            $this->normalUserClient->updateProject(
                $project['id'],
                [
                    'expirationDays' => 3,
                ],
            );
            $this->fail('This should fail.');
        } catch (ClientException $e) {
            $this->assertSame(sprintf("You don't have access to project %d", $project['id']), $e->getMessage());
            $this->assertSame(403, $e->getCode());
        }

        try {
            $this->normalUserClient->updateProject(
                $project['id'],
                [
                    'expirationDays' => 3,
                ],
            );
            $this->fail('This should fail.');
        } catch (ClientException $e) {
            $this->assertSame(sprintf("You don't have access to project %d", $project['id']), $e->getMessage());
            $this->assertSame(403, $e->getCode());
        }

        try {
            $this->normalUserClient->updateProject(
                $project['id'],
                [
                    'billedMonthlyPrice' => 10000,
                ],
            );
            $this->fail('This should fail.');
        } catch (ClientException $e) {
            $this->assertSame(sprintf("You don't have access to project %d", $project['id']), $e->getMessage());
            $this->assertSame(403, $e->getCode());
        }

        try {
            $this->normalUserClient->updateProject(
                $project['id'],
                [
                    'dataRetentionTimeInDays' => 123,
                ],
            );
            $this->fail('This should fail.');
        } catch (ClientException $e) {
            $this->assertSame(sprintf("You don't have access to project %d", $project['id']), $e->getMessage());
            $this->assertSame(403, $e->getCode());
        }
    }

    public function testNormalUserWithFeatureCanUpdateProject(): void
    {
        $this->client->addUserFeature($this->normalUser['email'], self::CAN_MANAGE_PROJECT_SETTINGS_FEATURE_NAME);

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
            'dataRetentionTimeInDays' => 1,
        ]);

        $this->assertSame('My test', $project['name']);
        $this->assertSame('snowflake', $project['defaultBackend']);
        $this->assertSame('0', $project['hasTryModeOn']);
        $this->assertSame('production', $project['type']);
        $this->assertNull($project['billedMonthlyPrice']);
        $this->assertSame(1, $project['dataRetentionTimeInDays']);

        $currentProjectType = $project['type'];

        $templates = $this->client->getProjectTemplates();
        $differentProjectType = null;
        foreach ($templates as $template) {
            if ($template['id'] !== $currentProjectType) {
                $differentProjectType = $template['id'];
                break;
            }
        }
        $this->assertNotNull($differentProjectType);

        try {
            $this->normalUserClient->updateProject(
                $project['id'],
                [
                    'timezone' => 'America/Detroit',
                ],
            );
            // I test the timezone setting in BigqueryProjectsTest
            $this->fail('This should fail, timezone is not supported for snowflake.');
        } catch (ClientException $e) {
            $this->assertSame(400, $e->getCode());
            $this->assertSame('The timezone parameter is only supported for the BigQuery backend. The selected backend "snowflake" does not support it.', $e->getMessage());
        }

        $updatedProject = $this->normalUserClient->updateProject(
            $project['id'],
            [
                'name' => 'Super duper Keboola project',
                'defaultBackend' => 'bigquery',
                'hasTryModeOn' => true,
                'type' => $differentProjectType,
                'expirationDays' => 3,
                'billedMonthlyPrice' => 10000,
                'dataRetentionTimeInDays' => 1,
            ],
        );

        $this->assertSame('Super duper Keboola project', $updatedProject['name']);
        $this->assertSame('bigquery', $updatedProject['defaultBackend']);
        $this->assertSame('1', $updatedProject['hasTryModeOn']);
        $this->assertSame($differentProjectType, $updatedProject['type']);
        $this->assertSame(10000, $updatedProject['billedMonthlyPrice']);
        $this->assertSame(1, $updatedProject['dataRetentionTimeInDays']);

        $this->assertArrayHasKey('isBYODB', $updatedProject);
        $this->assertFalse($updatedProject['isBYODB']);

        $this->client->removeUserFeature($this->normalUser['email'], 'can-update-project-settings');
    }

    public function testProjectUpdatePermissions(): void
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
            'dataRetentionTimeInDays' => 1,
        ]);

        $this->client->addUserToProject($project['id'], [
            'email' => EnvVariableHelper::getKbcTestAdminEmail(),
        ]);

        $client = $this->getClient([
            'token' => EnvVariableHelper::getKbcTestAdminToken(),
            'url' => EnvVariableHelper::getKbcManageApiUrl(),
            'backoffMaxTries' => 1,
        ]);

        // update
        $newName = 'new name';
        $project = $client->updateProject($project['id'], [
            'name' => $newName,
        ]);
        $this->assertEquals($newName, $project['name']);

        // change type should not be allowed
        try {
            $client->updateProject($project['id'], [
                'type' => 'production',
            ]);
            $this->fail('change type should not be allowed');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        // change expiration should not be allowed
        try {
            $client->updateProject($project['id'], [
                'expirationDays' => 23423,
            ]);
            $this->fail('change expiration should not be allowed');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        // change monthly fee should not be allowed
        try {
            $client->updateProject($project['id'], [
                'billedMonthlyPrice' => 23423,
            ]);
            $this->fail('change billedMonthlyPrice should not be allowed');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }
    }

    public function testChangeProjectOrganization(): void
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->createRedshiftProjectForClient($this->client, $organization['id'], [
            'name' => 'My test',
        ]);

        $newOrganization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org 2',
        ]);

        $changedProject = $this->client->changeProjectOrganization($project['id'], $newOrganization['id']);
        $this->assertEquals($newOrganization['id'], $changedProject['organization']['id']);
    }

    public function testChangeProjectLimits(): void
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->createRedshiftProjectForClient($this->client, $organization['id'], [
            'name' => 'My test',
        ]);

        $limits = [
            [
                'name' => 'goodData.prodTokenEnabled',
                'value' => 0,
            ],
            [
                'name' => 'goodData.usersCount',
                'value' => 20,
            ],
        ];
        $project = $this->client->setProjectLimits($project['id'], $limits);
        $this->assertEquals($limits[0], $project['limits']['goodData.prodTokenEnabled']);
        $this->assertEquals($limits[1], $project['limits']['goodData.usersCount']);

        $project = $this->client->getProject($project['id']);
        $this->assertEquals($limits[0], $project['limits']['goodData.prodTokenEnabled']);
        $this->assertEquals($limits[1], $project['limits']['goodData.usersCount']);
    }

    public function testDeleteProjectLimit(): void
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->createRedshiftProjectForClient($this->client, $organization['id'], [
            'name' => 'My test',
        ]);

        $limits = [
            [
                'name' => 'goodData.prodTokenEnabled',
                'value' => 0,
            ],
            [
                'name' => 'goodData.usersCount',
                'value' => 20,
            ],
        ];

        $this->client->setProjectLimits($project['id'], $limits);
        $this->client->removeProjectLimit($project['id'], 'goodData.usersCount');

        $project = $this->client->getProject($project['id']);

        $this->assertEquals($limits[0], $project['limits']['goodData.prodTokenEnabled']);
        $this->assertArrayNotHasKey('goodData.usersCount', $project['limits']);
    }

    public function testChangeProjectLimitsWithSuperToken(): void
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $projectAfterCreation = $this->createRedshiftProjectForClient($this->client, $organization['id'], [
            'name' => 'My test',
        ]);

        try {
            $clientWithSuperApiToken = $this->getClient([
                'token' => EnvVariableHelper::getKbcSuperApiToken(),
                'url' => EnvVariableHelper::getKbcManageApiUrl(),
                'backoffMaxTries' => 0,
            ]);

            $clientWithSuperApiToken->setProjectLimits($projectAfterCreation['id'], []);
            $this->fail('This should fail.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }
    }

    public function testAddNonexistentFeature(): void
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->createRedshiftProjectForClient($this->client, $organization['id'], [
            'name' => 'My test',
        ]);

        $featureName = 'random-feature-' . $this->getRandomFeatureSuffix();

        try {
            $this->client->addProjectFeature($project['id'], $featureName);
            $this->fail('Feature not found');
        } catch (ClientException $e) {
            $this->assertEquals(404, $e->getCode());
        }
    }

    public function testAddProjectFeatureTwice(): void
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);
        $project = $this->createRedshiftProjectForClient($this->client, $organization['id'], [
            'name' => 'My test',
        ]);

        $project = $this->client->getProject($project['id']);

        $initialFeaturesCount = count($project['features']);

        $newFeature = 'random-feature-' . $this->getRandomFeatureSuffix();
        $this->client->createFeature($newFeature, 'project', $newFeature, $newFeature);
        $this->client->addProjectFeature($project['id'], $newFeature);

        $project = $this->client->getProject($project['id']);

        $this->assertCount($initialFeaturesCount + 1, $project['features']);

        try {
            $this->client->addProjectFeature($project['id'], $newFeature);
            $this->fail('Feature already added');
        } catch (ClientException $e) {
            $this->assertEquals(422, $e->getCode());
        }

        $project = $this->client->getProject($project['id']);

        $this->assertCount($initialFeaturesCount + 1, $project['features']);
    }

    public function testAddRemoveProjectFeatures(): void
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->createRedshiftProjectForClient($this->client, $organization['id'], [
            'name' => 'My test',
        ]);

        $firstFeatureName = 'first-feature-' . $this->getRandomFeatureSuffix();

        $this->assertNotContains($firstFeatureName, $project['features']);

        $this->client->createFeature($firstFeatureName, 'project', $firstFeatureName, $firstFeatureName);
        $this->client->addProjectFeature($project['id'], $firstFeatureName);
        $project = $this->client->getProject($project['id']);

        $this->assertContains($firstFeatureName, $project['features']);

        $secondFeatureName = 'second-feature-' . $this->getRandomFeatureSuffix();
        $this->client->createFeature($secondFeatureName, 'project', $secondFeatureName, $secondFeatureName);
        $this->client->addProjectFeature($project['id'], $secondFeatureName);
        $project = $this->client->getProject($project['id']);
        $this->assertGreaterThanOrEqual(2, count($project['features']));

        $this->client->removeProjectFeature($project['id'], $secondFeatureName);
        $project = $this->client->getProject($project['id']);
        $this->assertContains($firstFeatureName, $project['features']);
        $this->assertNotContains($secondFeatureName, $project['features']);
    }

    public function testCreateProjectStorageTokenWithoutPermissions(): void
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->createRedshiftProjectForClient($this->client, $organization['id'], [
            'name' => 'My test',
        ]);

        // token without permissions
        $token = $this->client->createProjectStorageToken($project['id'], [
            'description' => 'test',
            'expiresIn' => 60,
        ]);

        $client = $this->getStorageClient([
            'url' => EnvVariableHelper::getKbcManageApiUrl(),
            'token' => $token['token'],
        ]);

        $verified = $client->verifyToken();
        $this->assertEquals($project['id'], $verified['owner']['id']);
        $this->assertFalse($verified['canManageBuckets']);
        $this->assertFalse($verified['canManageTokens']);
        $this->assertFalse($verified['canReadAllFileUploads']);
        $this->assertFalse($verified['canPurgeTrash']);
        $this->assertEmpty($verified['bucketPermissions']);
    }

    public function testCreateProjectStorageTokenUsingApplicationTokenWithScope(): void
    {
        $objectName = $this->generateDescriptionForTestObject();
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => $objectName,
        ]);
        $project = $this->createRedshiftProjectForClient($this->client, $organization['id'], [
            'name' => $objectName,
        ]);

        $manageClient = new Client([
            'token' => EnvVariableHelper::getKbcManageApiSuperTokenWithStorageTokensScope(),
            'url' => EnvVariableHelper::getKbcManageApiUrl(),
            'backoffMaxTries' => 0,
        ]);
        $storageToken = $manageClient->createProjectStorageToken($project['id'], [
            'description' => $objectName,
            'expiresIn' => 60,
            'canManageBuckets' => true,
            'canReadAllFileUploads' => true,
            'canPurgeTrash' => true,
        ]);
        $storageClient = $this->getStorageClient(
            [
                'url' => EnvVariableHelper::getKbcManageApiUrl(),
                'token' => $storageToken['token'],
            ]
        );
        $verified = $storageClient->verifyToken();
        $this->assertEquals($project['id'], $verified['owner']['id']);
        $this->assertTrue($verified['canManageBuckets']);
        $this->assertFalse($verified['canManageTokens']);
        $this->assertTrue($verified['canReadAllFileUploads']);
        $this->assertTrue($verified['canPurgeTrash']);
        $this->assertEmpty($verified['bucketPermissions']);
    }

    public function testCreateProjectStorageTokenWithMorePermissions(): void
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->createRedshiftProjectForClient($this->client, $organization['id'], [
            'name' => 'My test',
        ]);

        // new token with more permissions
        $token = $this->client->createProjectStorageToken($project['id'], [
            'description' => 'test',
            'expiresIn' => 60,
            'canManageBuckets' => true,
            'canReadAllFileUploads' => true,
            'canPurgeTrash' => true,
        ]);

        $client = $this->getStorageClient([
            'url' => EnvVariableHelper::getKbcManageApiUrl(),
            'token' => $token['token'],
        ]);

        $verified = $client->verifyToken();
        $this->assertEquals($project['id'], $verified['owner']['id']);
        $this->assertTrue($verified['canManageBuckets']);
        $this->assertFalse($verified['canManageTokens']);
        $this->assertTrue($verified['canReadAllFileUploads']);
        $this->assertTrue($verified['canPurgeTrash']);
    }

    public function testCreateProjectStorageTokenWithBucketPermissions(): void
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
            'dataRetentionTimeInDays' => 1,
        ]);

        $tokenWithManageBucketsPermission = $this->client->createProjectStorageToken($project['id'], [
            'description' => 'test',
            'expiresIn' => 60,
            'canManageBuckets' => true,
            'canReadAllFileUploads' => true,
        ]);

        $client = $this->getStorageClient([
            'url' => EnvVariableHelper::getKbcManageApiUrl(),
            'token' => $tokenWithManageBucketsPermission['token'],
        ]);

        // test bucket permissions
        // let's create some bucket with previous token
        $newBucketId = $client->createBucket('test', 'in');

        $tokenWithReadPermissionToOneBucket = $this->client->createProjectStorageToken($project['id'], [
            'description' => 'test',
            'expiresIn' => 60,
            'bucketPermissions' => [
                $newBucketId => 'read',
            ],
        ]);

        $clientWithReadBucketPermission = $this->getStorageClient([
            'url' => EnvVariableHelper::getKbcManageApiUrl(),
            'token' => $tokenWithReadPermissionToOneBucket['token'],
        ]);

        $verified = $clientWithReadBucketPermission->verifyToken();
        $this->assertEquals($project['id'], $verified['owner']['id']);
        $this->assertFalse($verified['canManageBuckets']);
        $this->assertFalse($verified['canManageTokens']);
        $this->assertFalse($verified['canReadAllFileUploads']);
        $this->assertEquals([$newBucketId => 'read'], $verified['bucketPermissions']);
    }

    public function testCreateProjectStorageTokenWithMangeTokensPermissionAndComponentAccess(): void
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->createRedshiftProjectForClient($this->client, $organization['id'], [
            'name' => 'My test',
        ]);

        // new token with canManageTokens
        $token = $this->client->createProjectStorageToken($project['id'], [
            'description' => 'test',
            'expiresIn' => 60,
            'canManageBuckets' => true,
            'canReadAllFileUploads' => true,
            'canManageTokens' => true,
        ]);

        $client = $this->getStorageClient([
            'url' => EnvVariableHelper::getKbcManageApiUrl(),
            'token' => $token['token'],
        ]);

        $verified = $client->verifyToken();
        $this->assertEquals($project['id'], $verified['owner']['id']);
        $this->assertTrue($verified['canManageBuckets']);
        $this->assertTrue($verified['canManageTokens']);
        $this->assertTrue($verified['canReadAllFileUploads']);

        $requestedComponents = ['component1', 'component2', 'component3'];
        $token2 = $this->client->createProjectStorageToken($project['id'], [
            'description' => 'test',
            'expiresIn' => 60,
            'canManageBuckets' => true,
            'canReadAllFileUploads' => true,
            'componentAccess' => $requestedComponents,
        ]);

        $client2 = $this->getStorageClient([
            'url' => EnvVariableHelper::getKbcManageApiUrl(),
            'token' => $token2['token'],
        ]);

        $verified2 = $client2->verifyToken();
        $this->assertEquals($project['id'], $verified['owner']['id']);
        $this->assertTrue($verified2['canManageBuckets']);
        $this->assertFalse($verified2['canManageTokens']);
        $this->assertTrue($verified2['canReadAllFileUploads']);
        $this->assertEquals($requestedComponents, $verified2['componentAccess']);
    }

    public function testSuperAdminCanDisableAndEnableProject(): void
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $this->client->addUserToOrganization($organization['id'], ['email' => $this->normalUser['email']]);
        $this->client->removeUserFromOrganization($organization['id'], $this->superAdmin['id']);
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);
        $this->client->removeUserFromMaintainer($this->testMaintainerId, $this->superAdmin['id']);

        $project = $this->createRedshiftProjectForClient($this->normalUserClient, $organization['id'], [
            'name' => 'My test',
        ]);

        $this->assertFalse($project['isDisabled']);

        $storageToken = $this->normalUserClient->createProjectStorageToken($project['id'], [
            'description' => 'test',
        ]);

        $disableReason = 'Disable test';
        $this->client->disableProject($project['id'], [
            'disableReason' => $disableReason,
            'estimatedEndTime' => '+1 hour',
        ]);

        $project = $this->client->getProject($project['id']);
        $this->assertTrue($project['isDisabled']);

        $this->assertEquals($disableReason, $project['disabled']['reason']);
        $this->assertNotEmpty($project['disabled']['estimatedEndTime']);

        $client = $this->getStorageClient([
            'url' => EnvVariableHelper::getKbcManageApiUrl(),
            'token' => $storageToken['token'],
            'backoffMaxTries' => 1,
        ]);

        try {
            $client->verifyToken();
            $this->fail('Token should be disabled');
        } catch (StorageApiClientException $e) {
            $this->assertEquals('MAINTENANCE', $e->getStringCode());
            $this->assertSame($e->getMessage(), $disableReason);
        }

        $this->client->enableProject($project['id']);

        $project = $this->client->getProject($project['id']);
        $this->assertFalse($project['isDisabled']);

        $storageToken = $client->verifyToken();
        $this->assertNotEmpty($storageToken);
    }

    public function testNormalAdminWithoutFeatureCannotListDeletedProjects(): void
    {
        $tokenInfo = $this->normalUserClient->verifyToken();
        $this->assertArrayHasKey('user', $tokenInfo);
        $user = $tokenInfo['user'];
        if (in_array(self::CAN_MANAGE_DELETED_PROJECTS_FEATURE_NAME, $user['features'], true)) {
            $this->client->removeUserFeature($user['email'], self::CAN_MANAGE_DELETED_PROJECTS_FEATURE_NAME);
        }

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('You don\'t have access to the resource. Token must belong to the super admin or admin has to have feature "can-manage-deleted-projects".');
        $this->normalUserClient->listDeletedProjects();
    }

    private function getTestClientWithFeature(string $case, string $feature): Client
    {
        switch ($case) {
            case 'SuperAdmin':
                $testClient = $this->client;
                break;
            case 'AdminWithFeature':
                $testClient = $this->normalUserClient;
                $tokenInfo = $testClient->verifyToken();
                $this->assertArrayHasKey('user', $tokenInfo);
                $user = $tokenInfo['user'];
                if (!in_array($feature, $user['features'], true)) {
                    $this->client->addUserFeature($user['email'], $feature);
                }
                break;
            default:
                throw new InvalidArgumentException(sprintf('Unknown case "%s"', $case));
        }
        return $testClient;
    }

    public static function deleteProjectsClientProvider(): Generator
    {
        yield 'SuperAdmin' => ['SuperAdmin'];
        yield 'AdminWithFeature' => ['AdminWithFeature'];
    }

    /**
     * @dataProvider deleteProjectsClientProvider
     */
    public function testListDeletedProjects(string $case): void
    {
        $testClient = $this->getTestClientWithFeature($case, self::CAN_MANAGE_DELETED_PROJECTS_FEATURE_NAME);
        $organizations = [];

        for ($i = 0; $i < 2; ++$i) {
            $organization = $this->initTestOrganization();
            $organizations[] = $organization;
            $project = $this->initTestProject($organization['id']);

            // check if the project is listed in organization projects
            $projects = $this->client->listOrganizationProjects($organization['id']);

            $this->assertCount(1, $projects);
            $this->assertEquals($project['id'], $projects[0]['id']);
            $this->assertEquals($project['name'], $projects[0]['name']);

            // delete project
            $this->client->deleteProject($project['id']);

            $projects = $this->client->listOrganizationProjects($organization['id']);
            $this->assertEmpty($projects);
        }

        // all deleted projects
        $projects = $testClient->listDeletedProjects();
        $this->assertGreaterThan($i + 1, $projects);

        // organization deleted projects
        $params = [
            'organizationId' => $organization['id'],
        ];

        $projects = $testClient->listDeletedProjects($params);
        $this->assertCount(1, $projects);

        // name filter test
        $params = [
            'organizationId' => $organization['id'],
            'name' => $project['name'],
        ];

        $projects = $testClient->listDeletedProjects($params);
        $this->assertGreaterThan(0, count($projects));

        $params = [
            'organizationId' => $organization['id'],
            'name' => $project['name'],
        ];

        $projects = $testClient->listDeletedProjects($params);
        $this->assertGreaterThan(0, count($projects));

        $params = [
            'organizationId' => $organization['id'],
            'name' => sha1($project['name']),
        ];

        $projects = $testClient->listDeletedProjects($params);
        $this->assertCount(0, $projects);

        foreach ($organizations as $organization) {
            $this->client->deleteOrganization($organization['id']);
        }
    }

    public function testListDeletedProjectsPaging(): void
    {
        $organization = $this->initTestOrganization();

        $project1 = $this->initTestProject($organization['id']);
        $project2 = $this->initTestProject($organization['id']);
        $project3 = $this->initTestProject($organization['id']);

        // check if the project is listed in organization projects
        $projects = $this->client->listOrganizationProjects($organization['id']);

        $this->assertCount(3, $projects);

        // delete project
        $this->client->deleteProject($project1['id']);
        $this->client->deleteProject($project2['id']);
        $this->client->deleteProject($project3['id']);

        $projects = $this->client->listOrganizationProjects($organization['id']);
        $this->assertEmpty($projects);

        // try paging
        $params = [
            'organizationId' => $organization['id'],
        ];

        $projects = $this->client->listDeletedProjects($params);
        $this->assertCount(3, $projects);

        $params = [
            'organizationId' => $organization['id'],
            'offset' => 0,
            'limit' => 2,
        ];

        $projects = $this->client->listDeletedProjects($params);
        $this->assertCount(2, $projects);

        $params = [
            'organizationId' => $organization['id'],
            'offset' => 2,
            'limit' => 2,
        ];

        $projects = $this->client->listDeletedProjects($params);
        $this->assertCount(1, $projects);

        $params = [
            'organizationId' => $organization['id'],
            'offset' => 4,
            'limit' => 2,
        ];

        $projects = $this->client->listDeletedProjects($params);
        $this->assertCount(0, $projects);

        $this->client->deleteOrganization($organization['id']);
    }

    public function testDeletedProjectsErrors(): void
    {
        $organization = $this->initTestOrganization();
        $project = $this->initTestProject($organization['id']);

        $this->client->deleteProject($project['id']);
        $this->client->deleteOrganization($organization['id']);

        // deleted organization
        try {
            $this->client->listDeletedProjects(['organizationId' => $organization['id']]);

            $this->fail('List deleted projects of deleted organization should produce error');
        } catch (ClientException $e) {
            $this->assertSame(
                sprintf('Organization "%s" not found', $organization['id']),
                $e->getMessage()
            );
            $this->assertEquals(400, $e->getCode());
        }

        // permission validation
        $client = $this->getClient([
            'token' => EnvVariableHelper::getKbcTestAdminToken(),
            'url' => EnvVariableHelper::getKbcManageApiUrl(),
        ]);

        try {
            $client->listDeletedProjects();
            $this->fail('List deleted projects with non super admint oken should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        try {
            $client->undeleteProject($project['id']);

            $this->fail('Undelete projects with non super admint oken should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }
    }

    /**
     * @dataProvider deleteProjectsClientProvider
     */
    public function testProjectUnDelete(string $case): void
    {
        $testClient = $this->getTestClientWithFeature($case, self::CAN_MANAGE_DELETED_PROJECTS_FEATURE_NAME);
        $organization = $this->initTestOrganization();

        $project = $this->initTestProject($organization['id']);

        $this->client->deleteProject($project['id']);

        $params = [
            'organizationId' => $organization['id'],
        ];

        $projects = $this->client->listDeletedProjects($params);
        $this->assertCount(1, $projects);

        $this->client->undeleteProject($project['id']);

        $projects = $this->client->listDeletedProjects($params);
        $this->assertCount(0, $projects);

        $projects = $this->client->listOrganizationProjects($organization['id']);
        $this->assertCount(1, $projects);

        $this->client->deleteProject($project['id']);

        $projects = $this->client->listDeletedProjects($params);
        $this->assertCount(1, $projects);

        $this->client->deleteOrganization($organization['id']);
    }

    /**
     * @dataProvider deleteProjectsClientProvider
     */
    public function testDeletedProjectDetail(string $case): void
    {
        $testClient = $this->getTestClientWithFeature($case, self::CAN_MANAGE_DELETED_PROJECTS_FEATURE_NAME);
        $organization = $this->initTestOrganization();

        $project = $this->initTestProject($organization['id']);

        $this->client->deleteProject($project['id']);

        $deletedProject = $testClient->getDeletedProject($project['id']);
        $this->assertTrue($deletedProject['isDeleted']);
        $this->assertFalse($deletedProject['isPurged']);
        $this->assertNull($deletedProject['purgedTime']);

        $clientWithScope = $this->createSuperManageTokenWithDeletedProjectsReadScopeClient();
        $deletedProjectFromClientWithScope = $clientWithScope->getDeletedProject($project['id']);
        $this->assertSame(
            $deletedProject,
            $deletedProjectFromClientWithScope,
            'Response for both clients should be the same'
        );

        $clientWithIncorrectScope = $this->createSuperManageTokenWithProjectsReadScopeClient();
        $this->expectExceptionMessage('You don\'t have access to the resource.');
        $this->expectException(ClientException::class);
        $clientWithIncorrectScope->getDeletedProject($project['id']);
    }

    public function testActiveProjectUnDelete(): void
    {
        $organization = $this->initTestOrganization();

        $project = $this->initTestProject($organization['id']);

        $params = [
            'organizationId' => $organization['id'],
        ];

        try {
            $this->client->undeleteProject($project['id']);
            $this->fail('Undelete active projects should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
        }

        $projects = $this->client->listOrganizationProjects($organization['id']);
        $this->assertCount(1, $projects);

        $this->client->deleteProject($project['id']);

        $projects = $this->client->listDeletedProjects($params);
        $this->assertCount(1, $projects);

        $this->client->deleteOrganization($organization['id']);
    }

    public function testNonExistingProjectUnDelete(): void
    {
        $organization = $this->initTestOrganization();

        try {
            $this->client->undeleteProject(PHP_INT_MAX);
            $this->fail('Undelete active projects should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(404, $e->getCode());
        }

        $projects = $this->client->listOrganizationProjects($organization['id']);
        $this->assertCount(0, $projects);

        $this->client->deleteOrganization($organization['id']);
    }

    /**
     * @dataProvider deleteProjectsClientProvider
     */
    public function testProjectWithExpirationUnDelete(string $case): void
    {
        $testClient = $this->getTestClientWithFeature($case, self::CAN_MANAGE_DELETED_PROJECTS_FEATURE_NAME);

        $organization = $this->initTestOrganization();

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
            'type' => 'demo',
            'dataRetentionTimeInDays' => 1,
        ]);
        $projectId = $project['id'];

        $project = $this->client->getProject($project['id']);
        $this->assertEquals('demo', $project['type']);
        $this->assertNotEmpty($project['expires']);

        $this->client->deleteProject($project['id']);

        $params = [
            'organizationId' => $organization['id'],
        ];

        $projects = $testClient->listDeletedProjects($params);
        $this->assertCount(1, $projects);

        $testClient->undeleteProject($project['id']);

        $projects = $testClient->listDeletedProjects($params);
        $this->assertCount(0, $projects);

        $projects = $this->client->listOrganizationProjects($organization['id']);
        $this->assertCount(1, $projects);

        $project = reset($projects);
        $this->assertEmpty($project['expires']);
        $this->assertEquals($projectId, $project['id']);

        $this->client->deleteProject($project['id']);

        $projects = $testClient->listDeletedProjects($params);
        $this->assertCount(1, $projects);

        $this->client->deleteOrganization($organization['id']);
    }

    /**
     * @dataProvider deleteProjectsClientProvider
     */
    public function testProjectUnDeleteWithExpiration(string $case): void
    {
        $testClient = $this->getTestClientWithFeature($case, self::CAN_MANAGE_DELETED_PROJECTS_FEATURE_NAME);
        $organization = $this->initTestOrganization();

        $project = $this->initTestProject($organization['id']);
        $this->assertEmpty($project['expires']);

        $this->client->deleteProject($project['id']);

        $params = [
            'organizationId' => $organization['id'],
        ];

        $projects = $testClient->listDeletedProjects($params);
        $this->assertCount(1, $projects);

        $testClient->undeleteProject($project['id'], ['expirationDays' => 7]);

        $projects = $testClient->listDeletedProjects($params);
        $this->assertCount(0, $projects);

        $projects = $this->client->listOrganizationProjects($organization['id']);
        $this->assertCount(1, $projects);

        $project = reset($projects);
        $this->assertNotEmpty($project['expires']);

        $this->client->deleteProject($project['id']);

        $projects = $testClient->listDeletedProjects($params);
        $this->assertCount(1, $projects);

        $this->client->deleteOrganization($organization['id']);
    }

    public function testProjectDataRetention(): void
    {
        $organization = $this->initTestOrganization();
        $project = $this->initTestProject($organization['id']);

        $this->assertSame(1, (int) $project['dataRetentionTimeInDays']);

        // verify that normal users can't update data retention time
        try {
            $this->normalUserClient->updateProject($project['id'], ['dataRetentionTimeInDays' => 0]);
            $this->fail('Must be a super admin to update data retention period');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $project = $this->client->updateProject($project['id'], ['dataRetentionTimeInDays' => 0]);
        $this->assertSame(0, (int) $project['dataRetentionTimeInDays']);
    }

    public function testLastMemberCanLeaveProject(): void
    {
        $organization = $this->initTestOrganization();
        $project = $this->initTestProject($organization['id']);

        $users = $this->client->listProjectUsers($project['id']);
        $this->assertCount(1, $users);

        $this->client->removeUserFromProject($project['id'], $this->superAdmin['id']);

        $users = $this->client->listProjectUsers($project['id']);
        $this->assertCount(0, $users);
    }

    public function testMaintainerAdminCannotDisableAndEnableProject(): void
    {
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->createRedshiftProjectForClient($this->client, $organization['id'], [
            'name' => 'My test',
        ]);

        // disable test
        try {
            $this->normalUserClient->disableProject($project['id'], ['disableReason' => 'Disable test']);
            $this->fail('Maintainer admin should not be able to disable project');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $project = $this->client->getProject($project['id']);
        $this->assertFalse($project['isDisabled']);

        // enable test
        $this->client->disableProject($project['id'], ['disableReason' => 'Disable test']);

        try {
            $this->normalUserClient->enableProject($project['id']);
            $this->fail('Maintainer admin should not be able to enable project');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $project = $this->client->getProject($project['id']);
        $this->assertTrue($project['isDisabled']);
    }

    public function testOrganizationAdminCannotDisableAndEnableProject(): void
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $this->client->addUserToOrganization($organization['id'], ['email' => $this->normalUser['email']]);

        $project = $this->createRedshiftProjectForClient($this->client, $organization['id'], [
            'name' => 'My test',
        ]);

        // disable test
        try {
            $this->normalUserClient->disableProject($project['id'], ['disableReason' => 'Disable test']);
            $this->fail('Organization admin should not be able to disable project');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $project = $this->client->getProject($project['id']);
        $this->assertFalse($project['isDisabled']);

        // enable test
        $this->client->disableProject($project['id'], ['disableReason' => 'Disable test']);

        try {
            $this->normalUserClient->enableProject($project['id']);
            $this->fail('Organization admin should not be able to enable project');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $project = $this->client->getProject($project['id']);
        $this->assertTrue($project['isDisabled']);
    }

    public function testProjectAdminCannotDisableAndEnableProject(): void
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->createRedshiftProjectForClient($this->client, $organization['id'], [
            'name' => 'My test',
        ]);

        $this->client->addUserToProject($project['id'], ['email' => $this->normalUser['email']]);

        // disable test
        try {
            $this->normalUserClient->disableProject($project['id'], ['disableReason' => 'Disable test']);
            $this->fail('Organization admin should not be able to disable project');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $project = $this->client->getProject($project['id']);
        $this->assertFalse($project['isDisabled']);

        // enable test
        $this->client->disableProject($project['id'], ['disableReason' => 'Disable test']);

        try {
            $this->normalUserClient->enableProject($project['id']);
            $this->fail('Organization admin should not be able to enable project');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $project = $this->client->getProject($project['id']);
        $this->assertTrue($project['isDisabled']);
    }

    public function testRandomAdminCannotDisableAndEnableProject(): void
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->createRedshiftProjectForClient($this->client, $organization['id'], [
            'name' => 'My test',
        ]);

        // disable test
        try {
            $this->normalUserClient->disableProject($project['id'], ['disableReason' => 'Disable test']);
            $this->fail('Organization admin should not be able to disable project');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $project = $this->client->getProject($project['id']);
        $this->assertFalse($project['isDisabled']);

        // enable test
        $this->client->disableProject($project['id'], ['disableReason' => 'Disable test']);

        try {
            $this->normalUserClient->enableProject($project['id']);
            $this->fail('Organization admin should not be able to enable project');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $project = $this->client->getProject($project['id']);
        $this->assertTrue($project['isDisabled']);
    }

    /**
     * @dataProvider addUserToProjectWithRoleData
     */
    public function testAddUserToProjectWithRole(string $role): void
    {
        $organization = $this->initTestOrganization();
        $project = $this->initTestProject($organization['id']);

        $this->client->addUserToProject($project['id'], [
            'email' => $this->normalUser['email'],
            'role' => $role,
        ]);

        $member = $this->findProjectUser($project['id'], $this->normalUser['email']);
        $this->assertNotNull($member);
        $this->assertEquals($role, $member['role']);
    }

    public function testAddUserToProjectWithInvalidRole(): void
    {
        $organization = $this->initTestOrganization();
        $project = $this->initTestProject($organization['id']);

        try {
            $this->client->addUserToProject($project['id'], [
                'email' => $this->normalUser['email'],
                'role' => 'invalid-role',
            ]);
            $this->fail('Create project membership with invalid role should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertRegExp('/Role .* is not valid. Allowed roles are: admin, guest/', $e->getMessage());
            $this->assertStringContainsString('invalid-role', $e->getMessage());
        }

        $member = $this->findProjectUser($project['id'], $this->normalUser['email']);
        $this->assertNull($member);
    }

    public function testMembershipRoleChange(): void
    {
        $organization = $this->initTestOrganization();
        $project = $this->initTestProject($organization['id']);

        $this->client->addUserToProject($project['id'], ['email' => $this->normalUser['email'],]);

        $member = $this->findProjectUser($project['id'], $this->normalUser['email']);
        $this->assertNotNull($member);
        $this->assertEquals('admin', $member['role']);

        $this->client->updateUserProjectMembership($project['id'], $this->normalUser['id'], ['role' => ProjectRole::GUEST]);

        $member = $this->findProjectUser($project['id'], $this->normalUser['email']);
        $this->assertNotNull($member);
        $this->assertEquals('guest', $member['role']);

        $this->client->updateUserProjectMembership($project['id'], $this->normalUser['id'], ['role' => 'admin']);

        $member = $this->findProjectUser($project['id'], $this->normalUser['email']);
        $this->assertNotNull($member);
        $this->assertEquals('admin', $member['role']);
    }

    public function testPayAsYoGoDetails(): void
    {
        $feature = self::PAY_AS_YOU_GO_PROJECT_FEATURE_NAME;

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => __CLASS__,
        ]);

        $project = $this->createRedshiftProjectForClient($this->client, $organization['id'], [
            'name' => __METHOD__,
        ]);

        $projectId = $project['id'];

        $project = $this->client->getProject($projectId);
        $this->assertArrayNotHasKey('payAsYouGo', $project);

        $projects = $this->client->listOrganizationProjects($organization['id']);
        $this->assertCount(1, $projects);

        $project = reset($projects);
        $this->assertEquals($projectId, $project['id']);
        $this->assertArrayNotHasKey('payAsYouGo', $project);

        $this->client->addProjectFeature($projectId, $feature);

        $project = $this->client->getProject($projectId);
        $this->assertArrayHasKey('payAsYouGo', $project);

        $payAsYouGo = $project['payAsYouGo'];
        $this->assertIsNumeric($payAsYouGo['purchasedCredits']);

        $projects = $this->client->listOrganizationProjects($organization['id']);
        $this->assertCount(1, $projects);

        $project = reset($projects);
        $this->assertEquals($projectId, $project['id']);
        $this->assertArrayHasKey('payAsYouGo', $project);

        $payAsYouGo = $project['payAsYouGo'];
        $this->assertIsNumeric($payAsYouGo['purchasedCredits']);
    }

    public function testCreditsCannotBeGivenToNonPaygoProject(): void
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $this->client->addUserToOrganization($organization['id'], ['email' => $this->normalUser['email']]);

        $project = $this->createRedshiftProjectForClient($this->normalUserClient, $organization['id'], [
            'name' => 'My test',
        ]);

        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('is not Pay As You Go project');

        $this->client->giveProjectCredits($project['id'], [
            'amount' => 100,
            'description' => 'Promo',
        ]);
    }

    /**
     * @dataProvider provideProjectCredits
     * @param int|float $givenCredits
     */
    public function testSuperAdminCanGiveProjectCredits(int|float $givenCredits): void
    {
        $this->client->removeUserFeature($this->superAdmin['email'], self::PAY_AS_YOU_GO_CREDITS_ADMIN_FEATURE_NAME);

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $this->client->addUserToOrganization($organization['id'], ['email' => $this->normalUser['email']]);

        $project = $this->createRedshiftProjectForClient($this->normalUserClient, $organization['id'], [
            'name' => 'My test',
        ]);

        $this->client->addProjectFeature($project['id'], self::PAY_AS_YOU_GO_PROJECT_FEATURE_NAME);

        $project = $this->client->getProject($project['id']);
        $purchasedCredits = $project['payAsYouGo']['purchasedCredits'];

        $response = $this->client->giveProjectCredits($project['id'], [
            'amount' => $givenCredits,
            'description' => 'Promo',
        ]);

        $this->assertArrayHasKey('id', $response);
        $this->assertIsInt($response['id']);
        $this->assertArrayHasKey('creditsAmount', $response);
        $this->assertSame($givenCredits, $response['creditsAmount']);
        $this->assertArrayHasKey('moneyAmount', $response);
        $this->assertNull($response['moneyAmount']);
        $this->assertArrayHasKey('idStripeInvoice', $response);
        $this->assertNull($response['idStripeInvoice']);
        $this->assertArrayHasKey('description', $response);
        $this->assertSame('Promo', $response['description']);
        $this->assertArrayHasKey('created', $response);
        $this->assertNotNull($response['created']);

        $project = $this->client->getProject($project['id']);
        $this->assertSame($purchasedCredits + $givenCredits, $project['payAsYouGo']['purchasedCredits']);
    }

    /**
     * @dataProvider provideProjectCredits
     * @param int|float $givenCredits
     */
    public function testAdminWithFeatureCanGiveProjectCredits(int|float $givenCredits): void
    {
        $this->client->removeUserFeature($this->normalUser['email'], self::PAY_AS_YOU_GO_CREDITS_ADMIN_FEATURE_NAME);
        $this->client->addUserFeature($this->normalUser['email'], self::PAY_AS_YOU_GO_CREDITS_ADMIN_FEATURE_NAME);

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $this->client->addUserToOrganization($organization['id'], ['email' => $this->normalUser['email']]);

        $project = $this->createRedshiftProjectForClient($this->normalUserClient, $organization['id'], [
            'name' => 'My test',
        ]);

        $this->client->addProjectFeature($project['id'], self::PAY_AS_YOU_GO_PROJECT_FEATURE_NAME);

        $project = $this->client->getProject($project['id']);
        $purchasedCredits = $project['payAsYouGo']['purchasedCredits'];

        $response = $this->client->giveProjectCredits($project['id'], [
            'amount' => $givenCredits,
            'description' => 'Promo',
        ]);

        $this->assertArrayHasKey('id', $response);
        $this->assertIsInt($response['id']);
        $this->assertArrayHasKey('creditsAmount', $response);
        $this->assertSame($givenCredits, $response['creditsAmount']);
        $this->assertArrayHasKey('moneyAmount', $response);
        $this->assertNull($response['moneyAmount']);
        $this->assertArrayHasKey('idStripeInvoice', $response);
        $this->assertNull($response['idStripeInvoice']);
        $this->assertArrayHasKey('description', $response);
        $this->assertSame('Promo', $response['description']);
        $this->assertArrayHasKey('created', $response);
        $this->assertNotNull($response['created']);

        $project = $this->client->getProject($project['id']);
        $this->assertSame($purchasedCredits + $givenCredits, $project['payAsYouGo']['purchasedCredits']);
    }

    public function provideProjectCredits(): Generator
    {
        yield 'integer' => [
            100,
        ];
        yield 'decimal' => [
            100.234,
        ];
        yield 'decimal <1' => [
            0.0123456,
        ];
    }

    public function testMaintainerAdminCannotGiveProjectCredits(): void
    {
        $this->client->removeUserFeature($this->normalUser['email'], self::PAY_AS_YOU_GO_CREDITS_ADMIN_FEATURE_NAME);

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->createRedshiftProjectForClient($this->client, $organization['id'], [
            'name' => 'My test',
        ]);

        $this->client->addProjectFeature($project['id'], self::PAY_AS_YOU_GO_PROJECT_FEATURE_NAME);

        $project = $this->client->getProject($project['id']);
        $purchasedCredits = $project['payAsYouGo']['purchasedCredits'];

        try {
            $this->normalUserClient->giveProjectCredits($project['id'], [
                'amount' => 100,
                'description' => 'Promo',
            ]);
            $this->fail('Maintainer admin should not be able to give credits');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $project = $this->client->getProject($project['id']);
        $this->assertSame($purchasedCredits, $project['payAsYouGo']['purchasedCredits']);
    }

    public function testOrganizationAdminCannotGiveProjectCredits(): void
    {
        $this->client->removeUserFeature($this->normalUser['email'], self::PAY_AS_YOU_GO_CREDITS_ADMIN_FEATURE_NAME);

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $this->client->addUserToOrganization($organization['id'], ['email' => $this->normalUser['email']]);

        $project = $this->createRedshiftProjectForClient($this->client, $organization['id'], [
            'name' => 'My test',
        ]);

        $this->client->addProjectFeature($project['id'], self::PAY_AS_YOU_GO_PROJECT_FEATURE_NAME);

        $project = $this->client->getProject($project['id']);
        $purchasedCredits = $project['payAsYouGo']['purchasedCredits'];

        try {
            $this->normalUserClient->giveProjectCredits($project['id'], [
                'amount' => 100,
                'description' => 'Promo',
            ]);
            $this->fail('Organization admin should not be able to give credits');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $project = $this->client->getProject($project['id']);
        $this->assertSame($purchasedCredits, $project['payAsYouGo']['purchasedCredits']);
    }

    public function testProjectAdminCannotGiveProjectCredits(): void
    {
        $this->client->removeUserFeature($this->normalUser['email'], self::PAY_AS_YOU_GO_CREDITS_ADMIN_FEATURE_NAME);

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->createRedshiftProjectForClient($this->client, $organization['id'], [
            'name' => 'My test',
        ]);

        $this->client->addProjectFeature($project['id'], self::PAY_AS_YOU_GO_PROJECT_FEATURE_NAME);

        $this->client->addUserToProject($project['id'], ['email' => $this->normalUser['email']]);

        $project = $this->client->getProject($project['id']);
        $purchasedCredits = $project['payAsYouGo']['purchasedCredits'];

        try {
            $this->normalUserClient->giveProjectCredits($project['id'], [
                'amount' => 100,
                'description' => 'Promo',
            ]);
            $this->fail('Project admin should not be able to give credits');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $project = $this->client->getProject($project['id']);
        $this->assertSame($purchasedCredits, $project['payAsYouGo']['purchasedCredits']);
    }

    public function testRandomAdminCannotGiveProjectCredits(): void
    {
        $this->client->removeUserFeature($this->normalUser['email'], self::PAY_AS_YOU_GO_CREDITS_ADMIN_FEATURE_NAME);

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->createRedshiftProjectForClient($this->client, $organization['id'], [
            'name' => 'My test',
        ]);

        $this->client->addProjectFeature($project['id'], self::PAY_AS_YOU_GO_PROJECT_FEATURE_NAME);

        $project = $this->client->getProject($project['id']);
        $purchasedCredits = $project['payAsYouGo']['purchasedCredits'];

        try {
            $this->normalUserClient->giveProjectCredits($project['id'], [
                'amount' => 100,
                'description' => 'Promo',
            ]);
            $this->fail('Maintainer admin should not be able to give credits');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $project = $this->client->getProject($project['id']);
        $this->assertSame($purchasedCredits, $project['payAsYouGo']['purchasedCredits']);
    }

    public function testNonVerifyProjectMemberRestrictions(): void
    {
        $inviteeEmail = 'devel-tests@keboola.com';

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $this->client->updateOrganization($organization['id'], [
            'allowAutoJoin' => 0,
        ]);
        $projectId = $this->createProjectWithSuperAdminMember($organization['id']);

        $this->client->addUserToProject($projectId, ['email' => $this->unverifiedUser['email']]);

        try {
            $this->unverifiedUserClient->inviteUserToProject($projectId, ['email' => $inviteeEmail]);
            $this->fail('Should fail');
        } catch (ClientException $e) {
            $this->assertSame('Only active users can perform this action.', $e->getMessage());
        }

        $joinRequest = $this->normalUserClient->requestAccessToProject($projectId);

        try {
            $this->unverifiedUserClient->approveProjectJoinRequest($projectId, $joinRequest['id']);
            $this->fail('Should fail');
        } catch (ClientException $e) {
            $this->assertSame('Only active users can perform this action.', $e->getMessage());
        }

        $invitation = $this->client->inviteUserToProject(
            $projectId,
            [
                'email' => $this->normalUserWithMfa['email'],
                'role' => ProjectRole::GUEST,
            ]
        );
        try {
            $this->unverifiedUserClient->cancelProjectInvitation($projectId, $invitation['id']);
            $this->fail('Should fail');
        } catch (ClientException $e) {
            $this->assertSame('Only active users can perform this action.', $e->getMessage());
        }

        try {
            $this->unverifiedUserClient->addUserToProject($projectId, ['email' => $this->normalUserWithMfa['email']]);
            $this->fail('Should fail');
        } catch (ClientException $e) {
            $this->assertSame('Only active users can perform this action.', $e->getMessage());
        }

        try {
            $this->unverifiedUserClient->updateUserProjectMembership($projectId, $this->normalUser['id'], ['role' => ProjectRole::GUEST]);
            $this->fail('Should fail');
        } catch (ClientException $e) {
            $this->assertSame('Only active users can perform this action.', $e->getMessage());
        }

        $this->client->removeUserFromProject($projectId, $this->unverifiedUser['id']);
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->unverifiedUser['email']]);

        try {
            $this->unverifiedUserClient->requestAccessToProject($projectId);
            $this->fail('Should fail');
        } catch (ClientException $e) {
            $this->assertSame('Only active users can perform this action.', $e->getMessage());
        }

        $this->client->addUserToOrganization($organization['id'], ['email' => $this->unverifiedUser['email']]);
        try {
            $this->unverifiedUserClient->joinProject($projectId);
            $this->fail('Should fail');
        } catch (ClientException $e) {
            $this->assertSame('Only active users can perform this action.', $e->getMessage());
        }
    }

    private function createSuperManageTokenWithProjectsReadScopeClient(): Client
    {
        $client = $this->getClient([
            'token' => EnvVariableHelper::getKbcManageApiSuperTokenWithProjectsReadScope(),
            'url' => EnvVariableHelper::getKbcManageApiUrl(),
        ]);
        $token = $client->verifyToken();
        $this->assertContains(
            'projects:read',
            $token['scopes'],
            'The provided token does not have required "projects:read" scope'
        );
        return $client;
    }

    private function createSuperManageTokenWithoutScopesClient(): Client
    {
        $client = $this->getClient([
            'token' => EnvVariableHelper::getKbcManageApiSuperTokenWithoutScopes(),
            'url' => EnvVariableHelper::getKbcManageApiUrl(),
        ]);
        $token = $client->verifyToken();
        $this->assertEquals(
            [],
            $token['scopes'],
            'The provided token should not have any scopes'
        );
        return $client;
    }

    private function createSuperManageTokenWithDeletedProjectsReadScopeClient(): Client
    {
        $client = $this->getClient([
            'token' => EnvVariableHelper::getKbcManageApiSuperTokenWithDeletedProjectsReadScope(),
            'url' => EnvVariableHelper::getKbcManageApiUrl(),
        ]);
        $token = $client->verifyToken();
        $this->assertContains(
            'deleted-projects:read',
            $token['scopes'],
            'The provided token does not have required "deleted-projects:read" scope'
        );
        return $client;
    }
}
