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

    public function testProjectCreate()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
           'name' => 'My test',
        ]);
        $this->assertArrayHasKey('id', $project);
        $this->assertEquals('My test', $project['name']);

        // check if the project is listed in organization projects
        $projects = $this->client->listOrganizationProjects($organization['id']);

        $this->assertCount(1, $projects);
        $this->assertEquals($project['id'], $projects[0]['id']);
        $this->assertEquals($project['name'], $projects[0]['name']);

        $foundProject = $this->client->getProject($project['id']);
        $this->assertEquals($project['id'], $foundProject['id']);
        $this->assertEquals($project['name'], $foundProject['name']);
        $this->assertArrayHasKey('organization', $foundProject);
        $this->assertEquals($organization['id'], $foundProject['organization']['id']);
        $this->assertArrayHasKey('limits', $foundProject);
        $this->assertTrue(count($foundProject['limits']) > 1);
        $this->assertArrayHasKey('metrics', $foundProject);
        $this->assertEquals('mysql', $foundProject['defaultBackend']);
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

        // delete project
        $this->client->deleteProject($project['id']);

        $projects = $this->client->listOrganizationProjects($organization['id']);
        $this->assertEmpty($projects);

        $this->client->deleteOrganization($organization['id']);
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
        $this->assertNotEmpty($admins[0]['id']);
        $this->assertNotEmpty($admins[0]['name']);
        $this->assertNotEmpty($admins[0]['email']);

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
        }

        $this->client->removeUserFromProject($project['id'], $foundUser['id']);

        $admins = $this->client->listProjectUsers($project['id']);
        $this->assertCount(1, $admins);
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
            'expirationSeconds' => '60'
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
        }

        // wait for the new guy to get removed from the project during next cron run
        $tries = 0;

        while ($tries < 7) {
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

        // fetch again
        $project = $this->client->getProject($project['id']);
        $this->assertEquals($newName, $project['name']);
        $this->assertEquals('mysql', $project['defaultBackend']);

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

    public function testAddRemoveProjectFeatures()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
        ]);

        $this->assertEmpty($project['features']);

        $featureName = 'storage-tests';
        $this->client->addProjectFeature($project['id'], $featureName);

        $project = $this->client->getProject($project['id']);

        $this->assertEquals([$featureName], $project['features']);

        $this->client->addProjectFeature($project['id'], 'storage-tests-2');
        $project = $this->client->getProject($project['id']);
        $this->assertCount(2, $project['features']);

        $this->client->removeProjectFeature($project['id'], 'storage-tests-2');
        $project = $this->client->getProject($project['id']);
        $this->assertEquals([$featureName], $project['features']);
    }

    public function testCreateProjectStorageToken()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
        ]);

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

        // new token with more permissions
        $token = $this->client->createProjectStorageToken($project['id'], [
            'description' => 'test',
            'expiresIn' => 60,
            'canManageBuckets' => true,
            'canReadAllFileUploads' => true,
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
        $this->assertNotEmpty($verified['bucketPermissions']);

        // test bucket permissions
        $token = $this->client->createProjectStorageToken($project['id'], [
            'description' => 'test',
            'expiresIn' => 60,
            'bucketPermissions' => [
                'in.c-main' => 'read',
            ]
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
        $this->assertEquals(['in.c-main' => 'read'], $verified['bucketPermissions']);
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
}