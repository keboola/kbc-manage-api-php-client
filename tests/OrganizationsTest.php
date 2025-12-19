<?php

declare(strict_types=1);

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Client;
use Keboola\ManageApi\ClientException;
use Keboola\ManageApiTest\Utils\EnvVariableHelper;

final class OrganizationsTest extends ClientTestCase
{
    public function testListOrganizations(): void
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Test org',
        ]);
        $this->client->addUserToOrganization($organization['id'], ['email' => $this->normalUser['email']]);

        $organizations = $this->client->listOrganizations();

        $this->assertGreaterThan(0, count($organizations));

        $organization = $organizations[0];
        $this->assertIsInt($organization['id']);
        $this->assertNotEmpty($organization['name']);
        $this->assertArrayHasKey('maintainer', $organization);
    }

    public function testLeastOneMemberLimit(): void
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
            $this->assertStringContainsString('least 1 member', $e->getMessage());
        }

        $members = $this->client->listOrganizationUsers($organizationId);
        $this->assertCount(1, $members);
    }

    public function testOrganizationCreateAndDelete(): void
    {
        $organizations = $this->client->listMaintainerOrganizations($this->testMaintainerId);
        $initialOrgsCount = count($organizations);
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $fromList = array_values(array_filter($this->client->listOrganizations(), function (array $org) use ($organization): bool {
            return $org['id'] === $organization['id'];
        }));
        $this->assertNotEmpty($fromList);
        $this->assertCount(1, $fromList);
        $this->assertEquals($organization['id'], $fromList[0]['id']);

        $projects = $this->client->listOrganizationProjects($organization['id']);
        $this->assertEmpty($projects);

        $organizations = $this->client->listMaintainerOrganizations($this->testMaintainerId);
        $this->assertCount($initialOrgsCount + 1, $organizations);

        $this->client->deleteOrganization($organization['id']);
    }

    public function testApplicationTokenWithScopeCanAccessOrganizationListAndDetailAndProjectList(): void
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Test org',
        ]);

        $deletedOrganization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => sha1($this->getTestName()) . ' deleted',
        ]);
        $this->client->deleteOrganization($deletedOrganization['id']);

        $client = new Client([
            'token' => EnvVariableHelper::getKbcManageApiSuperTokenWithOrganizationsReadScope(),
            'url' => EnvVariableHelper::getKbcManageApiUrl(),
            'backoffMaxTries' => 0,
        ]);

        $orgFromAppToken = $client->getOrganization($organization['id']);
        $orgFromAdminToken = $this->client->getOrganization($organization['id']);
        $this->assertEquals($orgFromAdminToken, $orgFromAppToken);
        $projects = $client->listOrganizationProjects($organization['id']);
        $this->assertEquals($orgFromAdminToken['projects'], $projects);
        $orgsFromTokenFull = $client->listOrganizations();
        $orgFromAdminFull = $this->client->listOrganizations();
        // filter out the orgs and find the one we just created
        $orgsFromToken = array_values(array_filter($orgsFromTokenFull, function (array $org) use ($organization): bool {
            return $org['id'] === $organization['id'];
        }));
        $orgsFromAdmin = array_values(array_filter($orgFromAdminFull, function (array $org) use ($organization): bool {
            return $org['id'] === $organization['id'];
        }));

        // we can't assert much more, because the token sees every organization (even those created outside of this test)
        $this->assertNotEmpty(
            $orgsFromToken,
            'There was no organization returned from the list',
        );
        $this->assertSame(
            $orgsFromAdmin[0],
            $orgsFromToken[0],
            'Organization from list does not match the one we created',
        );

        // test that deleted organization are not accessible in detail nor list
        // you need to use try/catch to check multiple scenarios
        try {
            $client->getOrganization($deletedOrganization['id']);
            $this->fail('Deleted organization should not be accessible');
        } catch (ClientException $e) {
            $this->assertEquals(404, $e->getCode());
        }

        $deletedOrganizationFromAdmin = array_values(array_filter($orgFromAdminFull, function (array $org) use ($deletedOrganization): bool {
            return $org['id'] === $deletedOrganization['id'];
        }));
        $deletedOrganizationFromToken = array_values(array_filter($orgsFromTokenFull, function (array $org) use ($deletedOrganization): bool {
            return $org['id'] === $deletedOrganization['id'];
        }));

        $this->assertEmpty($deletedOrganizationFromAdmin);
        $this->assertEmpty($deletedOrganizationFromToken);
    }

    public function testOrganizationDetail(): void
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Test org',
        ]);

        $org = $this->client->getOrganization($organization['id']);

        $this->assertEquals($org['name'], $organization['name']);
        $this->assertEmpty($org['projects']);
        $this->assertEmpty($org['crmId']);
        $this->assertEmpty($org['activityCenterProjectId']);
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
            $this->fail('Organisation has been deleted, should not exist.');
        } catch (ClientException $e) {
            $this->assertEquals(404, $e->getCode());
        }
    }

    public function testUpdateOrganization(): void
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Test org',
        ]);

        $this->assertEquals('Test org', $organization['name']);
        $this->assertSame(1, (int) $organization['allowAutoJoin']);

        $org = $this->client->updateOrganization($organization['id'], [
            'name' => 'new name',
            'allowAutoJoin' => 0,
        ]);

        $this->assertEquals('new name', $org['name']);
        $this->assertSame(0, (int) $org['allowAutoJoin']);

        // permissions of another user
        try {
            $this->normalUserClient->updateOrganization($organization['id'], [
                'name' => 'my name',
            ]);
            $this->fail('User should not have permissions to rename the organization');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }
    }

    public function testOrganizationCreateWithCrmId(): void
    {
        $crmId = '1243';
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Test org',
            'crmId' => $crmId,
        ]);

        $organization = $this->client->getOrganization($organization['id']);
        $this->assertEquals($crmId, $organization['crmId']);
    }

    public function testMaintainerMemberCanUpdateCrmId(): void
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

    public function testOrganizationMemberCannotUpdateCrmId(): void
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

    public function testOrganizationUsers(): void
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Test org',
        ]);
        $admins = $this->client->listOrganizationUsers($organization['id']);
        $this->assertCount(1, $admins);

        $this->client->addUserToOrganization($organization['id'], ['email' => 'devel-tests@keboola.com']);

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

            if ($user['email'] === 'devel-tests@keboola.com') {
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
            $this->normalUserClient->addUserToOrganization($organization['id'], ['email' => 'devel-tests+spam2@keboola.com']);
            $this->fail('User should not have permissions to add users to organization');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }
    }

    public function testSuperCannotAddAnybodyToOrganizationWithNoJoin(): void
    {
        $tokenInfo = $this->normalUserClient->verifyToken();
        $this->assertArrayHasKey('user', $tokenInfo);
        $normalUser = $tokenInfo['user'];

        $tokenInfo = $this->client->verifyToken();
        $this->assertArrayHasKey('user', $tokenInfo);
        $superAdmin = $tokenInfo['user'];

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Test org',
        ]);
        $this->client->addUserToOrganization($organization['id'], [
            'email' => $normalUser['email'],
        ]);
        $this->assertTrue($organization['allowAutoJoin']);
        $this->client->removeUserFromOrganization($organization['id'], $superAdmin['id']);
        $orgUsers = $this->client->listOrganizationUsers($organization['id']);
        $this->assertCount(1, $orgUsers);

        // make sure superAdmin can add someone to the organization, allowAutoJoin is true
        $org = $this->client->addUserToOrganization($organization['id'], ['email' => 'devel-tests+spammer@keboola.com']);
        $orgUsers = $this->client->listOrganizationUsers($organization['id']);
        $this->assertCount(2, $orgUsers);

        // now set allowAutoJoin to false and super should no longer be able to add user to org
        $this->normalUserClient->updateOrganization($organization['id'], ['allowAutoJoin' => false]);
        try {
            $this->client->addUserToOrganization($organization['id'], ['email' => 'devel-tests+spammer@keboola.com']);
            $this->fail('Should not be able to add the user');
        } catch (ClientException $e) {
            $this->assertEquals('manage.joinOrganizationPermissionDenied', $e->getStringCode());
        }
    }

    public function testSettingAutoJoinFlag(): void
    {
        $tokenInfo = $this->normalUserClient->verifyToken();
        $this->assertArrayHasKey('user', $tokenInfo);
        $normalUser = $tokenInfo['user'];

        $tokenInfo = $this->client->verifyToken();
        $this->assertArrayHasKey('user', $tokenInfo);
        $superAdmin = $tokenInfo['user'];

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Test org',
        ]);
        $this->client->addUserToOrganization($organization['id'], [
            'email' => $normalUser['email'],
        ]);
        $this->client->removeUserFromOrganization($organization['id'], $superAdmin['id']);

        // make sure superAdmin cannot update allowAutoJoin
        try {
            $org = $this->client->updateOrganization($organization['id'], ['allowAutoJoin' => false]);
            $this->fail("Superadmins not allowed to alter 'allowAutoJoin` parameter");
        } catch (ClientException $e) {
            $this->assertEquals('manage.updateOrganizationPermissionDenied', $e->getStringCode());
        }
        $this->assertEquals(true, $organization['allowAutoJoin']);
        $org = $this->normalUserClient->updateOrganization($organization['id'], ['allowAutoJoin' => false]);
        $this->assertEquals(false, $org['allowAutoJoin']);
    }

    public function testOrganizationAdminAutoJoin(): void
    {
        $tokenInfo = $this->normalUserClient->verifyToken();
        $this->assertArrayHasKey('user', $tokenInfo);
        $normalUser = $tokenInfo['user'];

        $tokenInfo = $this->client->verifyToken();
        $this->assertArrayHasKey('user', $tokenInfo);
        $superAdmin = $tokenInfo['user'];

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Test org',
        ]);
        $this->client->addUserToOrganization($organization['id'], [
            'email' => $normalUser['email'],
        ]);

        $testProject = $this->createRedshiftProjectForClient($this->normalUserClient, $organization['id'], [
            'name' => 'Test Project',
        ]);

        $projectUser = $this->findProjectUser($testProject['id'], $superAdmin['email']);
        $this->assertNull($projectUser);

        $this->client->addUserToProject($testProject['id'], [
            'email' => $superAdmin['email'],
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

        $this->client->addUserToProject($testProject['id'], [
            'email' => $superAdmin['email'],
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

    public function testSuperAdminAutoJoinError(): void
    {
        $tokenInfo = $this->normalUserClient->verifyToken();
        $this->assertArrayHasKey('user', $tokenInfo);
        $normalUser = $tokenInfo['user'];

        $tokenInfo = $this->client->verifyToken();
        $this->assertArrayHasKey('user', $tokenInfo);
        $superAdmin = $tokenInfo['user'];

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Test org',
        ]);
        $this->client->addUserToOrganization($organization['id'], [
            'email' => $normalUser['email'],
        ]);
        $this->client->removeUserFromOrganization($organization['id'], $superAdmin['id']);

        $testProject = $this->createRedshiftProjectForClient($this->normalUserClient, $organization['id'], [
            'name' => 'Test Project',
        ]);

        $projectUser = $this->findProjectUser($testProject['id'], $superAdmin['email']);
        $this->assertNull($projectUser);

        try {
            $this->client->addUserToProject($testProject['id'], [
                'email' => $superAdmin['email'],
            ]);
            $this->fail('Project join should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $projectUser = $this->findProjectUser($testProject['id'], $superAdmin['email']);
        $this->assertNull($projectUser);

        $this->normalUserClient->updateOrganization($organization['id'], ['allowAutoJoin' => false]);

        try {
            $this->client->addUserToProject($testProject['id'], [
                'email' => $superAdmin['email'],
            ]);
            $this->fail('Project join should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $projectUser = $this->findProjectUser($testProject['id'], $superAdmin['email']);
        $this->assertNull($projectUser);
    }

    public function testInviteSuperAdmin(): void
    {
        $tokenInfo = $this->normalUserClient->verifyToken();
        $this->assertArrayHasKey('user', $tokenInfo);
        $normalUser = $tokenInfo['user'];

        $tokenInfo = $this->client->verifyToken();
        $this->assertArrayHasKey('user', $tokenInfo);
        $superAdmin = $tokenInfo['user'];

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Test org',
        ]);
        $this->client->addUserToOrganization($organization['id'], [
            'email' => $normalUser['email'],
        ]);
        $this->client->removeUserFromOrganization($organization['id'], $superAdmin['id']);

        $testProject = $this->createRedshiftProjectForClient($this->normalUserClient, $organization['id'], [
            'name' => 'Test Project',
        ]);

        $org = $this->normalUserClient->updateOrganization($organization['id'], ['allowAutoJoin' => false]);
        $this->assertEquals(false, $org['allowAutoJoin']);

        $this->normalUserClient->addUserToProject($testProject['id'], [
            'email' => $superAdmin['email'],
        ]);

        $projUsers = $this->client->listProjectUsers($testProject['id']);
        $this->assertCount(2, $projUsers);
        foreach ($projUsers as $projUser) {
            $this->assertEquals('active', $projUser['status']);
            if ($projUser['email'] === $superAdmin['email']) {
                $this->assertEquals($projUser['id'], $superAdmin['id']);
                $this->assertEquals('active', $projUser['status']);
            } else {
                $this->assertEquals($projUser['email'], $normalUser['email']);
            }
        }
    }

    public function testActivityCenterId(): void
    {
        $tokenInfo = $this->normalUserClient->verifyToken();
        $this->assertArrayHasKey('user', $tokenInfo);
        $normalUser = $tokenInfo['user'];

        $organizationA = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Test org A',
        ]);

        // just to be able to create project B somewhere
        $organizationB = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Test org B',
        ]);

        $this->client->addUserToOrganization($organizationA['id'], [
            'email' => $normalUser['email'],
        ]);

        $this->client->addUserToOrganization($organizationB['id'], [
            'email' => $normalUser['email'],
        ]);

        $testProjectA = $this->createRedshiftProjectForClient($this->normalUserClient, $organizationA['id'], [
            'name' => 'Test Project A',
        ]);
        $testProjectB = $this->createRedshiftProjectForClient($this->normalUserClient, $organizationB['id'], [
            'name' => 'Test Project B',
        ]);

        try {
            $this->normalUserClient->updateOrganization($organizationA['id'], ['activityCenterProjectId' => $testProjectA['id']]);
            $this->fail('should fail');
        } catch (ClientException $e) {
            $this->assertSame('Only maintainer members can change ActivityCenter ProjectId', $e->getMessage());
        }

        $this->client->addUserToMaintainer($this->testMaintainerId, [
            'email' => $normalUser['email'],
        ]);

        $orgDetail = $this->normalUserClient->updateOrganization($organizationA['id'], ['activityCenterProjectId' => $testProjectA['id']]);
        $this->assertEquals($testProjectA['id'], $orgDetail['activityCenterProjectId']);

        $orgDetail = $this->normalUserClient->updateOrganization($organizationA['id'], ['activityCenterProjectId' => null]);
        $this->assertEquals(null, $orgDetail['activityCenterProjectId']);

        try {
            // project B is not in the organization
            $this->normalUserClient->updateOrganization($organizationA['id'], ['activityCenterProjectId' => $testProjectB['id']]);
            $this->fail('should fail');
        } catch (ClientException $e) {
            $this->assertSame('Project not found', $e->getMessage());
        }

        // move project A under Org B .
        $this->normalUserClient->changeProjectOrganization($testProjectA['id'], $organizationB['id']);

        $orgADetail = $this->normalUserClient->getOrganization($organizationA['id']);
        $this->assertNull($orgADetail['activityCenterProjectId']);

        // set activity center project in OrgB (because it is there) and delete the project
        $orgDetail = $this->normalUserClient->updateOrganization($organizationB['id'], ['activityCenterProjectId' => $testProjectA['id']]);
        $this->assertEquals($testProjectA['id'], $orgDetail['activityCenterProjectId']);

        $this->client->deleteProject($testProjectA['id']);
        $orgADetail = $this->normalUserClient->getOrganization($organizationB['id']);
        $this->assertNull($orgADetail['activityCenterProjectId']);
    }
}
