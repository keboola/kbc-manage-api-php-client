<?php

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Backend;
use Keboola\ManageApi\ClientException;
use Keboola\StorageApi\Client;

class ProjectsTest extends ClientTestCase
{
    private const FILE_STORAGE_PROVIDER_S3 = 'aws';
    private const FILE_STORAGE_PROVIDER_ABS = 'azure';
    private const PAY_AS_YOU_GO_CREDITS_ADMIN_FEATURE_NAME = 'pay-as-you-go-credits-admin';
    private const PAY_AS_YOU_GO_PROJECT_FEATURE_NAME = 'pay-as-you-go';

    public function supportedBackends(): array
    {
        return [
            [Backend::SNOWFLAKE],
            [Backend::REDSHIFT],
            [Backend::SYNAPSE],
        ];
    }

    public function unsupportedBackendFileStorageCombinations(): array
    {
        return [
            [
                Backend::REDSHIFT,
                self::FILE_STORAGE_PROVIDER_ABS,
                'Redshift does not support other file storage than S3.',
            ],
            [
                Backend::SYNAPSE,
                self::FILE_STORAGE_PROVIDER_S3,
                'Synapse storage backend supports only ABS file storage.',
            ],
        ];
    }

    /**
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
        ]);
        switch ($unsupportedFileStorageProvider) {
            case self::FILE_STORAGE_PROVIDER_S3:
                $storage = $this->client->listS3FileStorage()[0];
                break;
            case self::FILE_STORAGE_PROVIDER_ABS:
                $storage = $this->client->listAbsFileStorage()[0];
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
        } catch (\Throwable $e) {
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
        ]);

        $s3Storage = $this->client->listS3FileStorage()[0];
        $AbsStorage = $this->client->listAbsFileStorage()[0];

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
            case self::FILE_STORAGE_PROVIDER_ABS:
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
        } catch (\Throwable $e) {
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
     * @return array
     */
    private function initTestOrganization()
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
    private function initTestProject($organizationId)
    {
        $project = $this->client->createProject($organizationId, [
            'name' => 'My test',
        ]);
        $this->assertArrayHasKey('id', $project);
        $this->assertEquals('My test', $project['name']);

        $foundProject = $this->client->getProject($project['id']);
        $this->assertEquals($project['id'], $foundProject['id']);
        $this->assertEquals($project['name'], $foundProject['name']);
        $this->assertArrayHasKey('organization', $foundProject);
        $this->assertEquals($organizationId, $foundProject['organization']['id']);
        $this->assertArrayHasKey('limits', $foundProject);
        $this->assertTrue(count($foundProject['limits']) > 1);
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
        $this->assertInternalType('int', $firstLimit['value']);
        $this->assertEquals($firstLimit['name'], $limitKeys[0]);

        $this->assertArrayHasKey('fileStorage', $project);
        $fileStorage = $project['fileStorage'];
        $this->assertInternalType('int', $fileStorage['id']);
        $this->assertArrayHasKey('awsKey', $fileStorage);
        $this->assertArrayHasKey('region', $fileStorage);
        $this->assertArrayHasKey('filesBucket', $fileStorage);

        $this->assertArrayHasKey('backends', $project);

        $backends = $project['backends'];
        $this->assertArrayHasKey('snowflake', $backends);

        $snowflake = $backends['snowflake'];
        $this->assertInternalType('int', $snowflake['id']);
        $this->assertArrayHasKey('host', $snowflake);

        return $foundProject;
    }

    public function addUserToProjectWithRoleData(): array
    {
        return [
            [
                'admin',
            ],
            [
                'guest',
            ],
        ];
    }

    public function testSuperAdminCannotCreateProject()
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
            ]);

            $this->fail('SuperAdmin should be not allowed to create project');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertEquals('manage.createProjectPermissionDenied', $e->getStringCode());
            $this->assertEquals('Only organization members can create new projects', $e->getMessage());
        }

        $projects = $this->client->listOrganizationProjects($organizationId);
        $this->assertCount(0, $projects);
    }

    public function testMaintainerAdminCannotCreateProject()
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
            ]);

            $this->fail('MaintainerAdmin should be not allowed to create project');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertEquals('manage.createProjectPermissionDenied', $e->getStringCode());
            $this->assertEquals('Only organization members can create new projects', $e->getMessage());
        }

        $projects = $this->normalUserClient->listOrganizationProjects($organizationId);
        $this->assertCount(0, $projects);
    }

    public function testRandomAdminCannotCreateProject()
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
            ]);

            $this->fail('RandomAdmin should be not allowed to create project');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
            $this->assertContains('You don\'t have access to the organization', $e->getMessage());
        }

        $projects = $this->client->listOrganizationProjects($organizationId);
        $this->assertCount(0, $projects);
    }

    public function testOrganizationAdminCanCreateProject()
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

    public function testProductionProjectCreate()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
            'type' => 'production',
        ]);

        $project = $this->client->getProject($project['id']);
        $this->assertEquals('production', $project['type']);
        $this->assertNull($project['expires']);
    }

    public function testDemoProjectCreate()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
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
        ]);

        $this->assertEquals($backend, $project['defaultBackend']);
        $this->client->deleteProject($project['id']);
        $this->client->purgeDeletedProject($project['id']);
    }

    public function testCreateProjectWithRedshiftBackendFromTemplate()
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

    public function testCreateProjectWithDescription()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
            'description' => 'My test project',
        ]);

        $this->assertEquals('My test project', $project['description']);
    }

    public function testCreateProjectWithInvalidBackendShouldFail()
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

    public function testUsersManagement()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
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
           'email' => 'spam@keboola.com',
        ]);

        $admins = $this->client->listProjectUsers($project['id']);
        $this->assertCount(2, $admins);

        $foundUser = null;
        foreach ($admins as $user) {
            if ($user['email'] == 'spam@keboola.com') {
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

    public function testUserManagementInvalidEmail()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
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

    public function testExpiringUserDeletedProject()
    {
        // normal user in this case will be our maintainer.
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
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

    public function testTemporaryAccess()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
        ]);
        $admins = $this->client->listProjectUsers($project['id']);
        $this->assertCount(1, $admins);

        // test of adding / removing user with expiration/reason
        $resp = $this->client->addUserToProject($project['id'], [
            'email' => 'spam@keboola.com',
            'reason' => 'created by test',
            'expirationSeconds' => '20',
        ]);

        $admins = $this->client->listProjectUsers($project['id']);
        $this->assertCount(2, $admins);

        $foundUser = null;
        foreach ($admins as $user) {
            if ($user['email'] == 'spam@keboola.com') {
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

    public function testProjectUpdate()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
        ]);

        // update
        $newName = 'new name';
        $project = $this->client->updateProject($project['id'], [
            'name' => $newName,
        ]);
        $this->assertEquals($newName, $project['name']);
        $this->assertEquals(null, $project['description']);

        // set description
        $newDescription = 'description of my project';
        $project = $this->client->updateProject($project['id'], [
            'description' => $newDescription,
        ]);
        $this->assertEquals($newDescription, $project['description']);

        // unset description
        $project = $this->client->updateProject($project['id'], [
            'description' => '',
        ]);
        $this->assertEmpty($project['description']);

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

    public function testProjectUpdatePermissions()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
        ]);

        $this->client->addUserToProject($project['id'], [
            'email' => getenv('KBC_TEST_ADMIN_EMAIL'),
        ]);

        $client = $this->getClient([
            'token' => getenv('KBC_TEST_ADMIN_TOKEN'),
            'url' => getenv('KBC_MANAGE_API_URL'),
            'backoffMaxTries' => 1,
        ]);

        // update
        $newName = 'new name';
        $project = $client->updateProject($project['id'], [
            'name' => $newName,
        ]);
        $this->assertEquals($newName, $project['name']);

        // change type should not be allowd
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


    public function testChangeProjectOrganization()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
        ]);

        $newOrganization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org 2',
        ]);

        $changedProject = $this->client->changeProjectOrganization($project['id'], $newOrganization['id']);
        $this->assertEquals($newOrganization['id'], $changedProject['organization']['id']);
    }

    public function testChangeProjectLimits()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
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

    public function testDeleteProjectLimit()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
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

    public function testChangeProjectLimitsWithSuperToken()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $projectAfterCreation = $this->client->createProject($organization['id'], [
            'name' => 'My test',
        ]);

        try {
            $clientWithSuperApiToken = $this->getClient([
                'token' => getenv('KBC_SUPER_API_TOKEN'),
                'url' => getenv('KBC_MANAGE_API_URL'),
                'backoffMaxTries' => 0,
            ]);

            $clientWithSuperApiToken->setProjectLimits($projectAfterCreation['id'], []);
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }
    }

    public function testAddNonexistentFeature()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
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

    public function testAddProjectFeatureTwice()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);
        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
        ]);

        $project = $this->client->getProject($project['id']);

        $initialFeaturesCount = count($project['features']);

        $newFeature = 'random-feature-' . $this->getRandomFeatureSuffix();
        $this->client->createFeature($newFeature, 'project', $newFeature);
        $this->client->addProjectFeature($project['id'], $newFeature);

        $project = $this->client->getProject($project['id']);

        $this->assertSame($initialFeaturesCount + 1, count($project['features']));

        try {
            $this->client->addProjectFeature($project['id'], $newFeature);
            $this->fail('Feature already added');
        } catch (ClientException $e) {
            $this->assertEquals(422, $e->getCode());
        }

        $project = $this->client->getProject($project['id']);

        $this->assertSame($initialFeaturesCount + 1, count($project['features']));
    }

    public function testAddRemoveProjectFeatures()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
        ]);

        $firstFeatureName = 'first-feature-' . $this->getRandomFeatureSuffix();

        $this->assertNotContains($firstFeatureName, $project['features']);

        $this->client->createFeature($firstFeatureName, 'project', $firstFeatureName);
        $this->client->addProjectFeature($project['id'], $firstFeatureName);
        $project = $this->client->getProject($project['id']);

        $this->assertContains($firstFeatureName, $project['features']);

        $secondFeatureName = 'second-feature-' . $this->getRandomFeatureSuffix();
        $this->client->createFeature($secondFeatureName, 'project', $secondFeatureName);
        $this->client->addProjectFeature($project['id'], $secondFeatureName);
        $project = $this->client->getProject($project['id']);
        $this->assertGreaterThanOrEqual(2, count($project['features']));

        $this->client->removeProjectFeature($project['id'], $secondFeatureName);
        $project = $this->client->getProject($project['id']);
        $this->assertContains($firstFeatureName, $project['features']);
        $this->assertNotContains($secondFeatureName, $project['features']);
    }

    public function testCreateProjectStorageTokenWithoutPermissions()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
        ]);

        // token without permissions
        $token = $this->client->createProjectStorageToken($project['id'], [
            'description' => 'test',
            'expiresIn' => 60,
        ]);

        $client = $this->getClient([
            'url' => getenv('KBC_MANAGE_API_URL'),
            'token' => $token['token'],
        ]);

        $verified = $client->verifyToken();
        $this->assertEquals($project['id'], $verified['owner']['id']);
        $this->assertFalse($verified['canManageBuckets']);
        $this->assertFalse($verified['canManageTokens']);
        $this->assertFalse($verified['canReadAllFileUploads']);
        $this->assertFalse($verified['canPurgeTrash']);
        $this->assertFalse($verified['canUseDirectAccess']);
        $this->assertEmpty($verified['bucketPermissions']);
    }

    public function testCreateProjectStorageTokenWithMorePermissions()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
        ]);

        // new token with more permissions
        $token = $this->client->createProjectStorageToken($project['id'], [
            'description' => 'test',
            'expiresIn' => 60,
            'canManageBuckets' => true,
            'canReadAllFileUploads' => true,
            'canPurgeTrash' => true,
            'canUseDirectAccess' => true,//test created token will be canUseDirectAccess=false
        ]);

        $client = $this->getClient([
            'url' => getenv('KBC_MANAGE_API_URL'),
            'token' => $token['token'],
        ]);

        $verified = $client->verifyToken();
        $this->assertEquals($project['id'], $verified['owner']['id']);
        $this->assertTrue($verified['canManageBuckets']);
        $this->assertFalse($verified['canManageTokens']);
        $this->assertTrue($verified['canReadAllFileUploads']);
        $this->assertTrue($verified['canPurgeTrash']);
        $this->assertFalse($verified['canUseDirectAccess']);
    }

    public function testCreateProjectStorageTokenWithBucketPermissions()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
        ]);

        $tokenWithManageBucketsPermission = $this->client->createProjectStorageToken($project['id'], [
            'description' => 'test',
            'expiresIn' => 60,
            'canManageBuckets' => true,
            'canReadAllFileUploads' => true,
        ]);

        $client = $this->getClient([
            'url' => getenv('KBC_MANAGE_API_URL'),
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

        $clientWithReadBucketPermission = $this->getClient([
            'url' => getenv('KBC_MANAGE_API_URL'),
            'token' => $tokenWithReadPermissionToOneBucket['token'],
        ]);

        $verified = $clientWithReadBucketPermission->verifyToken();
        $this->assertEquals($project['id'], $verified['owner']['id']);
        $this->assertFalse($verified['canManageBuckets']);
        $this->assertFalse($verified['canManageTokens']);
        $this->assertFalse($verified['canReadAllFileUploads']);
        $this->assertFalse($verified['canUseDirectAccess']);
        $this->assertEquals([$newBucketId => 'read'], $verified['bucketPermissions']);
    }

    public function testCreateProjectStorageTokenWithMangeTokensPermission()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
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

        $client = $this->getClient([
            'url' => getenv('KBC_MANAGE_API_URL'),
            'token' => $token['token'],
        ]);

        $verified = $client->verifyToken();
        $this->assertEquals($project['id'], $verified['owner']['id']);
        $this->assertTrue($verified['canManageBuckets']);
        $this->assertTrue($verified['canManageTokens']);
        $this->assertTrue($verified['canReadAllFileUploads']);
        $this->assertFalse($verified['canUseDirectAccess']);
    }

    public function testSuperAdminCanDisableAndEnableProject()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $this->client->addUserToOrganization($organization['id'], ['email' => $this->normalUser['email']]);
        $this->client->removeUserFromOrganization($organization['id'], $this->superAdmin['id']);
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);
        $this->client->removeUserFromMaintainer($this->testMaintainerId, $this->superAdmin['id']);

        $project = $this->normalUserClient->createProject($organization['id'], [
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

        $client = $this->getClient([
            'url' =>  getenv('KBC_MANAGE_API_URL'),
            'token' => $storageToken['token'],
            'backoffMaxTries' => 1,
        ]);

        try {
            $client->verifyToken();
            $this->fail('Token should be disabled');
        } catch (\Keboola\StorageApi\ClientException $e) {
            $this->assertEquals($e->getStringCode(), 'MAINTENANCE');
            $this->assertEquals($e->getMessage(), $disableReason);
        }

        $this->client->enableProject($project['id']);

        $project = $this->client->getProject($project['id']);
        $this->assertFalse($project['isDisabled']);

        $storageToken = $client->verifyToken();
        $this->assertNotEmpty($storageToken);
    }

    public function testListDeletedProjects()
    {
        $organizations = array();

        for ($i=0; $i<2; $i++) {
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
        $projects = $this->client->listDeletedProjects();
        $this->assertGreaterThan($i + 1, $projects);

        // organization deleted projects
        $params = array(
            'organizationId' => $organization['id'],
        );

        $projects = $this->client->listDeletedProjects($params);
        $this->assertCount(1, $projects);

        // name filter test
        $params = array(
            'organizationId' => $organization['id'],
            'name' => $project['name'],
        );

        $projects = $this->client->listDeletedProjects($params);
        $this->assertGreaterThan(0, count($projects));

        $params = array(
            'organizationId' => $organization['id'],
            'name' => $project['name'],
        );

        $projects = $this->client->listDeletedProjects($params);
        $this->assertGreaterThan(0, count($projects));

        $params = array(
            'organizationId' => $organization['id'],
            'name' => sha1($project['name']),
        );

        $projects = $this->client->listDeletedProjects($params);
        $this->assertCount(0, $projects);

        foreach ($organizations as $organization) {
            $this->client->deleteOrganization($organization['id']);
        }
    }

    public function testListDeletedProjectsPaging()
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
        $params = array(
            'organizationId' => $organization['id'],
        );

        $projects = $this->client->listDeletedProjects($params);
        $this->assertCount(3, $projects);

        $params = array(
            'organizationId' => $organization['id'],
            'offset' => 0,
            'limit' => 2,
        );

        $projects = $this->client->listDeletedProjects($params);
        $this->assertCount(2, $projects);

        $params = array(
            'organizationId' => $organization['id'],
            'offset' => 2,
            'limit' => 2,
        );

        $projects = $this->client->listDeletedProjects($params);
        $this->assertCount(1, $projects);

        $params = array(
            'organizationId' => $organization['id'],
            'offset' => 4,
            'limit' => 2,
        );

        $projects = $this->client->listDeletedProjects($params);
        $this->assertCount(0, $projects);

        $this->client->deleteOrganization($organization['id']);
    }

    public function testDeletedProjectsErrors()
    {
        $organization = $this->initTestOrganization();
        $project = $this->initTestProject($organization['id']);

        $this->client->deleteProject($project['id']);
        $this->client->deleteOrganization($organization['id']);

        // deleted organization
        try {
            $this->client->listDeletedProjects(array('organizationId' => $organization['id']));

            $this->fail('List deleted projects of deleted organization should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
        }

        // permission validation
        $client = $this->getClient([
            'token' => getenv('KBC_TEST_ADMIN_TOKEN'),
            'url' => getenv('KBC_MANAGE_API_URL'),
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

    public function testProjectUnDelete()
    {
        $organization = $this->initTestOrganization();

        $project = $this->initTestProject($organization['id']);

        $this->client->deleteProject($project['id']);

        $params = array(
            'organizationId' => $organization['id'],
        );

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

    public function testDeletedProjectDetail()
    {
        $organization = $this->initTestOrganization();

        $project = $this->initTestProject($organization['id']);

        $this->client->deleteProject($project['id']);

        $params = array(
            'organizationId' => $organization['id'],
        );

        $deletedProject = $this->client->getDeletedProject($project['id']);
        $this->assertTrue($deletedProject['isDeleted']);
        $this->assertFalse($deletedProject['isPurged']);
        $this->assertNull($deletedProject['purgedTime']);
    }

    public function testActiveProjectUnDelete()
    {
        $organization = $this->initTestOrganization();

        $project = $this->initTestProject($organization['id']);

        $params = array(
            'organizationId' => $organization['id'],
        );

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

    public function testNonExistingProjectUnDelete()
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

    public function testProjectWithExpirationUnDelete()
    {
        $organization = $this->initTestOrganization();

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
            'type' => 'demo',
        ]);
        $projectId = $project['id'];

        $project = $this->client->getProject($project['id']);
        $this->assertEquals('demo', $project['type']);
        $this->assertNotEmpty($project['expires']);

        $this->client->deleteProject($project['id']);

        $params = array(
            'organizationId' => $organization['id'],
        );

        $projects = $this->client->listDeletedProjects($params);
        $this->assertCount(1, $projects);

        $this->client->undeleteProject($project['id']);

        $projects = $this->client->listDeletedProjects($params);
        $this->assertCount(0, $projects);

        $projects = $this->client->listOrganizationProjects($organization['id']);
        $this->assertCount(1, $projects);

        $project = reset($projects);
        $this->assertEmpty($project['expires']);
        $this->assertEquals($projectId, $project['id']);

        $this->client->deleteProject($project['id']);

        $projects = $this->client->listDeletedProjects($params);
        $this->assertCount(1, $projects);

        $this->client->deleteOrganization($organization['id']);
    }

    public function testProjectUnDeleteWithExpiration()
    {
        $organization = $this->initTestOrganization();

        $project = $this->initTestProject($organization['id']);
        $this->assertEmpty($project['expires']);

        $this->client->deleteProject($project['id']);

        $params = array(
            'organizationId' => $organization['id'],
        );

        $projects = $this->client->listDeletedProjects($params);
        $this->assertCount(1, $projects);

        $this->client->undeleteProject($project['id'], array('expirationDays' => 7));

        $projects = $this->client->listDeletedProjects($params);
        $this->assertCount(0, $projects);

        $projects = $this->client->listOrganizationProjects($organization['id']);
        $this->assertCount(1, $projects);

        $project = reset($projects);
        $this->assertNotEmpty($project['expires']);

        $this->client->deleteProject($project['id']);

        $projects = $this->client->listDeletedProjects($params);
        $this->assertCount(1, $projects);

        $this->client->deleteOrganization($organization['id']);
    }

    public function testProjectDataRetention()
    {
        $organization = $this->initTestOrganization();
        $project = $this->initTestProject($organization['id']);

        $this->assertEquals(7, (int) $project['dataRetentionTimeInDays']);

        // verify that normal users can't update data retention time
        try {
            $this->normalUserClient->updateProject($project['id'], ['dataRetentionTimeInDays' => 30]);
            $this->fail('Must be a super admin to update data retention period');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $project = $this->client->updateProject($project['id'], ['dataRetentionTimeInDays' => 30]);
        $this->assertEquals(30, (int) $project['dataRetentionTimeInDays']);
    }

    public function testLastMemberCanLeaveProject()
    {
        $organization = $this->initTestOrganization();
        $project = $this->initTestProject($organization['id']);

        $users = $this->client->listProjectUsers($project['id']);
        $this->assertCount(1, $users);

        $this->client->removeUserFromProject($project['id'], $this->superAdmin['id']);

        $users = $this->client->listProjectUsers($project['id']);
        $this->assertCount(0, $users);
    }

    public function testMaintainerAdminCannotDisableAndEnableProject()
    {
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
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

    public function testOrganizationAdminCannotDisableAndEnableProject()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $this->client->addUserToOrganization($organization['id'], ['email' => $this->normalUser['email']]);

        $project = $this->client->createProject($organization['id'], [
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

    public function testProjectAdminCannotDisableAndEnableProject()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
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

    public function testRandomAdminCannotDisableAndEnableProject()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
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
            $this->assertContains('invalid-role', $e->getMessage());
        }

        $member = $this->findProjectUser($project['id'], $this->normalUser['email']);
        $this->assertNull($member);
    }

    public function testMembershipRoleChange()
    {
        $organization = $this->initTestOrganization();
        $project = $this->initTestProject($organization['id']);

        $this->client->addUserToProject($project['id'], ['email' => $this->normalUser['email'],]);

        $member = $this->findProjectUser($project['id'], $this->normalUser['email']);
        $this->assertEquals('admin', $member['role']);

        $this->client->updateUserProjectMembership($project['id'], $this->normalUser['id'], ['role' => 'guest']);

        $member = $this->findProjectUser($project['id'], $this->normalUser['email']);
        $this->assertEquals('guest', $member['role']);

        $this->client->updateUserProjectMembership($project['id'], $this->normalUser['id'], ['role' => 'admin']);

        $member = $this->findProjectUser($project['id'], $this->normalUser['email']);
        $this->assertEquals('admin', $member['role']);
    }

    public function testPayAsYoGoDetails()
    {
        $feature = self::PAY_AS_YOU_GO_PROJECT_FEATURE_NAME;

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => __CLASS__,
        ]);

        $project = $this->client->createProject($organization['id'], [
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
        $this->assertInternalType('integer', $payAsYouGo['purchasedCredits']);

        $projects = $this->client->listOrganizationProjects($organization['id']);
        $this->assertCount(1, $projects);

        $project = reset($projects);
        $this->assertEquals($projectId, $project['id']);
        $this->assertArrayHasKey('payAsYouGo', $project);

        $payAsYouGo = $project['payAsYouGo'];
        $this->assertInternalType('integer', $payAsYouGo['purchasedCredits']);
    }

    public function testCreditsCannotBeGivenToNonPaygoProject(): void
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $this->client->addUserToOrganization($organization['id'], ['email' => $this->normalUser['email']]);

        $project = $this->normalUserClient->createProject($organization['id'], [
            'name' => 'My test',
        ]);

        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('is not Pay As You Go project');

        $this->client->giveProjectCredits($project['id'], [
            'amount' => 100,
            'description' => 'Promo',
        ]);
    }

    public function testSuperAdminCanGiveProjectCredits(): void
    {
        $this->client->removeUserFeature($this->superAdmin['email'], self::PAY_AS_YOU_GO_CREDITS_ADMIN_FEATURE_NAME);

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $this->client->addUserToOrganization($organization['id'], ['email' => $this->normalUser['email']]);

        $project = $this->normalUserClient->createProject($organization['id'], [
            'name' => 'My test',
        ]);

        $this->client->addProjectFeature($project['id'], self::PAY_AS_YOU_GO_PROJECT_FEATURE_NAME);

        $project = $this->client->getProject($project['id']);
        $purchasedCredits = $project['payAsYouGo']['purchasedCredits'];

        $response = $this->client->giveProjectCredits($project['id'], [
            'amount' => 100,
            'description' => 'Promo',
        ]);

        $this->assertArrayHasKey('id', $response);
        $this->assertInternalType('int', $response['id']);
        $this->assertArrayHasKey('creditsAmount', $response);
        $this->assertSame(100, $response['creditsAmount']);
        $this->assertArrayHasKey('moneyAmount', $response);
        $this->assertNull($response['moneyAmount']);
        $this->assertArrayHasKey('idStripeInvoice', $response);
        $this->assertNull($response['moneyAmount']);
        $this->assertArrayHasKey('description', $response);
        $this->assertSame('Promo', $response['description']);
        $this->assertArrayHasKey('created', $response);
        $this->assertNotNull($response['created']);

        $project = $this->client->getProject($project['id']);
        $this->assertSame($purchasedCredits + 100, $project['payAsYouGo']['purchasedCredits']);
    }

    public function testAdminWithFeatureCanGiveProjectCredits(): void
    {
        $this->client->removeUserFeature($this->normalUser['email'], self::PAY_AS_YOU_GO_CREDITS_ADMIN_FEATURE_NAME);
        $this->client->addUserFeature($this->normalUser['email'], self::PAY_AS_YOU_GO_CREDITS_ADMIN_FEATURE_NAME);

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $this->client->addUserToOrganization($organization['id'], ['email' => $this->normalUser['email']]);

        $project = $this->normalUserClient->createProject($organization['id'], [
            'name' => 'My test',
        ]);

        $this->client->addProjectFeature($project['id'], self::PAY_AS_YOU_GO_PROJECT_FEATURE_NAME);

        $project = $this->client->getProject($project['id']);
        $purchasedCredits = $project['payAsYouGo']['purchasedCredits'];

        $response = $this->client->giveProjectCredits($project['id'], [
            'amount' => 100,
            'description' => 'Promo',
        ]);

        $this->assertArrayHasKey('id', $response);
        $this->assertInternalType('int', $response['id']);
        $this->assertArrayHasKey('creditsAmount', $response);
        $this->assertSame(100, $response['creditsAmount']);
        $this->assertArrayHasKey('moneyAmount', $response);
        $this->assertNull($response['moneyAmount']);
        $this->assertArrayHasKey('idStripeInvoice', $response);
        $this->assertNull($response['moneyAmount']);
        $this->assertArrayHasKey('description', $response);
        $this->assertSame('Promo', $response['description']);
        $this->assertArrayHasKey('created', $response);
        $this->assertNotNull($response['created']);

        $project = $this->client->getProject($project['id']);
        $this->assertSame($purchasedCredits + 100, $project['payAsYouGo']['purchasedCredits']);
    }

    public function testMaintainerAdminCannotGiveProjectCredits(): void
    {
        $this->client->removeUserFeature($this->normalUser['email'], self::PAY_AS_YOU_GO_CREDITS_ADMIN_FEATURE_NAME);

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
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

        $project = $this->client->createProject($organization['id'], [
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

        $project = $this->client->createProject($organization['id'], [
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

        $project = $this->client->createProject($organization['id'], [
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
}
