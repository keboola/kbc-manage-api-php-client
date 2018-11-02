<?php
namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

class ProjectInvitationsTest extends ClientTestCase
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

        foreach ($this->normalUserClient->listMyProjectInvitations() as $invitation) {
            $this->normalUserClient->declineMyProjectInvitation($invitation['id']);
        }

        foreach ($this->client->listMyProjectInvitations() as $invitation) {
            $this->client->declineMyProjectInvitation($invitation['id']);
        }
    }

    public function autoJoinProvider(): array
    {
        return [
            [
                true,
            ],
            [
                false,
            ],
        ];
    }

    /**
     * @dataProvider autoJoinProvider
     * @param bool $allowAutoJoin
     */
    public function testSuperAdminInvitesError(bool $allowAutoJoin): void
    {
        $inviteeEmail = 'spam@keboola.com';
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember();

        $this->normalUserClient->updateOrganization($this->organization['id'], [
            "allowAutoJoin" => $allowAutoJoin
        ]);

        $invitations = $this->client->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);

        $projectUser = $this->findProjectUser($projectId, $inviteeEmail);
        $this->assertNull($projectUser);

        try {
            $this->client->inviteUserToProject($projectId, ['email' => $inviteeEmail]);
            $this->fail('Invite someone should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $invitations = $this->client->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);

        $projectUser = $this->findProjectUser($projectId, $inviteeEmail);
        $this->assertNull($projectUser);
    }

    /**
     * @dataProvider autoJoinProvider
     * @param bool $allowAutoJoin
     */
    public function testMaintainerAdminInvitesError(bool $allowAutoJoin): void
    {
        $inviteeEmail = 'spam@keboola.com';
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember();

        $this->client->updateOrganization($this->organization['id'], [
            "allowAutoJoin" => $allowAutoJoin
        ]);

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $invitations = $this->normalUserClient->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);

        $projectUser = $this->findProjectUser($projectId, $inviteeEmail);
        $this->assertNull($projectUser);

        try {
            $this->normalUserClient->inviteUserToProject($projectId, ['email' => $inviteeEmail]);
            $this->fail('Invite someone should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $invitations = $this->normalUserClient->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);

        $projectUser = $this->findProjectUser($projectId, $inviteeEmail);
        $this->assertNull($projectUser);
    }

    /**
     * @dataProvider autoJoinProvider
     * @param bool $allowAutoJoin
     */
    public function testAdminInvitesError(bool $allowAutoJoin): void
    {
        $inviteeEmail = 'spam@keboola.com';
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember();

        $this->client->updateOrganization($this->organization['id'], [
            "allowAutoJoin" => $allowAutoJoin
        ]);

        $invitations = $this->client->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);

        $projectUser = $this->findProjectUser($projectId, $inviteeEmail);
        $this->assertNull($projectUser);

        try {
            $this->normalUserClient->listProjectInvitations($projectId);
            $this->fail('List invitations should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        try {
            $this->normalUserClient->inviteUserToProject($projectId, ['email' => $inviteeEmail]);
            $this->fail('Invite someone should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $invitations = $this->client->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);

        $projectUser = $this->findProjectUser($projectId, $inviteeEmail);
        $this->assertNull($projectUser);
    }

    /**
     * @dataProvider autoJoinProvider
     * @param bool $allowAutoJoin
     */
    public function testOrganizationAdminInvites(bool $allowAutoJoin): void
    {
        $inviteeEmail = 'spam@keboola.com';
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithSuperAdminMember();

        $this->normalUserClient->updateOrganization($this->organization['id'], [
            "allowAutoJoin" => $allowAutoJoin
        ]);

        $invitations = $this->normalUserClient->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);

        $projectUser = $this->findProjectUser($projectId, $inviteeEmail);
        $this->assertNull($projectUser);

        $invitation = $this->normalUserClient->inviteUserToProject($projectId, ['email' => $inviteeEmail]);

        $this->assertEquals('', $invitation['reason']);
        $this->assertEmpty($invitation['expires']);

        $invitee = $this->client->getUser($inviteeEmail);

        $this->assertEquals($invitee['id'], $invitation['user']['id']);
        $this->assertEquals($invitee['email'], $invitation['user']['email']);
        $this->assertEquals($invitee['name'], $invitation['user']['name']);

        $this->assertEquals($this->normalUser['id'], $invitation['creator']['id']);
        $this->assertEquals($this->normalUser['email'], $invitation['creator']['email']);
        $this->assertEquals($this->normalUser['name'], $invitation['creator']['name']);

        $invitations = $this->normalUserClient->listProjectInvitations($projectId);
        $this->assertCount(1, $invitations);

        $this->assertEquals($invitation, reset($invitations));

        $this->assertEquals($invitation, $this->normalUserClient->getProjectInvitation($projectId, $invitation['id']));

        $this->normalUserClient->cancelProjectInvitation($projectId, $invitation['id']);

        $invitations = $this->normalUserClient->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);

        $projectUser = $this->findProjectUser($projectId, $inviteeEmail);
        $this->assertNull($projectUser);
    }

    /**
     * @dataProvider autoJoinProvider
     * @param bool $allowAutoJoin
     */
    public function testProjectMemberInvites(bool $allowAutoJoin): void
    {
        $inviteeEmail = 'spam@keboola.com';
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember();

        $this->normalUserClient->updateOrganization($this->organization['id'], [
            "allowAutoJoin" => $allowAutoJoin,
        ]);

        $invitations = $this->normalUserClient->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);

        $projectUser = $this->findProjectUser($projectId, $inviteeEmail);
        $this->assertNull($projectUser);

        $invitation = $this->normalUserClient->inviteUserToProject($projectId, ['email' => $inviteeEmail]);

        $this->assertEquals('', $invitation['reason']);
        $this->assertEmpty($invitation['expires']);

        $invitee = $this->client->getUser($inviteeEmail);

        $this->assertEquals($invitee['id'], $invitation['user']['id']);
        $this->assertEquals($invitee['email'], $invitation['user']['email']);
        $this->assertEquals($invitee['name'], $invitation['user']['name']);

        $this->assertEquals($this->normalUser['id'], $invitation['creator']['id']);
        $this->assertEquals($this->normalUser['email'], $invitation['creator']['email']);
        $this->assertEquals($this->normalUser['name'], $invitation['creator']['name']);

        $invitations = $this->normalUserClient->listProjectInvitations($projectId);
        $this->assertCount(1, $invitations);

        $this->assertEquals($invitation, reset($invitations));

        $this->assertEquals($invitation, $this->normalUserClient->getProjectInvitation($projectId, $invitation['id']));

        $this->normalUserClient->cancelProjectInvitation($projectId, $invitation['id']);

        $invitations = $this->normalUserClient->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);

        $projectUser = $this->findProjectUser($projectId, $inviteeEmail);
        $this->assertNull($projectUser);
    }

    public function testAdminAcceptInvitation()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember();
        $project = $this->client->getProject($projectId);

        $invitations = $this->client->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);

        $this->client->inviteUserToProject($projectId, ['email' => $this->normalUser['email']]);

        $invitations = $this->normalUserClient->listMyProjectInvitations();
        $this->assertCount(1, $invitations);

        $invitation = reset($invitations);

        $this->assertEquals('', $invitation['reason']);
        $this->assertEmpty($invitation['expires']);

        $this->assertEquals($project['id'], $invitation['project']['id']);
        $this->assertEquals($project['name'], $invitation['project']['name']);

        $this->assertEquals($this->superAdmin['id'], $invitation['creator']['id']);
        $this->assertEquals($this->superAdmin['email'], $invitation['creator']['email']);
        $this->assertEquals($this->superAdmin['name'], $invitation['creator']['name']);

        $this->assertEquals($invitation, $this->normalUserClient->getMyProjectInvitation($invitation['id']));

        $this->normalUserClient->acceptMyProjectInvitation($invitation['id']);

        $invitations = $this->normalUserClient->listMyProjectInvitations();
        $this->assertCount(0, $invitations);

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNotNull($projectUser);

        $this->assertArrayHasKey('invitor', $projectUser);
        $this->assertArrayHasKey('approver', $projectUser);

        $this->assertNotEmpty($projectUser['invitor']);
        $this->assertEquals($this->superAdmin['id'], $projectUser['invitor']['id']);
        $this->assertEquals($this->superAdmin['email'], $projectUser['invitor']['email']);
        $this->assertEquals($this->superAdmin['name'], $projectUser['invitor']['name']);

        $this->assertNull($projectUser['approver']);
    }

    public function testAdminDeclineInvitation()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember();
        $project = $this->client->getProject($projectId);

        $invitations = $this->client->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);

        $this->client->inviteUserToProject($projectId, ['email' => $this->normalUser['email']]);

        $invitations = $this->normalUserClient->listMyProjectInvitations();
        $this->assertCount(1, $invitations);

        $invitation = reset($invitations);

        $this->assertEquals('', $invitation['reason']);
        $this->assertEmpty($invitation['expires']);

        $this->assertEquals($project['id'], $invitation['project']['id']);
        $this->assertEquals($project['name'], $invitation['project']['name']);

        $this->assertEquals($this->superAdmin['id'], $invitation['creator']['id']);
        $this->assertEquals($this->superAdmin['email'], $invitation['creator']['email']);
        $this->assertEquals($this->superAdmin['name'], $invitation['creator']['name']);

        $this->normalUserClient->declineMyProjectInvitation($invitation['id']);

        $invitations = $this->normalUserClient->listMyProjectInvitations();
        $this->assertCount(0, $invitations);

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNull($projectUser);
    }

    public function testInviteDuplicityError()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember();

        $this->normalUserClient->inviteUserToProject($projectId, ['email' => $this->superAdmin['email']]);

        $invitations = $this->normalUserClient->listProjectInvitations($projectId);
        $this->assertCount(1, $invitations);

        // send invitation twice
        try {
            $this->normalUserClient->inviteUserToProject($projectId, ['email' => $this->superAdmin['email']]);
            $this->fail('Invite user to project twice should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertContains('already', $e->getMessage());
            $this->assertContains('invited', $e->getMessage());
        }

        $invitations = $this->normalUserClient->listProjectInvitations($projectId);
        $this->assertCount(1, $invitations);
    }

    public function testInviteMemberError()
    {
        $inviteeEmail = 'spam@keboola.com';
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember();

        $this->normalUserClient->addUserToProject($projectId, ['email' => $inviteeEmail]);

        try {
            $this->normalUserClient->inviteUserToProject($projectId, ['email' => $inviteeEmail]);
            $this->fail('Invite existing member to project should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertContains('already', $e->getMessage());
            $this->assertContains('member', $e->getMessage());
        }

        $invitations = $this->normalUserClient->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);
    }

    public function testInviteSelfError()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember();

        $this->normalUserClient->addUserToProject($projectId, ['email' => $this->superAdmin['email']]);
        $this->normalUserClient->removeUserFromProject($projectId, $this->normalUser['id']);

        try {
            $this->normalUserClient->inviteUserToProject($projectId, ['email' => $this->normalUser['email']]);
            $this->fail('Invite yourself to project should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertContains('You cannot invite yourself', $e->getMessage());
        }

        $invitations = $this->normalUserClient->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);
    }

    public function testInviteRequesterError()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember();

        $this->normalUserClient->addUserToProject($projectId, ['email' => $this->superAdmin['email']]);
        $this->normalUserClient->removeUserFromProject($projectId, $this->normalUser['id']);
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);
        $this->client->removeUserFromOrganization($this->organization['id'], $this->normalUser['id']);
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);

        $this->client->updateOrganization($this->organization['id'], [
            "allowAutoJoin" => 0
        ]);

        $this->normalUserClient->requestAccessToProject($projectId);

        try {
            $this->client->inviteUserToProject($projectId, ['email' => $this->normalUser['email']]);
            $this->fail('Invite user having join request should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertContains('This user has already requested access', $e->getMessage());
        }

        $invitations = $this->normalUserClient->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);
    }

    public function testInvitationCancelError()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember();

        $invitations = $this->client->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);

        $invitation = $this->normalUserClient->inviteUserToProject($projectId, ['email' => $this->superAdmin['email']]);

        $invitations = $this->client->listProjectInvitations($projectId);
        $this->assertCount(1, $invitations);

        $this->assertEquals($invitation, reset($invitations));

        $this->normalUserClient->addUserToProject($projectId, ['email' => 'spam@keboola.com']);
        $this->normalUserClient->removeUserFromProject($projectId, $this->normalUser['id']);
        $this->normalUserClient->removeUserFromOrganization($this->organization['id'], $this->normalUser['id']);

        // normal admin
        try {
            $this->normalUserClient->cancelProjectInvitation($projectId, $invitation['id']);
            $this->fail('Cancel invitations should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $invitations = $this->client->listProjectInvitations($projectId);
        $this->assertCount(1, $invitations);

        // super admin
        try {
            $this->client->cancelProjectInvitation($projectId, $invitation['id']);
            $this->fail('Cancel invitations should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $invitations = $this->client->listProjectInvitations($projectId);
        $this->assertCount(1, $invitations);

        // maintainer admin
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        try {
            $this->normalUserClient->cancelProjectInvitation($projectId, $invitation['id']);
            $this->fail('Cancel invitations should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $invitations = $this->client->listProjectInvitations($projectId);
        $this->assertCount(1, $invitations);
    }

    public function testReasonAndExpiresPropagation()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember();

        $projectUser = $this->findProjectUser($projectId, $this->superAdmin['email']);
        $this->assertNull($projectUser);

        $invitations = $this->normalUserClient->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);

        $invitation = $this->normalUserClient->inviteUserToProject($projectId, [
            'email' => $this->superAdmin['email'],
            'reason' => 'Testing reason propagation',
            'expirationSeconds' => 3600
        ]);

        $this->assertEquals('Testing reason propagation', $invitation['reason']);
        $this->assertNotEmpty($invitation['expires']);

        $this->client->acceptMyProjectInvitation($invitation['id']);

        $invitations = $this->client->listMyProjectInvitations();
        $this->assertCount(0, $invitations);

        $projectUser = $this->findProjectUser($projectId, $this->superAdmin['email']);
        $this->assertNotNull($projectUser);

        $this->assertEquals($projectUser['reason'], $invitation['reason']);
        $this->assertNotEmpty($projectUser['expires']);
    }

    public function testProjectDeleteRemovesInvitations()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember();

        $this->normalUserClient->inviteUserToProject($projectId, ['email' => $this->superAdmin['email']]);

        $invitations = $this->client->listMyProjectInvitations();
        $this->assertCount(1, $invitations);

        $this->client->deleteProject($projectId);

        $invitations = $this->client->listMyProjectInvitations();
        $this->assertCount(0, $invitations);

        $this->client->undeleteProject($projectId);

        $invitations = $this->client->listMyProjectInvitations();
        $this->assertCount(0, $invitations);
    }

    public function testInvitationExpiration()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember();

        $this->normalUserClient->inviteUserToProject($projectId, [
            'email' => $this->superAdmin['email'],
            'expirationSeconds' => 20,
        ]);

        $invitations = $this->client->listMyProjectInvitations();
        $this->assertCount(1, $invitations);

        // the next time the cron runs the invitation should be removed.
        sleep(120);

        $invitations = $this->client->listMyProjectInvitations();
        $this->assertCount(0, $invitations);
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
