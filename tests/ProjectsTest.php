<?php
/**
 * Created by PhpStorm.
 * User: martinhalamicek
 * Date: 15/10/15
 * Time: 15:29
 */

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;
use Keboola\StorageApi\Client;

class ProjectsTest extends ClientTestCase
{
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

    public function testCreateProjectWithRedshiftBackend()
    {
        $backend = 'redshift';
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
            'defaultBackend' => $backend,
        ]);

        $this->assertEquals($backend, $project['defaultBackend']);
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
            $this->fail("Project should not be created");
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
            'expirationSeconds' => '20'
        ]);

        $admins = $this->normalUserClient->listProjectUsers($project['id']);
        $this->assertCount(2, $admins);

        foreach ($admins as $projUser) {
            $this->assertEquals("active", $projUser['status']);
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
            'email' => $this->normalUser['email']
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
            'expirationSeconds' => '20'
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
            $this->assertEquals("created by test", $foundUser["reason"]);
            $this->assertGreaterThan(date("Y-m-d H:i:s", time()),$foundUser["expires"]);
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

        $client = new \Keboola\ManageApi\Client([
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
            ]
        ];
        $project = $this->client->setProjectLimits($project['id'], $limits);
        $this->assertEquals($limits[0], $project['limits']['goodData.prodTokenEnabled']);
        $this->assertEquals($limits[1], $project['limits']['goodData.usersCount']);

        $project = $this->client->getProject($project['id']);
        $this->assertEquals($limits[0], $project['limits']['goodData.prodTokenEnabled']);
        $this->assertEquals($limits[1], $project['limits']['goodData.usersCount']);
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
            $clientWithSuperApiToken = new \Keboola\ManageApi\Client([
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

        $this->assertEmpty($project['features']);

        $firstFeatureName = 'first-feature-' . $this->getRandomFeatureSuffix();
        $this->client->createFeature($firstFeatureName, 'project', $firstFeatureName);
        $this->client->addProjectFeature($project['id'], $firstFeatureName);
        $project = $this->client->getProject($project['id']);

        $this->assertEquals([$firstFeatureName], $project['features']);

        $secondFeatureName = 'second-feature-' . $this->getRandomFeatureSuffix();
        $this->client->createFeature($secondFeatureName, 'project', $secondFeatureName);
        $this->client->addProjectFeature($project['id'], $secondFeatureName);
        $project = $this->client->getProject($project['id']);
        $this->assertCount(2, $project['features']);

        $this->client->removeProjectFeature($project['id'], $secondFeatureName);
        $project = $this->client->getProject($project['id']);
        $this->assertEquals([$firstFeatureName], $project['features']);
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

        $client = new Client([
            'url' => getenv('KBC_MANAGE_API_URL'),
            'token' => $token['token'],
        ]);

        $verified = $client->verifyToken();
        $this->assertEquals($project['id'], $verified['owner']['id']);
        $this->assertFalse($verified['canManageBuckets']);
        $this->assertFalse($verified['canManageTokens']);
        $this->assertFalse($verified['canReadAllFileUploads']);
        $this->assertEmpty($verified['bucketPermissions']);
        $this->assertFalse($verified['canPurgeTrash']);
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
        ]);

        $client = new Client([
            'url' => getenv('KBC_MANAGE_API_URL'),
            'token' => $token['token'],
        ]);

        $verified = $client->verifyToken();
        $this->assertEquals($project['id'], $verified['owner']['id']);
        $this->assertTrue($verified['canManageBuckets']);
        $this->assertFalse($verified['canManageTokens']);
        $this->assertTrue($verified['canReadAllFileUploads']);
        $this->assertTrue($verified['canPurgeTrash']);
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

        $client = new Client([
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
            ]
        ]);

        $clientWithReadBucketPermission = new Client([
            'url' => getenv('KBC_MANAGE_API_URL'),
            'token' => $tokenWithReadPermissionToOneBucket['token'],
        ]);

        $verified = $clientWithReadBucketPermission->verifyToken();
        $this->assertEquals($project['id'], $verified['owner']['id']);
        $this->assertFalse($verified['canManageBuckets']);
        $this->assertFalse($verified['canManageTokens']);
        $this->assertFalse($verified['canReadAllFileUploads']);
        $this->assertFalse($verified['canPurgeTrash']);
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

        $client = new Client([
            'url' => getenv('KBC_MANAGE_API_URL'),
            'token' => $token['token'],
        ]);

        $verified = $client->verifyToken();
        $this->assertEquals($project['id'], $verified['owner']['id']);
        $this->assertTrue($verified['canManageBuckets']);
        $this->assertTrue($verified['canManageTokens']);
        $this->assertTrue($verified['canReadAllFileUploads']);
        $this->assertFalse($verified['canPurgeTrash']);
    }

    public function testProjectEnableDisable()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
        ]);

        $this->assertFalse($project['isDisabled']);

        $storageToken = $this->client->createProjectStorageToken($project['id'], [
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

        $client = new Client([
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
        $client = new \Keboola\ManageApi\Client([
            'token' => getenv('KBC_TEST_ADMIN_TOKEN'),
            'url' => getenv('KBC_MANAGE_API_URL')
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
}
