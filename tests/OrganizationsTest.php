<?php
/**
 * Created by PhpStorm.
 * User: martinhalamicek
 * Date: 15/10/15
 * Time: 15:29
 */

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

class OrganizationsTest extends ClientTestCase
{
    public function testListOrganizations()
    {
        $organizations = $this->client->listOrganizations();

        $this->assertGreaterThan(0, count($organizations));

        $organization = $organizations[0];
        $this->assertInternalType('int', $organization['id']);
        $this->assertNotEmpty($organization['name']);
        $this->assertArrayHasKey('maintainer', $organization);
    }

    public function testLeastOneMemberLimit()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Test org',
        ]);

        $organizationId = $organization['id'];

        $this->client->addUserToOrganization($organizationId, ['email' => $this->normalUser['email']]);

        $members = $this->client->listOrganizationUsers($organizationId);
        $this->assertCount(2, $members);

        $this->client->removeUserFromOrganization($organizationId, $this->superAdmin['id']);

        $members = $this->client->listOrganizationUsers($organizationId);
        $this->assertCount(1, $members);

        try {
            $this->client->removeUserFromOrganization($organizationId, $this->normalUser['id']);
            $this->fail('The last member could not be removed from the organization');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertContains('least 1 member', $e->getMessage());
        }

        $members = $this->client->listOrganizationUsers($organizationId);
        $this->assertCount(1, $members);
    }

    public function testOrganizationCreateAndDelete()
    {
        $organizations = $this->client->listMaintainerOrganizations($this->testMaintainerId);
        $initialOrgsCount = count($organizations);
        $organization = $this->client->createOrganization($this->testMaintainerId, [
           'name' => 'My org',
        ]);

        $fromList = array_values(array_filter($this->client->listOrganizations(), function($org) use($organization) {
            return $org['id'] === $organization['id'];
        }));
        $this->assertNotEmpty($fromList);
        $this->assertCount(1, $fromList);
        $this->assertEquals($organization['id'], $fromList[0]['id']);

        $projects = $this->client->listOrganizationProjects($organization['id']);
        $this->assertEmpty($projects);

        $organizations = $this->client->listMaintainerOrganizations($this->testMaintainerId);
        $this->assertEquals($initialOrgsCount + 1, count($organizations));

        $this->client->deleteOrganization($organization['id']);
    }

    public function testOrganizationDetail()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Test org',
        ]);

        $org = $this->client->getOrganization($organization['id']);

        $this->assertEquals($org['name'], $organization['name']);
        $this->assertEmpty($org['projects']);
        $this->assertEmpty($org['crmId']);
        $this->assertNotEmpty($organization['created']);

        // permissions of another user
        try {
            $this->normalUserClient->getOrganization($organization['id']);
            $this->fail('User should not have permissions to organization');
        } catch (ClientException $e) {
           $this->assertEquals(403, $e->getCode());
        }

        try {
            $this->normalUserClient->deleteOrganization($organization['id']);
            $this->fail('User should not have permissions to organization');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $this->client->deleteOrganization($organization['id']);

        try {
            $org = $this->client->getOrganization($organization['id']);
            $this->fail("Organisation has been deleted, should not exist.");
        } catch (ClientException $e) {
            $this->assertEquals(404, $e->getCode());
        }
    }
    
    public function testUpdateOrganization()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Test org',
        ]);

        $this->assertEquals("Test org", $organization['name']);
        $this->assertEquals(1, (int) $organization['allowAutoJoin']);

        $org = $this->client->updateOrganization($organization['id'], [
            "name" => "new name",
            "allowAutoJoin" => 0
        ]);

        $this->assertEquals("new name", $org['name']);
        $this->assertEquals(0, (int) $org['allowAutoJoin']);

        // permissions of another user
        try {
            $this->normalUserClient->updateOrganization($organization['id'], [
                "name" => "my name",
            ]);
            $this->fail('User should not have permissions to rename the organization');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }
    }

    public function testOrganizationCreateWithCrmId()
    {
        $crmId = '1243';
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Test org',
            'crmId' => $crmId,
        ]);

        $organization = $this->client->getOrganization($organization['id']);
        $this->assertEquals($crmId, $organization['crmId']);
    }

    public function testMaintainerMemberCanUpdateCrmId()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Test org',
        ]);
        $crmId = '12334';
        $organization = $this->client->updateOrganization($organization['id'], [
            'crmId' => $crmId,
        ]);

        $this->assertEquals($crmId, $organization['crmId']);
    }

    public function testOrganizationMemberCannotUpdateCrmId()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Test org',
        ]);
        $this->client->addUserToOrganization($organization['id'], ['email' => $this->normalUser['email']]);

        $this->expectException(ClientException::class);
        $this->expectExceptionCode(403);
        $this->normalUserClient->updateOrganization($organization['id'], [
            'crmId' => 'some id',
        ]);
    }

    public function testOrganizationUsers()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Test org',
        ]);
        $admins = $this->client->listOrganizationUsers($organization['id']);
        $this->assertCount(1, $admins);

        $this->client->addUserToOrganization($organization['id'], ['email' => 'spam@keboola.com']);

        $admins = $this->client->listOrganizationUsers($organization['id']);
        $this->assertCount(2, $admins);

        $foundUser = null;
        foreach ($admins as $user) {
            $this->assertNotEmpty($user['id']);
            $this->assertArrayHasKey('name', $user);
            $this->assertNotEmpty($user['email']);
            $this->assertTrue(is_bool($user['mfaEnabled']));
            $this->assertNotEmpty($user['created']);
            $this->assertArrayHasKey('invitor', $user);
            $this->assertNull($user['invitor']);

            if ($user['email'] == 'spam@keboola.com') {
                $foundUser = $user;
                break;
            }
        }
        if (!$foundUser) {
            $this->fail('User should be in list');
        }

        $this->client->removeUserFromOrganization($organization['id'], $foundUser['id']);

        $admins = $this->client->listOrganizationUsers($organization['id']);
        $this->assertCount(1, $admins);

        // permissions of another user
        try {
            $this->normalUserClient->addUserToOrganization($organization['id'], ['email' => 'spam2@keboola.com']);
            $this->fail('User should not have permissions to add users to organization');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }
    }

    public function testSuperCannotAddAnybodyToOrganizationWithNoJoin()
    {
        $normalUser = $this->normalUserClient->verifyToken()['user'];
        $superAdmin = $this->client->verifyToken()['user'];

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Test org',
        ]);
        $this->client->addUserToOrganization($organization['id'], [
            "email" => $normalUser['email']
        ]);
        $this->assertTrue($organization['allowAutoJoin']);
        $this->client->removeUserFromOrganization($organization['id'], $superAdmin['id']);
        $orgUsers = $this->client->listOrganizationUsers($organization['id']);
        $this->assertCount(1, $orgUsers);

        // make sure superAdmin can add someone to the organization, allowAutoJoin is true
        $org = $this->client->addUserToOrganization($organization['id'], ["email" => "spammer@keboola.com"]);
        $orgUsers = $this->client->listOrganizationUsers($organization['id']);
        $this->assertCount(2, $orgUsers);

        // now set allowAutoJoin to false and super should no longer be able to add user to org
        $this->normalUserClient->updateOrganization($organization['id'], ['allowAutoJoin' => false]);
        try {
            $this->client->addUserToOrganization($organization['id'], ["email" => "spammer@keboola.com"]);
            $this->fail("Should not be able to add the user");
        } catch (ClientException $e) {
            $this->assertEquals("manage.joinOrganizationPermissionDenied", $e->getStringCode());
        }
    }

    public function testSettingAutoJoinFlag()
    {
        $normalUser = $this->normalUserClient->verifyToken()['user'];
        $superAdmin = $this->client->verifyToken()['user'];

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Test org',
        ]);
        $this->client->addUserToOrganization($organization['id'], [
            "email" => $normalUser['email']
        ]);
        $this->client->removeUserFromOrganization($organization['id'], $superAdmin['id']);

        // make sure superAdmin cannot update allowAutoJoin
        try {
            $org = $this->client->updateOrganization($organization['id'], ['allowAutoJoin' => false]);
            $this->fail("Superadmins not allowed to alter 'allowAutoJoin` parameter");
        } catch (ClientException $e) {
            $this->assertEquals("manage.updateOrganizationPermissionDenied", $e->getStringCode());
        }
        $this->assertEquals(true, $organization['allowAutoJoin']);
        $org = $this->normalUserClient->updateOrganization($organization['id'], ['allowAutoJoin' => false]);
        $this->assertEquals(false, $org['allowAutoJoin']);
    }

    public function testOrganizationAdminAutoJoin()
    {
        $normalUser = $this->normalUserClient->verifyToken()['user'];
        $superAdmin = $this->client->verifyToken()['user'];

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Test org',
        ]);
        $this->client->addUserToOrganization($organization['id'], [
            "email" => $normalUser['email']
        ]);

        $testProject = $this->normalUserClient->createProject($organization['id'], [
            'name' => 'Test Project',
        ]);

        $projectUser = $this->findProjectUser($testProject['id'], $superAdmin['email']);
        $this->assertNull($projectUser);

        $this->client->addUserToProject($testProject['id'],[
            "email" => $superAdmin['email']
        ]);

        $projectUser = $this->findProjectUser($testProject['id'], $superAdmin['email']);
        $this->assertNotNull($projectUser);
        $this->assertArrayHasKey('approver', $projectUser);
        $this->assertArrayHasKey('status', $projectUser);

        $this->assertEquals('active', $projectUser['status']);
        $this->assertEquals($superAdmin['id'], $projectUser['approver']['id']);
        $this->assertEquals($superAdmin['email'], $projectUser['approver']['email']);
        $this->assertEquals($superAdmin['name'], $projectUser['approver']['name']);

        $this->client->removeUserFromProject($testProject['id'], $superAdmin['id']);

        $projectUser = $this->findProjectUser($testProject['id'], $superAdmin['email']);
        $this->assertNull($projectUser);

        $this->normalUserClient->updateOrganization($organization['id'], ['allowAutoJoin' => false]);

        $this->client->addUserToProject($testProject['id'],[
            "email" => $superAdmin['email']
        ]);

        $projectUser = $this->findProjectUser($testProject['id'], $superAdmin['email']);
        $this->assertNotNull($projectUser);
        $this->assertArrayHasKey('approver', $projectUser);
        $this->assertArrayHasKey('status', $projectUser);

        $this->assertEquals('active', $projectUser['status']);
        $this->assertEquals($superAdmin['id'], $projectUser['approver']['id']);
        $this->assertEquals($superAdmin['email'], $projectUser['approver']['email']);
        $this->assertEquals($superAdmin['name'], $projectUser['approver']['name']);
    }

    public function testSuperAdminAutoJoinError()
    {
        $normalUser = $this->normalUserClient->verifyToken()['user'];
        $superAdmin = $this->client->verifyToken()['user'];

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Test org',
        ]);
        $this->client->addUserToOrganization($organization['id'], [
            "email" => $normalUser['email']
        ]);
        $this->client->removeUserFromOrganization($organization['id'], $superAdmin['id']);

        $testProject = $this->normalUserClient->createProject($organization['id'], [
            'name' => 'Test Project',
        ]);

        $projectUser = $this->findProjectUser($testProject['id'], $superAdmin['email']);
        $this->assertNull($projectUser);

        try {
            $this->client->addUserToProject($testProject['id'],[
                "email" => $superAdmin['email']
            ]);
            $this->fail('Project join should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $projectUser = $this->findProjectUser($testProject['id'], $superAdmin['email']);
        $this->assertNull($projectUser);

        $this->normalUserClient->updateOrganization($organization['id'], ['allowAutoJoin' => false]);

        try {
            $this->client->addUserToProject($testProject['id'],[
                "email" => $superAdmin['email']
            ]);
            $this->fail('Project join should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $projectUser = $this->findProjectUser($testProject['id'], $superAdmin['email']);
        $this->assertNull($projectUser);
    }

    public function testInviteSuperAdmin()
    {
        $normalUser = $this->normalUserClient->verifyToken()['user'];
        $superAdmin = $this->client->verifyToken()['user'];

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Test org',
        ]);
        $this->client->addUserToOrganization($organization['id'], [
            "email" => $normalUser['email']
        ]);
        $this->client->removeUserFromOrganization($organization['id'], $superAdmin['id']);

        $testProject = $this->normalUserClient->createProject($organization['id'], [
            'name' => 'Test Project',
        ]);

        $org = $this->normalUserClient->updateOrganization($organization['id'], ['allowAutoJoin' => false]);
        $this->assertEquals(false, $org['allowAutoJoin']);

        $this->normalUserClient->addUserToProject($testProject['id'],[
            "email" => $superAdmin['email']
        ]);

        $projUsers = $this->client->listProjectUsers($testProject['id']);
        $this->assertCount(2,$projUsers);
        foreach ($projUsers as $projUser) {
            $this->assertEquals("active", $projUser['status']);
            if ($projUser['email'] === $superAdmin['email']) {
                $this->assertEquals($projUser['id'], $superAdmin['id']);
                $this->assertEquals("active", $projUser['status']);
            } else {
                $this->assertEquals($projUser['email'], $normalUser['email']);
            }
        }
    }
}
