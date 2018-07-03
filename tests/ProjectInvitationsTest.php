<?php
namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

class ProjectInvitationsTest extends ClientTestCase
{
    public function testCreateDeleteInvitation()
    {
        $project = $this->initTestProject();

        $invitations = $this->client->listProjectInvitations($project['id']);
        $this->assertCount(0, $invitations);

        $invitation = $this->client->inviteUserToProject($project['id'], ['email' => $this->normalUser['email']]);

        $this->assertEquals('', $invitation['reason']);
        $this->assertEmpty($invitation['expires']);

        $this->assertEquals($this->normalUser['id'], $invitation['user']['id']);
        $this->assertEquals($this->normalUser['email'], $invitation['user']['email']);
        $this->assertEquals($this->normalUser['name'], $invitation['user']['name']);

        $this->assertEquals($this->superAdmin['id'], $invitation['creator']['id']);
        $this->assertEquals($this->superAdmin['email'], $invitation['creator']['email']);
        $this->assertEquals($this->superAdmin['name'], $invitation['creator']['name']);

        $invitations = $this->client->listProjectInvitations($project['id']);
        $this->assertCount(1, $invitations);

        $this->assertEquals($invitation, reset($invitations));

        $this->client->cancelProjectInvitation($project['id'], $invitation['id']);

        $invitations = $this->client->listProjectInvitations($project['id']);
        $this->assertCount(0, $invitations);
    }

    public function testCreateInvitationError()
    {
        $project = $this->initTestProject();

        $invitation = $this->client->inviteUserToProject($project['id'], ['email' => $this->normalUser['email']]);

        try {
            $this->client->inviteUserToProject($project['id'], ['email' => $this->normalUser['email']]);
            $this->fail('Invite user to project twice should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertContains('already', $e->getMessage());
            $this->assertContains('invited', $e->getMessage());
        }

        $this->client->addUserToProject($project['id'], ['email' => 'spam@keboola.com']);

        try {
            $this->client->inviteUserToProject($project['id'], ['email' => 'spam@keboola.com']);
            $this->fail('Invite existing member to project should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertContains('already', $e->getMessage());
            $this->assertContains('member', $e->getMessage());
        }

        $this->client->cancelProjectInvitation($project['id'], $invitation['id']);
    }

    public function testNormalUserError()
    {
        $project = $this->initTestProject();

        // restricted operations for normal user without membership
        try {
            $this->normalUserClient->listProjectInvitations($project['id']);
            $this->fail('Normal user cannot list invitations to random projects');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        try {
            $this->normalUserClient->inviteUserToProject($project['id'], ['email' => 'spam@keboola.com']);
            $this->fail('Normal user cannot invite users to random projects');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        // normal user - invite other user
        $this->client->addUserToProject($project['id'], ['email' => $this->normalUser['email']]);

        $invitation = $this->normalUserClient->inviteUserToProject($project['id'], ['email' => 'spam@keboola.com']);

        $invitations = $this->normalUserClient->listProjectInvitations($project['id']);
        $this->assertCount(1, $invitations);

        $this->assertEquals($invitation, reset($invitations));

        // restricted operations for normal user without membership
        $this->client->removeUserFromProject($project['id'], $this->normalUser['id']);

        try {
            $this->normalUserClient->listProjectInvitations($project['id']);
            $this->fail('Normal user cannot cancel invitations to random projects');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        // normal user - cancel invitation
        $this->client->addUserToProject($project['id'], ['email' => $this->normalUser['email']]);

        $this->normalUserClient->cancelProjectInvitation($project['id'], $invitation['id']);

        $invitations = $this->normalUserClient->listProjectInvitations($project['id']);
        $this->assertCount(0, $invitations);
    }

    public function testAcceptInvitation()
    {
        $project = $this->initTestProject();

        $invitations = $this->client->listProjectInvitations($project['id']);
        $this->assertCount(0, $invitations);

        $this->client->inviteUserToProject($project['id'], ['email' => $this->normalUser['email']]);

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

        $this->normalUserClient->acceptMyProjectInvitation($invitation['id']);

        $invitations = $this->normalUserClient->listMyProjectInvitations();
        $this->assertCount(0, $invitations);

        $userFound = false;
        foreach ($this->normalUserClient->listProjectUsers($project['id']) as $user) {
            if ($user['id'] === $this->normalUser['id']) {
                $userFound = true;
            }
        }

        $this->assertTrue($userFound);
    }

    public function testDeclineInvitation()
    {
        $project = $this->initTestProject();

        $invitations = $this->client->listProjectInvitations($project['id']);
        $this->assertCount(0, $invitations);

        $this->client->inviteUserToProject($project['id'], ['email' => $this->normalUser['email']]);

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

        $userFound = false;
        foreach ($this->client->listProjectUsers($project['id']) as $user) {
            if ($user['id'] === $this->normalUser['id']) {
                $userFound = true;
            }
        }

        $this->assertFalse($userFound);
    }

    public function testReasonAndExpiresPropagation()
    {
        $project = $this->initTestProject();

        $invitations = $this->client->listProjectInvitations($project['id']);
        $this->assertCount(0, $invitations);

        $invitation = $this->client->inviteUserToProject($project['id'], [
            'email' => $this->normalUser['email'],
            'reason' => 'Testing reason propagation',
            'expirationSeconds' => 3600
        ]);

        $this->assertEquals('Testing reason propagation', $invitation['reason']);
        $this->assertNotEmpty($invitation['expires']);

        $this->normalUserClient->acceptMyProjectInvitation($invitation['id']);

        $invitations = $this->normalUserClient->listMyProjectInvitations();
        $this->assertCount(0, $invitations);

        $userMembership = null;
        foreach ($this->normalUserClient->listProjectUsers($project['id']) as $user) {
            if ($user['id'] === $this->normalUser['id']) {
                $userMembership = $user;
            }
        }

        $this->assertEquals($userMembership['reason'], $invitation['reason']);
        $this->assertNotEmpty($userMembership['expires']);
    }

    private function initTestProject()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
        ]);

        return $project;
    }
}