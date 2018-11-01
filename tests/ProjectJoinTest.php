<?php
namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

class ProjectJoinTest extends ClientTestCase
{
    private $organization;

    /**
     * Create empty organization without admins, remove admins from test maintainer and delete all their join requests
     */
    public function setUp()
    {
        parent::setUp();

        $this->organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $this->client->removeUserFromOrganization($this->organization['id'], $this->superAdmin['id']);

        foreach ($this->client->listMaintainerMembers($this->testMaintainerId) as $member) {
            if ($member['id'] === $this->normalUser['id']) {
                $this->client->removeUserFromMaintainer($this->testMaintainerId, $member['id']);
            }

            if ($member['id'] === $this->superAdmin['id']) {
                $this->client->removeUserFromMaintainer($this->testMaintainerId, $member['id']);
            }
        }

        foreach ($this->normalUserClient->listMyProjectJoinRequests() as $joinRequest) {
            $this->normalUserClient->deleteMyProjectJoinRequest($joinRequest['id']);
        }

        foreach ($this->client->listMyProjectJoinRequests() as $joinRequest) {
            $this->client->deleteMyProjectJoinRequest($joinRequest['id']);
        }
    }

    public function testSuperAdminJoinProject(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember();

        $projectUser = $this->findProjectUser($projectId, $this->superAdmin['email']);
        $this->assertNull($projectUser);

        $this->client->joinProject($projectId);

        $projectUser = $this->findProjectUser($projectId, $this->superAdmin['email']);
        $this->assertNotNull($projectUser);

        $this->assertArrayHasKey('approver', $projectUser);

        $this->assertEquals($this->superAdmin['id'], $projectUser['approver']['id']);
        $this->assertEquals($this->superAdmin['email'], $projectUser['approver']['email']);
        $this->assertEquals($this->superAdmin['name'], $projectUser['approver']['name']);
    }

    public function testMaintainerAdminJoinProject(): void
    {
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithSuperAdminMember();

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNull($projectUser);

        $this->normalUserClient->joinProject($projectId);

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNotNull($projectUser);
        $this->assertArrayHasKey('approver', $projectUser);

        $this->assertEquals($this->normalUser['id'], $projectUser['approver']['id']);
        $this->assertEquals($this->normalUser['email'], $projectUser['approver']['email']);
        $this->assertEquals($this->normalUser['name'], $projectUser['approver']['name']);
    }

    public function testSuperAdminJoinProjectError(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember();

        $this->normalUserClient->updateOrganization($this->organization['id'], [
            "allowAutoJoin" => 0
        ]);

        $projectUser = $this->findProjectUser($projectId, $this->superAdmin['email']);
        $this->assertNull($projectUser);

        try {
            $this->client->joinProject($projectId);
            $this->fail('Project join should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $projectUser = $this->findProjectUser($projectId, $this->superAdmin['email']);
        $this->assertNull($projectUser);
    }

    public function testMaintainerAdminJoinProjectError(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember();

        $this->client->updateOrganization($this->organization['id'], [
            "allowAutoJoin" => 0
        ]);

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNull($projectUser);

        try {
            $this->normalUserClient->joinProject($projectId);
            $this->fail('Project join should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNull($projectUser);
    }

    public function testOrganizationAdminJoinProject(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithSuperAdminMember();

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNull($projectUser);

        $this->normalUserClient->joinProject($projectId);

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNotNull($projectUser);
        $this->assertArrayHasKey('approver', $projectUser);

        $this->assertEquals($this->normalUser['id'], $projectUser['approver']['id']);
        $this->assertEquals($this->normalUser['email'], $projectUser['approver']['email']);
        $this->assertEquals($this->normalUser['name'], $projectUser['approver']['name']);

        // project without auto-join
        $this->normalUserClient->updateOrganization($this->organization['id'], [
            "allowAutoJoin" => 0
        ]);

        $this->normalUserClient->removeUserFromProject($projectId, $this->normalUser['id']);

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNull($projectUser);

        $this->normalUserClient->joinProject($projectId);

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNotNull($projectUser);
        $this->assertArrayHasKey('approver', $projectUser);

        $this->assertEquals($this->normalUser['id'], $projectUser['approver']['id']);
        $this->assertEquals($this->normalUser['email'], $projectUser['approver']['email']);
        $this->assertEquals($this->normalUser['name'], $projectUser['approver']['name']);
    }

    public function testOrganizationAdminJoinProjectRequestDelete()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember();

        $this->client->updateOrganization($this->organization['id'], [
            "allowAutoJoin" => 0
        ]);

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);

        $this->normalUserClient->requestAccessToProject($projectId);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(1, $joinRequests);

        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $this->normalUserClient->joinProject($projectId);

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNotNull($projectUser);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);
    }

    public function testOrganizationAdminJoinProjectInvitationDelete()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember();

        $this->client->updateOrganization($this->organization['id'], [
            "allowAutoJoin" => 0
        ]);

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);

        $this->client->inviteUserToProject($projectId, ['email' => $this->normalUser['email']]);

        $joinRequests = $this->client->listProjectInvitations($projectId);
        $this->assertCount(1, $joinRequests);

        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $this->normalUserClient->joinProject($projectId);

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNotNull($projectUser);

        $joinRequests = $this->client->listProjectInvitations($projectId);
        $this->assertCount(0, $joinRequests);
    }

    public function testAdminJoinProjectError(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember();

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNull($projectUser);

        try {
            $this->normalUserClient->joinProject($projectId);
            $this->fail('Project join should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNull($projectUser);

        // project without auto-join
        $this->client->updateOrganization($this->organization['id'], [
            "allowAutoJoin" => 0
        ]);

        try {
            $this->normalUserClient->joinProject($projectId);
            $this->fail('Project join should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNull($projectUser);
    }

    public function testProjectMemberJoinProjectError(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember();

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNotNull($projectUser);

        try {
            $this->normalUserClient->joinProject($projectId);
            $this->fail('Project join should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
        }

        // project without auto-join
        $this->normalUserClient->updateOrganization($this->organization['id'], [
            "allowAutoJoin" => 0
        ]);

        try {
            $this->normalUserClient->joinProject($projectId);
            $this->fail('Project join should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
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

    private function createProjectWithNormalAdminMember(): int
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
