<?php

declare(strict_types=1);

namespace Keboola\ManageApiTest;

use Iterator;
use Keboola\ManageApi\ClientException;
use Keboola\ManageApi\ProjectRole;

final class ProjectInvitationsTest extends ClientTestCase
{
    private $organization;

    /**
     * Create empty organization without admins, remove admins from test maintainer and delete all their join requests
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => 'devel-tests+spam@keboola.com']);

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

        $this->client->addUserToOrganization($this->organization['id'], ['email' => 'devel-tests@keboola.com']);
        $this->client->removeUserFromOrganization($this->organization['id'], $this->superAdmin['id']);

        foreach ($this->normalUserClient->listMyProjectInvitations() as $invitation) {
            $this->normalUserClient->declineMyProjectInvitation($invitation['id']);
        }

        foreach ($this->client->listMyProjectInvitations() as $invitation) {
            $this->client->declineMyProjectInvitation($invitation['id']);
        }
    }

    public static function autoJoinProvider(): Iterator
    {
        yield [
            true,
        ];
        yield [
            false,
        ];
    }

    public static function inviteUserToProjectWithRoleData(): Iterator
    {
        yield [
            ProjectRole::ADMIN,
        ];
        yield [
            ProjectRole::GUEST,
        ];
        yield [
            ProjectRole::READ_ONLY,
        ];
        yield [
            ProjectRole::SHARE,
        ];
    }

    /**
     * @dataProvider autoJoinProvider
     * @param bool $allowAutoJoin
     */
    public function testSuperAdminCannotInviteRegardlessOfAllowAutoJoin(bool $allowAutoJoin): void
    {
        $inviteeEmail = 'devel-tests@keboola.com';
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember($this->organization['id']);

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
        $inviteeEmail = 'devel-tests@keboola.com';
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

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
        $inviteeEmail = 'devel-tests@keboola.com';
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

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
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $inviteeEmail = 'devel-tests@keboola.com';
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
        $inviteeEmail = 'devel-tests@keboola.com';
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember($this->organization['id']);

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
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);
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
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);
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
        $projectId = $this->createProjectWithNormalAdminMember($this->organization['id']);

        $this->normalUserClient->inviteUserToProject($projectId, ['email' => $this->superAdmin['email']]);

        $invitations = $this->normalUserClient->listProjectInvitations($projectId);
        $this->assertCount(1, $invitations);

        // send invitation twice
        try {
            $this->normalUserClient->inviteUserToProject($projectId, ['email' => $this->superAdmin['email']]);
            $this->fail('Invite user to project twice should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertStringContainsString('already', $e->getMessage());
            $this->assertStringContainsString('invited', $e->getMessage());
        }

        $invitations = $this->normalUserClient->listProjectInvitations($projectId);
        $this->assertCount(1, $invitations);
    }

    public function testCannotInviteExistingMember(): void
    {
        $inviteeEmail = 'devel-tests@keboola.com';
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember($this->organization['id']);

        $this->normalUserClient->addUserToProject($projectId, ['email' => $inviteeEmail]);

        try {
            $this->normalUserClient->inviteUserToProject($projectId, ['email' => $inviteeEmail]);
            $this->fail('Invite existing member to project should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertStringContainsString('already', $e->getMessage());
            $this->assertStringContainsString('member', $e->getMessage());
        }

        $invitations = $this->normalUserClient->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);
    }

    public function testCannotInviteYourself(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember($this->organization['id']);

        $this->normalUserClient->addUserToProject($projectId, ['email' => $this->superAdmin['email']]);
        $this->normalUserClient->removeUserFromProject($projectId, $this->normalUser['id']);

        try {
            $this->normalUserClient->inviteUserToProject($projectId, ['email' => $this->normalUser['email']]);
            $this->fail('Invite yourself to project should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertStringContainsString('You cannot invite yourself', $e->getMessage());
        }

        $invitations = $this->normalUserClient->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);
    }

    public function testCannotInviteUserHavingJoinRequest(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember($this->organization['id']);

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
            $this->assertStringContainsString('This user has already requested access', $e->getMessage());
        }

        $invitations = $this->normalUserClient->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);
    }

    public function testRandomAdminCannotManageInvitationsInProject(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember($this->organization['id']);

        $invitations = $this->client->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);

        $invitation = $this->normalUserClient->inviteUserToProject($projectId, ['email' => $this->superAdmin['email']]);

        $invitations = $this->client->listProjectInvitations($projectId);
        $this->assertCount(1, $invitations);

        $this->assertEquals($invitation, reset($invitations));

        $this->normalUserClient->addUserToProject($projectId, ['email' => 'devel-tests@keboola.com']);
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
        $projectId = $this->createProjectWithNormalAdminMember($this->organization['id']);

        $invitations = $this->client->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);

        $invitation = $this->normalUserClient->inviteUserToProject($projectId, ['email' => $this->superAdmin['email']]);

        $invitations = $this->client->listProjectInvitations($projectId);
        $this->assertCount(1, $invitations);

        $this->assertEquals($invitation, reset($invitations));

        $this->normalUserClient->addUserToProject($projectId, ['email' => 'devel-tests@keboola.com']);
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
        $projectId = $this->createProjectWithNormalAdminMember($this->organization['id']);

        $invitations = $this->client->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);

        $invitation = $this->normalUserClient->inviteUserToProject($projectId, ['email' => $this->superAdmin['email']]);

        $invitations = $this->client->listProjectInvitations($projectId);
        $this->assertCount(1, $invitations);

        $this->assertEquals($invitation, reset($invitations));

        $this->normalUserClient->addUserToProject($projectId, ['email' => 'devel-tests@keboola.com']);
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
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

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
        $projectId = $this->createProjectWithNormalAdminMember($this->organization['id']);

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
        $projectId = $this->createProjectWithNormalAdminMember($this->organization['id']);

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
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

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
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        try {
            $this->client->inviteUserToProject($projectId, [
                'email' => $this->normalUser['email'],
                'role' => 'invalid-role',
            ]);
            $this->fail('Create project membership with invalid role should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertMatchesRegularExpression('/Role .* is not valid. Allowed roles are: admin, guest/', $e->getMessage());
            $this->assertStringContainsString('invalid-role', $e->getMessage());
        }

        $invitations = $this->client->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);
    }

    public function testInviteUserToProjectWithInvalidEmail(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        try {
            $this->client->inviteUserToProject($projectId, [
                'email' => 'not email address at all ! ',
            ]);
            $this->fail('Create project membership with invalid email should produce error');
        } catch (ClientException $e) {
            $this->assertSame('Email address is not valid.', $e->getMessage());
            $this->assertEquals(422, $e->getCode());
        }

        $invitations = $this->client->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);
    }
}
