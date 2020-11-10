<?php
namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;
use Keboola\ManageApi\ProjectRole;

class ProjectInvitationsTest extends ClientTestCase
{
    private $organization;

    /**
     * Create empty organization without admins, remove admins from test maintainer and delete all their join requests
     */
    public function setUp()
    {
        parent::setUp();

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => 'spam+spam@keboola.com']);

        foreach ($this->client->listMaintainerMembers($this->testMaintainerId) as $member) {
            if ($member['id'] === $this->normalUser['id']) {
                $this->client->removeUserFromMaintainer($this->testMaintainerId, $member['id']);
            }

            if ($member['id'] === $this->superAdmin['id']) {
                $this->client->removeUserFromMaintainer($this->testMaintainerId, $member['id']);
            }
        }

        $this->organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $this->client->addUserToOrganization($this->organization['id'], ['email' => 'spam@keboola.com']);
        $this->client->removeUserFromOrganization($this->organization['id'], $this->superAdmin['id']);

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

    public function inviteUserToProjectWithRoleData(): array
    {
        return [
            [
                ProjectRole::ADMIN,
            ],
            [
                ProjectRole::GUEST,
            ],
            [
                ProjectRole::READ_ONLY,
            ],
            [
                ProjectRole::SHARE,
            ],
        ];
    }

    /**
     * @dataProvider autoJoinProvider
     * @param bool $allowAutoJoin
     */
    public function testSuperAdminCannotInviteRegardlessOfAllowAutoJoin(bool $allowAutoJoin): void
    {
        $inviteeEmail = 'spam@keboola.com';
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember();

        $this->normalUserClient->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => $allowAutoJoin,
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
    public function testMaintainerAdminCannotInviteRegardlessOfAllowAutoJoin(bool $allowAutoJoin): void
    {
        $inviteeEmail = 'spam@keboola.com';
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember();

        $this->client->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => $allowAutoJoin,
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
    public function testRandomAdminCannotInviteRegardlessOfAllowAutoJoin(bool $allowAutoJoin): void
    {
        $inviteeEmail = 'spam@keboola.com';
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember();

        $this->client->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => $allowAutoJoin,
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
    public function testOrganizationAdminCanInviteRegardlessOfAllowAutoJoin(bool $allowAutoJoin): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember();

        $inviteeEmail = 'spam@keboola.com';
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $this->normalUserClient->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => $allowAutoJoin,
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
    public function testProjectMemberCanInviteRegardlessOfAllowAutoJoin(bool $allowAutoJoin): void
    {
        $inviteeEmail = 'spam@keboola.com';
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember();

        $this->normalUserClient->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => $allowAutoJoin,
        ]);

        $invitations = $this->normalUserClient->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);

        $projectUser = $this->findProjectUser($projectId, $inviteeEmail);
        $this->assertNull($projectUser);

        $invitation = $this->normalUserClient->inviteUserToProject($projectId, ['email' => $inviteeEmail]);

        $this->assertEquals('', $invitation['reason']);
        $this->assertEquals('admin', $invitation['role']);
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

    public function testAdminAcceptsInvitation(): void
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

    public function testAdminDeclinesInvitation(): void
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

    public function testCannotInviteAlreadyInvitedUser(): void
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

    public function testCannotInviteExistingMember(): void
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

    public function testCannotInviteYourself(): void
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

    public function testCannotInviteUserHavingJoinRequest(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember();

        $this->normalUserClient->addUserToProject($projectId, ['email' => $this->superAdmin['email']]);
        $this->normalUserClient->removeUserFromProject($projectId, $this->normalUser['id']);
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);
        $this->client->removeUserFromOrganization($this->organization['id'], $this->normalUser['id']);
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);

        $this->client->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => 0,
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

    public function testRandomAdminCannotManageInvitationsInProject(): void
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

        try {
            $this->normalUserClient->cancelProjectInvitation($projectId, $invitation['id']);
            $this->fail('Cancel invitations should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $invitations = $this->client->listProjectInvitations($projectId);
        $this->assertCount(1, $invitations);
    }

    public function testSuperAdminCannotManageInvitationInProject(): void
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

        try {
            $this->client->cancelProjectInvitation($projectId, $invitation['id']);
            $this->fail('Cancel invitations should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $invitations = $this->client->listProjectInvitations($projectId);
        $this->assertCount(1, $invitations);
    }

    public function testMaintainerAdminCannotManageInvitationInProject(): void
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

    /**
     * @dataProvider inviteUserToProjectWithRoleData
     */
    public function testInvitationAttributesPropagationToProjectMembership(string $role): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember();

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNull($projectUser);

        $invitations = $this->client->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);

        $invitation = $this->client->inviteUserToProject($projectId, [
            'email' => $this->normalUser['email'],
            'reason' => 'Testing reason propagation',
            'role' => $role,
            'expirationSeconds' => 3600,
        ]);

        $this->assertEquals('Testing reason propagation', $invitation['reason']);
        $this->assertEquals($role, $invitation['role']);
        $this->assertNotEmpty($invitation['expires']);

        $this->normalUserClient->acceptMyProjectInvitation($invitation['id']);

        $invitations = $this->normalUserClient->listMyProjectInvitations();
        $this->assertCount(0, $invitations);

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNotNull($projectUser);

        $this->assertEquals($invitation['reason'], $projectUser['reason']);
        $this->assertEquals($role, $projectUser['role']);
        $this->assertNotEmpty($projectUser['expires']);
    }

    public function testDeletingProjectRemovesInvitations(): void
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

    public function testInvitationExpiration(): void
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

    /**
     * @dataProvider inviteUserToProjectWithRoleData
     */
    public function testInviteUserToProjectWithRole(string $role): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember();

        $invitation = $this->client->inviteUserToProject($projectId, [
            'email' => $this->normalUser['email'],
            'role' => $role,
        ]);

        $this->assertEquals($role, $invitation['role']);

        $invitations = $this->client->listProjectInvitations($projectId);
        $this->assertCount(1, $invitations);

        $this->assertEquals($invitation, reset($invitations));
    }

    public function testInviteUserToProjectWithInvalidRole(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember();

        try {
            $this->client->inviteUserToProject($projectId, [
                'email' => $this->normalUser['email'],
                'role' => 'invalid-role',
            ]);
            $this->fail('Create project membership with invalid role should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertRegExp('/Role .* is not valid. Allowed roles are: admin, guest/', $e->getMessage());
            $this->assertContains('invalid-role', $e->getMessage());
        }

        $invitations = $this->client->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);
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
