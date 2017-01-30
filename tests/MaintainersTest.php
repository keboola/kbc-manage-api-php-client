<?php
/**
 * Created by PhpStorm.
 * User: martinhalamicek
 * Date: 15/10/15
 * Time: 15:29
 */

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

class MaintainersTest extends ClientTestCase
{

    public function testListMaintainers()
    {
        $maintainers = $this->client->listMaintainers();

        $this->assertGreaterThan(0, count($maintainers));

        $maintainer = $maintainers[0];

        $this->assertInternalType('int', $maintainer['id']);
        $this->assertNotEmpty($maintainer['name']);
        $this->assertNotEmpty($maintainer['created']);
    }
    
    public function testUserMaintainerUsers()
    {
        $maintainers = $this->client->listMaintainers();

        $this->assertGreaterThan(0, count($maintainers));

        $superAdmin = $this->client->verifyToken()['user'];

        $maintainer = $maintainers[0];

        $maintainerMembers = $this->client->listMaintainerMembers($maintainer['id']);
        $this->assertCount(0,$maintainerMembers);

        $this->client->addUserToMaintainer($maintainer['id'],['email' => $superAdmin['email']]);
        $maintainerMembers = $this->client->listMaintainerMembers($maintainer['id']);
        $this->assertCount(1,$maintainerMembers);

        $this->client->removeUserFromMaintainer($maintainer['id'], $superAdmin['id']);
        $maintainerMembers = $this->client->listMaintainerMembers($maintainer['id']);
        $this->assertCount(0,$maintainerMembers);

    }

    // the remaining tests are testing maintainer user privileges
    // 1) confirm maintainer users cannot join organization
    // 2) confirm maintainer users cannot update autoJoin flag for organization
    // 3) confirm project joining requirements.
    // 4) confirm invited maintainer is active
    public function testMaintainerNoJoinOrganization()
    {
        $normalUserClient = new \Keboola\ManageApi\Client([
            'token' => getenv('KBC_TEST_ADMIN_TOKEN'),
            'url' => getenv('KBC_MANAGE_API_URL')
        ]);
        $normalUser = $normalUserClient->verifyToken()['user'];

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Test org',
        ]);

        // make normalUser a maintainer
        $this->client->addUserToMaintainer($this->testMaintainerId,['email' => $normalUser['email']]);

        // make sure maintainer cannot join organization
        try {
            $this->client->addUserToOrganization($organization['id'], ["email" => $normalUser['email']]);
            $this->fail("Cannot add maintainers to organization");
        } catch (ClientException $e) {
            $this->assertEquals("manage.joinOrganizationPermissionDenied", $e->getStringCode());
        }
    }

    public function testSettingAutoJoinFlag()
    {
        $normalUserClient = new \Keboola\ManageApi\Client([
            'token' => getenv('KBC_TEST_ADMIN_TOKEN'),
            'url' => getenv('KBC_MANAGE_API_URL')
        ]);
        $normalUser = $normalUserClient->verifyToken()['user'];
        $superAdmin = $this->client->verifyToken()['user'];

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Test org',
        ]);

        // make normalUser a maintainer
        $this->client->addUserToMaintainer($this->testMaintainerId,['email' => $normalUser['email']]);

        // make sure superAdmin cannot update allowAutoJoin
        try {
            $org = $normalUserClient->updateOrganization($organization['id'], ['allowAutoJoin' => false]);
            $this->fail("Maintainers not allowed to alter 'allowAutoJoin` parameter");
        } catch (ClientException $e) {
            $this->assertEquals("manage.updateOrganizationPermissionDenied", $e->getStringCode());
        }
        $this->assertEquals(true, $organization['allowAutoJoin']);
        $org = $this->client->updateOrganization($organization['id'], ['allowAutoJoin' => false]);
        $this->assertEquals(false, $org['allowAutoJoin']);
    }

    public function testMaintainerAutoJoin()
    {
        $normalUserClient = new \Keboola\ManageApi\Client([
            'token' => getenv('KBC_TEST_ADMIN_TOKEN'),
            'url' => getenv('KBC_MANAGE_API_URL')
        ]);
        $normalUser = $normalUserClient->verifyToken()['user'];
        $superAdmin = $this->client->verifyToken()['user'];

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Test org',
        ]);
        $this->client->addUserToOrganization($organization['id'], [
            "email" => $normalUser['email']
        ]);
        $this->client->removeUserFromOrganization($organization['id'], $superAdmin['id']);

        // make normalUser a maintainer
        $this->client->addUserToMaintainer($this->testMaintainerId,['email' => $normalUser['email']]);


        $testProject = $this->client->createProject($organization['id'], [
            'name' => 'Test Project',
        ]);

        // allowAutoJoin is true, so maintainer should be allowed to join this new project
        $this->client->addUserToProject($testProject['id'],[
            "email" => $normalUser['email']
        ]);
        $projUsers = $this->client->listProjectUsers($testProject['id']);
        $this->assertCount(2,$projUsers);
        foreach ($projUsers as $projUser) {
            $this->assertEquals("active", $projUser['status']);
            if ($projUser['email'] === $normalUser['email']) {
                $this->assertEquals($projUser['id'], $normalUser['id']);
                $this->assertEquals("active", $projUser['status']);
            } else {
                $this->assertEquals($projUser['email'], $normalUser['email']);
            }
        }
        $this->client->removeUserFromProject($testProject['id'],$superAdmin['id']);
        $projUsers = $this->client->listProjectUsers($testProject['id']);
        $this->assertCount(1,$projUsers);

        $normalUserClient->updateOrganization($organization['id'], ['allowAutoJoin' => false]);

        // now superAdmin should have access pending when he tries to join the project
        $this->client->addUserToProject($testProject['id'], [
            'email' => $superAdmin['email'],
            'reason' => "testing",
            'expirationSeconds' => 8600
        ]);
        $projUsers = $this->client->listProjectUsers($testProject['id']);
        $this->assertCount(2,$projUsers);
        foreach ($projUsers as $projUser) {
            if ($projUser['email'] === $superAdmin['email']) {
                $this->assertEquals($projUser['id'], $normalUser['id']);
                $this->assertEquals("pending", $projUser['status']);
                $this->assertEquals("testing", $projUser['reason']);
            } else {
                $this->assertEquals("active", $projUser['status']);
                $this->assertEquals($projUser['email'], $normalUser['email']);
            }
        }
    }

    public function testInviteMaintainer()
    {
        $normalUserClient = new \Keboola\ManageApi\Client([
            'token' => getenv('KBC_TEST_ADMIN_TOKEN'),
            'url' => getenv('KBC_MANAGE_API_URL')
        ]);
        $normalUser = $normalUserClient->verifyToken()['user'];
        $superAdmin = $this->client->verifyToken()['user'];

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Test org',
        ]);

        // make normalUser a maintainer
        $this->client->addUserToMaintainer($this->testMaintainerId,['email' => $normalUser['email']]);

        $testProject = $this->client->createProject($organization['id'], [
            'name' => 'Test Project',
        ]);

        $org = $this->client->updateOrganization($organization['id'], ['allowAutoJoin' => false]);
        $this->assertEquals(false, $org['allowAutoJoin']);

        $this->client->addUserToProject($testProject['id'],[
            "email" => $normalUser['email']
        ]);

        $projUsers = $this->client->listProjectUsers($testProject['id']);
        $this->assertCount(2,$projUsers);
        foreach ($projUsers as $projUser) {
            $this->assertEquals("active", $projUser['status']);
            if ($projUser['email'] === $normalUser['email']) {
                $this->assertEquals($projUser['id'], $normalUser['id']);
                $this->assertEquals("active", $projUser['status']);
            } else {
                $this->assertEquals($projUser['email'], $normalUser['email']);
            }
        }
    }
}