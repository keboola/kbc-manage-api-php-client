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

    public function testSuperAdminInvitesError()
    {
        $inviteeEmail = 'spam@keboola.com';
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember();

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

        // project without auto-join
        $this->normalUserClient->updateOrganization($this->organization['id'], [
            "allowAutoJoin" => 0
        ]);

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

    public function testMaintainerAdminInvitesError(): void
    {
        $inviteeEmail = 'spam@keboola.com';
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember();

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

        // project without auto-join
        $this->client->updateOrganization($this->organization['id'], [
            "allowAutoJoin" => 0
        ]);

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

    public function testAdminInvitesError(): void
    {
        $inviteeEmail = 'spam@keboola.com';
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember();

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

        // project without auto-join
        $this->client->updateOrganization($this->organization['id'], [
            "allowAutoJoin" => 0
        ]);

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

    public function testOrganizationAdminInvites(): void
    {
        $inviteeEmail = 'spam@keboola.com';
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithSuperAdminMember();

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

        // project without auto-join
        $this->normalUserClient->updateOrganization($this->organization['id'], [
            "allowAutoJoin" => 0
        ]);

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

        $this->assertEquals($invitation, $this->client->getProjectInvitation($projectId, $invitation['id']));

        $this->normalUserClient->cancelProjectInvitation($projectId, $invitation['id']);

        $invitations = $this->normalUserClient->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);

        $projectUser = $this->findProjectUser($projectId, $inviteeEmail);
        $this->assertNull($projectUser);
    }

    public function testProjectMemberInvites(): void
    {
        $inviteeEmail = 'spam@keboola.com';
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember();

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

        // project without auto-join
        $this->normalUserClient->updateOrganization($this->organization['id'], [
            "allowAutoJoin" => 0
        ]);

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

        $this->assertEquals($invitation, $this->client->getProjectInvitation($projectId, $invitation['id']));

        $this->normalUserClient->cancelProjectInvitation($projectId, $invitation['id']);

        $invitations = $this->normalUserClient->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);

        $projectUser = $this->findProjectUser($projectId, $inviteeEmail);
        $this->assertNull($projectUser);
    }

    public function testAcceptInvitation()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember();
        $project = $this->client->getProject($projectId);

        $invitations = $this->client->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);

        $this->normalUserClient->inviteUserToProject($projectId, ['email' => $this->superAdmin['email']]);

        $invitations = $this->client->listMyProjectInvitations();
        $this->assertCount(1, $invitations);

        $invitation = reset($invitations);

        $this->assertEquals('', $invitation['reason']);
        $this->assertEmpty($invitation['expires']);

        $this->assertEquals($project['id'], $invitation['project']['id']);
        $this->assertEquals($project['name'], $invitation['project']['name']);

        $this->assertEquals($this->normalUser['id'], $invitation['creator']['id']);
        $this->assertEquals($this->normalUser['email'], $invitation['creator']['email']);
        $this->assertEquals($this->normalUser['name'], $invitation['creator']['name']);

        $this->assertEquals($invitation, $this->client->getMyProjectInvitation($invitation['id']));

        $this->client->acceptMyProjectInvitation($invitation['id']);

        $invitations = $this->client->listMyProjectInvitations();
        $this->assertCount(0, $invitations);

        $projectUser = $this->findProjectUser($projectId, $this->superAdmin['email']);
        $this->assertNotNull($projectUser);

        $this->assertArrayHasKey('invitor', $projectUser);
        $this->assertArrayHasKey('approver', $projectUser);

        $this->assertNotEmpty($projectUser['invitor']);
        $this->assertEquals($this->normalUser['id'], $projectUser['invitor']['id']);
        $this->assertEquals($this->normalUser['email'], $projectUser['invitor']['email']);
        $this->assertEquals($this->normalUser['name'], $projectUser['invitor']['name']);

        $this->assertNull($projectUser['approver']);
    }

    public function testDeclineInvitation()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember();
        $project = $this->client->getProject($projectId);

        $invitations = $this->client->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);

        $this->normalUserClient->inviteUserToProject($projectId, ['email' => $this->superAdmin['email']]);

        $invitations = $this->client->listMyProjectInvitations();
        $this->assertCount(1, $invitations);

        $invitation = reset($invitations);

        $this->assertEquals('', $invitation['reason']);
        $this->assertEmpty($invitation['expires']);

        $this->assertEquals($project['id'], $invitation['project']['id']);
        $this->assertEquals($project['name'], $invitation['project']['name']);

        $this->assertEquals($this->normalUser['id'], $invitation['creator']['id']);
        $this->assertEquals($this->normalUser['email'], $invitation['creator']['email']);
        $this->assertEquals($this->normalUser['name'], $invitation['creator']['name']);

        $this->client->declineMyProjectInvitation($invitation['id']);

        $invitations = $this->client->listMyProjectInvitations();
        $this->assertCount(0, $invitations);

        $projectUser = $this->findProjectUser($projectId, $this->superAdmin['email']);
        $this->assertNull($projectUser);
    }

    public function testInviteConflictsError()
    {
        $inviteeEmail = 'spam@keboola.com';
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember();

        $this->normalUserClient->inviteUserToProject($projectId, ['email' => $this->superAdmin['email']]);

        $invitations = $this->normalUserClient->listProjectInvitations($projectId);
        $this->assertCount(1, $invitations);

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
        $this->assertCount(1, $invitations);

        $this->normalUserClient->removeUserFromProject($projectId, $this->normalUser['id']);

        try {
            $this->normalUserClient->inviteUserToProject($projectId, ['email' => $this->normalUser['email']]);
            $this->fail('Invite existing member to project should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertContains('You cannot invite yourself', $e->getMessage());
        }

        $invitations = $this->normalUserClient->listProjectInvitations($projectId);
        $this->assertCount(1, $invitations);
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