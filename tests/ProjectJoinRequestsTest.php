<?php
namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

class ProjectJoinRequestsTest extends ClientTestCase
{
    private $organization;

    public function setUp()
    {
        parent::setUp();

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $this->client->addUserToOrganization($organization['id'], ['email' => $this->normalUser['email']]);

        $isSuperAdminMaintainer = false;
        foreach ($this->client->listMaintainerMembers($this->testMaintainerId) as $member) {
            if ($member['id'] === $this->normalUser['id']) {
                $this->client->removeUserFromMaintainer($this->testMaintainerId, $member['id']);
            }

            if ($member['id'] === $this->superAdmin['id']) {
                $isSuperAdminMaintainer = true;
            }
        }

        if (!$isSuperAdminMaintainer) {
            $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->superAdmin['email']]);
        }

        $this->organization = $organization;

        foreach ($this->client->listMyProjectJoinRequests() as $joinRequest) {
            $this->client->deleteMyProjectJoinRequest($joinRequest['id']);
        }

        foreach ($this->normalUserClient->listMyProjectJoinRequests() as $joinRequest) {
            $this->normalUserClient->deleteMyProjectJoinRequest($joinRequest['id']);
        }
    }

    public function testSuperAdminRequestAccess(): void
    {
        $this->client->removeUserFromOrganization($this->organization['id'], $this->superAdmin['id']);
        $this->client->removeUserFromMaintainer($this->testMaintainerId, $this->superAdmin['id']);

        $this->normalUserClient->updateOrganization($this->organization['id'], [
            "allowAutoJoin" => 0
        ]);

        $projectId = $this->createProjectWithOrganizationMember();

        $projectUser = $this->findProjectUser($projectId, $this->superAdmin['email']);
        $this->assertNull($projectUser);

        $joinRequests = $this->client->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);

        $joinRequest = $this->client->requestAccessToProject($projectId);
        $joinRequest = $this->client->getProjectJoinRequest($projectId, $joinRequest['id']);

        $this->assertEquals('', $joinRequest['reason']);
        $this->assertEmpty($joinRequest['expires']);
        $this->assertEquals($this->superAdmin['id'], $joinRequest['user']['id']);
        $this->assertEquals($this->superAdmin['email'], $joinRequest['user']['email']);

        $joinRequests = $this->client->listProjectJoinRequests($projectId);
        $this->assertCount(1, $joinRequests);

        $this->assertEquals($joinRequest, reset($joinRequests));

        try {
            $this->client->approveProjectJoinRequest($projectId, $joinRequest['id']);
            $this->fail('Approve join request should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        try {
            $this->client->rejectProjectJoinRequest($projectId, $joinRequest['id']);
            $this->fail('Reject join request should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $joinRequests = $this->client->listProjectJoinRequests($projectId);
        $this->assertCount(1, $joinRequests);

        $this->assertEquals($joinRequest, reset($joinRequests));
    }

    public function testMaintainerAdminRequestAccess(): void
    {
        $this->client->removeUserFromOrganization($this->organization['id'], $this->normalUser['id']);
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $this->client->updateOrganization($this->organization['id'], [
            "allowAutoJoin" => 0
        ]);

        $projectId = $this->createProjectWithSuperAdminMember();

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNull($projectUser);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);

        $joinRequest = $this->normalUserClient->requestAccessToProject($projectId);
        $joinRequest = $this->normalUserClient->getProjectJoinRequest($projectId, $joinRequest['id']);

        $this->assertEquals('', $joinRequest['reason']);
        $this->assertEmpty($joinRequest['expires']);
        $this->assertEquals($this->normalUser['id'], $joinRequest['user']['id']);
        $this->assertEquals($this->normalUser['email'], $joinRequest['user']['email']);

        $joinRequests = $this->normalUserClient->listProjectJoinRequests($projectId);
        $this->assertCount(1, $joinRequests);

        $this->assertEquals($joinRequest, reset($joinRequests));

        try {
            $this->normalUserClient->approveProjectJoinRequest($projectId, $joinRequest['id']);
            $this->fail('Approve join request should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        try {
            $this->normalUserClient->rejectProjectJoinRequest($projectId, $joinRequest['id']);
            $this->fail('Reject join request should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $joinRequests = $this->normalUserClient->listProjectJoinRequests($projectId);
        $this->assertCount(1, $joinRequests);

        $this->assertEquals($joinRequest, reset($joinRequests));
    }

    public function testOrganizationAdminRequestAccessError(): void
    {
        $projectId = $this->createProjectWithSuperAdminMember();

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNull($projectUser);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);

        try {
            $this->normalUserClient->requestAccessToProject($projectId);
            $this->fail('Request access should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
        }

        $joinRequests = $this->normalUserClient->listProjectJoinRequests($projectId);
        $this->assertCount(0, $joinRequests);

        // project without auto-join
        $this->client->updateOrganization($this->organization['id'], [
            "allowAutoJoin" => 0
        ]);

        try {
            $this->normalUserClient->requestAccessToProject($projectId);
            $this->fail('Request access should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
        }

        $joinRequests = $this->normalUserClient->listProjectJoinRequests($projectId);
        $this->assertCount(0, $joinRequests);
    }

    public function testAdminRequestAccessError(): void
    {
        $this->client->removeUserFromOrganization($this->organization['id'], $this->normalUser['id']);

        $projectId = $this->createProjectWithSuperAdminMember();

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNull($projectUser);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);

        try {
            $this->normalUserClient->requestAccessToProject($projectId);
            $this->fail('Request access should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);

        // project without auto-join
        $this->client->updateOrganization($this->organization['id'], [
            "allowAutoJoin" => 0
        ]);

        try {
            $this->normalUserClient->requestAccessToProject($projectId);
            $this->fail('Request access should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);
    }

    public function testProjectMemberRequestAccessError(): void
    {
        $projectId = $this->createProjectWithOrganizationMember();

        $this->client->removeUserFromOrganization($this->organization['id'], $this->normalUser['id']);

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNotNull($projectUser);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);

        try {
            $this->normalUserClient->requestAccessToProject($projectId);
            $this->fail('Request access should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
        }

        $joinRequests = $this->normalUserClient->listProjectJoinRequests($projectId);
        $this->assertCount(0, $joinRequests);

        // project without auto-join
        $this->client->updateOrganization($this->organization['id'], [
            "allowAutoJoin" => 0
        ]);

        try {
            $this->normalUserClient->requestAccessToProject($projectId);
            $this->fail('Request access should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
        }

        $joinRequests = $this->normalUserClient->listProjectJoinRequests($projectId);
        $this->assertCount(0, $joinRequests);
    }

    public function testSuperAdminRequestAccessError(): void
    {
        $this->client->removeUserFromOrganization($this->organization['id'], $this->superAdmin['id']);
        $this->client->removeUserFromMaintainer($this->testMaintainerId, $this->superAdmin['id']);

        $projectId = $this->createProjectWithOrganizationMember();

        $projectUser = $this->findProjectUser($projectId, $this->superAdmin['email']);
        $this->assertNull($projectUser);

        $joinRequests = $this->client->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);

        try {
            $this->client->requestAccessToProject($projectId);
            $this->fail('Request access should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
        }

        $joinRequests = $this->client->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);
    }

    public function testMaintainerAdminRequestAccessError(): void
    {
        $this->client->removeUserFromOrganization($this->organization['id'], $this->normalUser['id']);
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $projectId = $this->createProjectWithSuperAdminMember();

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNull($projectUser);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);

        try {
            $this->normalUserClient->requestAccessToProject($projectId);
            $this->fail('Request access should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
        }

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);
    }

    public function testOrganizationAdminApproveError()
    {
        $projectId = $this->createProjectWithSuperAdminMember();

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNull($projectUser);

        $this->normalUserClient->updateOrganization($this->organization['id'], [
            "allowAutoJoin" => 0
        ]);

        $this->client->removeUserFromOrganization($this->organization['id'], $this->normalUser['id']);
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $joinRequest = $this->normalUserClient->requestAccessToProject($projectId);
        $joinRequest = $this->client->getProjectJoinRequest($projectId, $joinRequest['id']);

        $this->assertEquals('', $joinRequest['reason']);
        $this->assertEmpty($joinRequest['expires']);
        $this->assertEquals($this->normalUser['id'], $joinRequest['user']['id']);
        $this->assertEquals($this->normalUser['email'], $joinRequest['user']['email']);

        $joinRequests = $this->client->listProjectJoinRequests($projectId);
        $this->assertCount(1, $joinRequests);

        $this->assertEquals($joinRequest, reset($joinRequests));

        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        try {
            $this->normalUserClient->approveProjectJoinRequest($projectId, $joinRequest['id']);
            $this->fail('Approve join request should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        try {
            $this->normalUserClient->rejectProjectJoinRequest($projectId, $joinRequest['id']);
            $this->fail('Reject join request should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $joinRequests = $this->client->listProjectJoinRequests($projectId);
        $this->assertCount(1, $joinRequests);

        $this->assertEquals($joinRequest, reset($joinRequests));
    }

    public function testProjectMemberApproveJoinRequest()
    {
        $projectId = $this->createProjectWithOrganizationMember();

        $this->client->removeUserFromOrganization($this->organization['id'], $this->normalUser['id']);

        $this->client->updateOrganization($this->organization['id'], [
            "allowAutoJoin" => 0
        ]);

        $this->client->removeUserFromOrganization($this->organization['id'], $this->superAdmin['id']);

        $joinRequest = $this->client->requestAccessToProject(
            $projectId,
            [
                'reason' => 'Testing reason propagation',
                'expirationSeconds' => 3600
            ]
        );

        $joinRequests = $this->client->listMyProjectJoinRequests();
        $this->assertCount(1, $joinRequests);

        $joinRequest = $this->normalUserClient->getProjectJoinRequest($projectId, $joinRequest['id']);

        $this->assertEquals('Testing reason propagation', $joinRequest['reason']);
        $this->assertNotEmpty($joinRequest['expires']);
        $this->assertEquals($this->superAdmin['id'], $joinRequest['user']['id']);
        $this->assertEquals($this->superAdmin['email'], $joinRequest['user']['email']);

        $joinRequests = $this->normalUserClient->listProjectJoinRequests($projectId);
        $this->assertCount(1, $joinRequests);

        $this->assertEquals($joinRequest, reset($joinRequests));

        $projectUser = $this->findProjectUser($projectId, $this->superAdmin['email']);
        $this->assertNull($projectUser);

        $this->normalUserClient->approveProjectJoinRequest($projectId, $joinRequest['id']);

        $projectUser = $this->findProjectUser($projectId, $this->superAdmin['email']);
        $this->assertNotNull($projectUser);

        $this->assertArrayHasKey('approver', $projectUser);

        $this->assertEquals($this->normalUser['id'], $projectUser['approver']['id']);
        $this->assertEquals($this->normalUser['email'], $projectUser['approver']['email']);
        $this->assertEquals($this->normalUser['name'], $projectUser['approver']['name']);
        $this->assertEquals($projectUser['reason'], $joinRequest['reason']);
        $this->assertEquals($projectUser['expires'], $joinRequest['expires']);
        $this->assertNotEmpty($projectUser['expires']);

        $joinRequests = $this->normalUserClient->listProjectJoinRequests($projectId);
        $this->assertCount(0, $joinRequests);
    }

    public function testProjectMemberRejectJoinRequest()
    {
        $projectId = $this->createProjectWithOrganizationMember();

        $this->client->removeUserFromOrganization($this->organization['id'], $this->normalUser['id']);

        $this->client->updateOrganization($this->organization['id'], [
            "allowAutoJoin" => 0
        ]);

        $this->client->removeUserFromOrganization($this->organization['id'], $this->superAdmin['id']);

        $joinRequest = $this->client->requestAccessToProject($projectId);

        $joinRequest = $this->normalUserClient->getProjectJoinRequest($projectId, $joinRequest['id']);

        $this->assertEquals('', $joinRequest['reason']);
        $this->assertEmpty($joinRequest['expires']);
        $this->assertEquals($this->superAdmin['id'], $joinRequest['user']['id']);
        $this->assertEquals($this->superAdmin['email'], $joinRequest['user']['email']);

        $joinRequests = $this->normalUserClient->listProjectJoinRequests($projectId);
        $this->assertCount(1, $joinRequests);

        $this->assertEquals($joinRequest, reset($joinRequests));

        $projectUser = $this->findProjectUser($projectId, $this->superAdmin['email']);
        $this->assertNull($projectUser);

        $this->normalUserClient->rejectProjectJoinRequest($projectId, $joinRequest['id']);

        $projectUser = $this->findProjectUser($projectId, $this->superAdmin['email']);
        $this->assertNull($projectUser);

        $joinRequests = $this->normalUserClient->listProjectJoinRequests($projectId);
        $this->assertCount(0, $joinRequests);
    }

    public function testProjectDeleteRemovesJoinRequests()
    {
        $this->client->removeUserFromOrganization($this->organization['id'], $this->normalUser['id']);
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $this->client->updateOrganization($this->organization['id'], [
            "allowAutoJoin" => 0
        ]);

        $projectId = $this->createProjectWithSuperAdminMember();

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);

        $this->normalUserClient->requestAccessToProject($projectId);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(1, $joinRequests);

        $this->client->deleteProject($projectId);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);

        $this->client->undeleteProject($projectId);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);
    }

    public function testJoinRequestExpiration()
    {
        $this->client->removeUserFromOrganization($this->organization['id'], $this->normalUser['id']);
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $this->client->updateOrganization($this->organization['id'], [
            "allowAutoJoin" => 0
        ]);

        $projectId = $this->createProjectWithSuperAdminMember();

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);

        $this->normalUserClient->requestAccessToProject($projectId, [
            'expirationSeconds' => 20,
        ]);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(1, $joinRequests);

        // the next time the cron runs the invitation should be removed.
        sleep(120);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);
    }

    public function testMyJoinRequest()
    {
        $this->client->removeUserFromOrganization($this->organization['id'], $this->normalUser['id']);
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $this->client->updateOrganization($this->organization['id'], [
            "allowAutoJoin" => 0
        ]);

        $projectId = $this->createProjectWithSuperAdminMember();
        $project = $this->client->getProject($projectId);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);

        $joinRequest = $this->normalUserClient->requestAccessToProject($projectId);

        $this->assertEquals('', $joinRequest['reason']);
        $this->assertEmpty($joinRequest['expires']);
        $this->assertEquals($project['id'], $joinRequest['project']['id']);
        $this->assertEquals($project['name'], $joinRequest['project']['name']);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(1, $joinRequests);

        $this->assertEquals($joinRequest, reset($joinRequests));

        $this->assertEquals($joinRequest, $this->normalUserClient->getMyProjectJoinRequest($joinRequest['id']));

        $this->normalUserClient->deleteMyProjectJoinRequest($joinRequest['id']);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);
    }


    public function testJoinRequestConflictsError()
    {
        $this->client->removeUserFromOrganization($this->organization['id'], $this->normalUser['id']);
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $this->client->updateOrganization($this->organization['id'], [
            "allowAutoJoin" => 0
        ]);

        $projectId = $this->createProjectWithSuperAdminMember();

        $joinRequest = $this->normalUserClient->requestAccessToProject($projectId);

        try {
            $this->normalUserClient->requestAccessToProject($projectId);
            $this->fail('Request access to project twice should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertContains('already', $e->getMessage());
            $this->assertContains('sent', $e->getMessage());
        }

        $this->normalUserClient->deleteMyProjectJoinRequest($joinRequest['id']);

        $this->client->addUserToProject($projectId, ['email' => $this->normalUser['email']]);

        try {
            $this->normalUserClient->requestAccessToProject($projectId);
            $this->fail('Request access of member should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertContains('already', $e->getMessage());
            $this->assertContains('member', $e->getMessage());
        }
    }

    private function findProjectUser(int $projectId, string $userEmail): ?array
    {
        $projectUsers = $this->client->listProjectUsers($projectId);

        foreach ($projectUsers as $projectUser) {
            if ($projectUser['email'] === $userEmail) {
                return $projectUser;
            }
        }

        return null;
    }

    private function createProjectWithOrganizationMember(): int
    {
        $project = $this->normalUserClient->createProject($this->organization['id'], [
            'name' => 'My test',
        ]);

        return $project['id'];
    }

    private function createProjectWithSuperAdminMember(): int
    {
        $project = $this->client->createProject($this->organization['id'], [
            'name' => 'My test',
        ]);

        return $project['id'];
    }
}
