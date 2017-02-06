<?php
/**
 * Created by PhpStorm.
 * User: martinhalamicek
 * Date: 15/10/15
 * Time: 15:29
 */

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Client;
use Keboola\ManageApi\ClientException;

class MaintainersTest extends ClientTestCase
{
    private $normalUser;
    
    private $superAdmin;

    public function setUp()
    {
        parent::setUp();
        $this->normalUser = $this->normalUserClient->verifyToken()['user'];
        $this->superAdmin = $this->client->verifyToken()['user'];
        $maintainers = $this->client->listMaintainers();
        foreach ($maintainers as $maintainer) {
            if ((int) $maintainer['id'] === (int) $this->testMaintainerId) {
                $members = $this->client->listMaintainerMembers($maintainer['id']);
                foreach ($members as $member) {
                    if ($member['id'] != $this->superAdmin['id']) {
                        $this->client->removeUserFromMaintainer($maintainer['id'], $member['id']);
                    }
                }
            } else {
                $this->client->deleteMaintainer($maintainer['id']);
            }
        }
    }

    public function testCreateDeleteMaintainer()
    {
        $testMaintainer = $this->client->getMaintainer($this->testMaintainerId);

        $newMaintainer = $this->client->createMaintainer([
            'name' => "test maintainer",
            'defaultConnectionMysqlId' => $testMaintainer['defaultConnectionMysqlId'],
            'defaultConnectionRedshiftId' => $testMaintainer['defaultConnectionRedshiftId'],
            'defaultConnectionSnowflakeId' => $testMaintainer['defaultConnectionSnowflakeId'],
        ]);

        $this->assertEquals('test maintainer', $newMaintainer['name']);
        $this->assertArrayHasKey('created', $newMaintainer);
        $this->assertEquals($testMaintainer['defaultConnectionMysqlId'], $newMaintainer['defaultConnectionMysqlId']);
        $this->assertEquals($testMaintainer['defaultConnectionRedshiftId'], $newMaintainer['defaultConnectionRedshiftId']);
        $this->assertEquals($testMaintainer['defaultConnectionSnowflakeId'], $newMaintainer['defaultConnectionSnowflakeId']);
        $this->assertArrayHasKey('zendesk_url', $newMaintainer);
        $this->assertNull($newMaintainer['zendesk_url']);

        $maintainerList = $this->client->listMaintainers();
        $this->assertCount(2, $maintainerList);

        $this->client->deleteMaintainer($newMaintainer['id']);

        $maintainerList = $this->client->listMaintainers();
        $this->assertCount(1, $maintainerList);

        // retrieve the deleted maintainer should throw 404
        try {
            $deletedMaintainer = $this->client->getMaintainer($newMaintainer['id']);
            $this->fail("retrieve the deleted maintainer should throw 404");
        } catch (ClientException $e) {
            $this->assertEquals(404, $e->getCode());
        }
    }

    public function testUpdateMaintainer()
    {
        $backends = $this->client->listStorageBackend();
        foreach ($backends as $backend) {
            switch ($backend['backend']) {
                case 'mysql':
                    $mysqlBackend = $backend;
                    break;
                case 'redshift':
                    $redshiftBackend = $backend;
                    break;
                case 'snowflake':
                    $snowflakeBackend = $backend;
            }
        }

        $newMaintainer = $this->client->createMaintainer([
            'name' => "test maintainer"
        ]);
        $updateArray = ['name' => 'updated name'];
        if (!is_null($mysqlBackend)) {
            $updateArray['defaultConnectionMysqlId'] = $mysqlBackend['id'];
        }
        if (!is_null($redshiftBackend)) {
            $updateArray['defaultConnectionRedshiftId'] = $redshiftBackend['id'];
        }
        if (!is_null($snowflakeBackend)) {
            $updateArray['defaultConnectionSnowflakeId'] = $snowflakeBackend['id'];
        }

        $updateArray['zendesk_url'] = "https://fake.url.com";

        $updatedMaintainer = $this->client->updateMaintainer($newMaintainer['id'], $updateArray);
        $this->assertEquals('updated name', $updatedMaintainer['name']);
        $this->assertEquals("https://fake.url.com", $updatedMaintainer['zendesk_url']);

        if (array_key_exists('defaultConnectionMysqlId',$updateArray)) {
            $this->assertEquals($mysqlBackend['id'], $updatedMaintainer['defaultConnectionMysqlId']);
        }
        if (array_key_exists('defaultConnectionRedshiftId',$updateArray)) {
            $this->assertEquals($redshiftBackend['id'], $updatedMaintainer['defaultConnectionRedshiftId']);
        }
        if (array_key_exists('defaultConnectionSnowflakeId',$updateArray)) {
            $this->assertEquals($snowflakeBackend['id'], $updatedMaintainer['defaultConnectionSnowflakeId']);
        }
    }

    public function testNormalUserMaintainerPermissions()
    {
        $testMaintainer = $this->client->getMaintainer($this->testMaintainerId);
        try {
            $newMaintainer = $this->normalUserClient->createMaintainer([
                'name' => "test maintainer",
                'defaultConnectionMysqlId' => $testMaintainer['defaultConnectionMysqlId'],
                'defaultConnectionRedshiftId' => $testMaintainer['defaultConnectionRedshiftId'],
                'defaultConnectionSnowflakeId' => $testMaintainer['defaultConnectionSnowflakeId'],
            ]);    
            $this->fail("normal user should not be able to create maintainrer");
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }
        // normal user should have empty maintainer list
        $maintainerList = $this->normalUserClient->listMaintainers();
        $this->assertCount(0,$maintainerList);
        try {
            $this->normalUserClient->getMaintainer($this->testMaintainerId);
            $this->fail("normal user cannot fetch maintainer which he is not a member of");
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        try {
            $this->normalUserClient->deleteMaintainer($this->testMaintainerId);
            $this->fail("normal user cannot delete a maintainer");
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }
        
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);
        $testMaintainer = $this->normalUserClient->getMaintainer($this->testMaintainerId);
        $this->assertNotEmpty($testMaintainer['name']);
    }

    public function testCrossMaintainerOrganizationAccess()
    {
        $secondMaintainer = $this->client->createMaintainer(["name" => "secondary maintainer"]);
        $this->client->addUserToMaintainer($secondMaintainer['id'], ["id" => $this->normalUser['id']]);
        $this->client->removeUserFromMaintainer($secondMaintainer['id'], $this->superAdmin['id']);

        $normalMaintainers = $this->normalUserClient->listMaintainers();
        $this->assertCount(1, $normalMaintainers);
        $this->assertEquals('secondary maintainer', $normalMaintainers[0]['name']);

        try {
            $this->normalUserClient->updateMaintainer($this->testMaintainerId, ["name" => "should fail"]);
            $this->fail("Should not be able to update other maintainer");
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        try {
            $this->normalUserClient->createOrganization($this->testMaintainerId, ["name" => "org create fail"]);
            $this->fail("Should not be able to create org for other maintainer");
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        try {
            $this->normalUserClient->addUserToMaintainer($this->testMaintainerId, ["email" => $this->normalUser['email']]);
            $this->fail("Should not be able to add user to other maintainer");
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        try {
            $this->normalUserClient->removeUserFromMaintainer($this->testMaintainerId, $this->superAdmin['id']);
            $this->fail("Should not be able to remove users from foreign maintainer");
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }
    }
    
    public function testListMaintainers()
    {
        $maintainers = $this->client->listMaintainers();

        $this->assertGreaterThan(0, count($maintainers));

        $maintainer = $maintainers[0];

        $this->assertInternalType('int', $maintainer['id']);
        $this->assertNotEmpty($maintainer['name']);
        $this->assertNotEmpty($maintainer['created']);
        $this->assertArrayHasKey('defaultConnectionMysqlId', $maintainer);
        $this->assertArrayHasKey('defaultConnectionRedshiftId', $maintainer);
        $this->assertArrayHasKey('defaultConnectionSnowflakeId', $maintainer);
        $this->assertArrayHasKey('zendesk_url', $maintainer);
        $this->assertNull($maintainer['zendesk_url']);
    }
    
    public function testUserMaintainerUsers()
    {
        $maintainers = $this->client->listMaintainers();

        $this->assertGreaterThan(0, count($maintainers));

        $superAdmin = $this->client->verifyToken()['user'];
        $maintainer = $maintainers[0];

        $maintainerMembers = $this->client->listMaintainerMembers($maintainer['id']);
        $this->assertCount(1,$maintainerMembers);

        $this->client->addUserToMaintainer($maintainer['id'],['email' => $this->normalUser['email']]);
        $maintainerMembers = $this->client->listMaintainerMembers($maintainer['id']);
        $this->assertCount(2,$maintainerMembers);

        $this->client->removeUserFromMaintainer($maintainer['id'], $this->normalUser['id']);
        $maintainerMembers = $this->client->listMaintainerMembers($maintainer['id']);
        $this->assertCount(1,$maintainerMembers);
        $this->assertEquals($superAdmin['email'], $maintainerMembers[0]['email']);

    }

    // the remaining tests are testing maintainer user privileges
    // 1) confirm maintainer users cannot join organization
    // 2) confirm maintainer users cannot update autoJoin flag for organization
    // 3) confirm project joining requirements.
    // 4) confirm invited maintainer is active
    public function testMaintainerNoJoinOrganization()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Test org',
        ]);

        // make sure normalUser is a maintainer
        $this->addNormalMaintainer();

        // make sure maintainer cannot join organization -- superAdmin should not be org member for this test
        $this->client->removeUserFromOrganization($organization['id'], $this->superAdmin['id']);
        try {
            $org = $this->client->addUserToOrganization($organization['id'], ["email" => $this->normalUser['email']]);
            $this->fail("Cannot add maintainers to organization");
        } catch (ClientException $e) {
            $this->assertEquals("manage.joinOrganizationPermissionDenied", $e->getStringCode());
        }
    }

    public function testSettingAutoJoinFlag()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Test org',
        ]);

        // make sure normalUser is a maintainer
        $this->addNormalMaintainer();

        // make sure superAdmin cannot update allowAutoJoin
        try {
            $org = $this->normalUserClient->updateOrganization($organization['id'], ['allowAutoJoin' => false]);
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
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Test org',
        ]);

        // make sure normalUser is a maintainer
        $this->addNormalMaintainer($this->normalUser);

        $testProject = $this->client->createProject($organization['id'], [
            'name' => 'Test Project',
        ]);

        // allowAutoJoin is true, so maintainer should be allowed to join this new project
        $this->client->addUserToProject($testProject['id'],[
            "email" => $this->normalUser['email']
        ]);
        $projUsers = $this->client->listProjectUsers($testProject['id']);
        $this->assertCount(2,$projUsers);
        foreach ($projUsers as $projUser) {
            $this->assertEquals("active", $projUser['status']);
            if ($projUser['email'] === $this->normalUser['email']) {
                $this->assertEquals($projUser['id'], $this->normalUser['id']);
                $this->assertEquals("active", $projUser['status']);
            } else {
                $this->assertEquals($projUser['email'], $this->superAdmin['email']);
            }
        }
        $this->client->removeUserFromProject($testProject['id'],$this->normalUser['id']);
        $projUsers = $this->client->listProjectUsers($testProject['id']);
        $this->assertCount(1,$projUsers);

        $org = $this->client->updateOrganization($organization['id'], ['allowAutoJoin' => false]);

        // now maintainer should have access pending when he tries to join the project
        $this->normalUserClient->addUserToProject($testProject['id'], [
            'email' => $this->normalUser['email'],
            'reason' => "testing",
            'expirationSeconds' => 8600
        ]);
        $projUsers = $this->client->listProjectUsers($testProject['id']);
        $this->assertCount(2,$projUsers);
        foreach ($projUsers as $projUser) {
            if ($projUser['email'] === $this->normalUser['email']) {
                $this->assertEquals($projUser['id'], $this->normalUser['id']);
                $this->assertEquals("pending", $projUser['status']);
                $this->assertEquals("testing", $projUser['reason']);
            } else {
                $this->assertEquals("active", $projUser['status']);
                $this->assertEquals($projUser['email'], $this->superAdmin['email']);
            }
        }
    }

    public function testInviteMaintainer()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Test org',
        ]);

        // make sure normalUser is a maintainer
        $this->addNormalMaintainer();

        $testProject = $this->client->createProject($organization['id'], [
            'name' => 'Test Project',
        ]);

        $org = $this->client->updateOrganization($organization['id'], ['allowAutoJoin' => false]);
        $this->assertEquals(false, $org['allowAutoJoin']);

        $this->client->addUserToProject($testProject['id'],[
            "email" => $this->normalUser['email']
        ]);

        $projUsers = $this->client->listProjectUsers($testProject['id']);
        $this->assertCount(2,$projUsers);
        foreach ($projUsers as $projUser) {
            $this->assertEquals("active", $projUser['status']);
            if ($projUser['email'] === $this->normalUser['email']) {
                $this->assertEquals($projUser['id'], $this->normalUser['id']);
                $this->assertEquals("active", $projUser['status']);
            } else {
                $this->assertEquals($projUser['email'], $this->superAdmin['email']);
            }
        }
    }

    private function addNormalMaintainer() {
        // make sure normalUser a maintainer
        $maintainers = $this->client->listMaintainerMembers($this->testMaintainerId);
        $normalMaintainerExists = false;
        foreach ($maintainers as $maintainer) {
            if ($maintainer['email'] === $this->normalUser['email']) {
                $normalMaintainerExists = true;
            }
        }
        if (!$normalMaintainerExists) {
            $this->client->addUserToMaintainer($this->testMaintainerId,['email' => $this->normalUser['email']]);
        }
    }
}