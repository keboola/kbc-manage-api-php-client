<?php
namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

class MaintainerInvitationsTest extends ClientTestCase
{
    private $maintainer;

    /**
     * Create empty maintainer without admins and decline all maintainer invitations for test users
     */
    public function setUp()
    {
        parent::setUp();

        $this->maintainer = $this->client->createMaintainer([
            'name' => self::TESTS_MAINTAINER_PREFIX . " - MaintainerJoinTest",
        ]);

        $this->client->addUserToMaintainer($this->maintainer['id'], ['email' => 'spam+spam@keboola.com']);
        $this->client->removeUserFromMaintainer($this->maintainer['id'], $this->superAdmin['id']);

        foreach ($this->normalUserClient->listMyMaintainerInvitations() as $invitation) {
            $this->normalUserClient->declineMyMaintainerInvitation($invitation['id']);
        }

        foreach ($this->client->listMyMaintainerInvitations() as $invitation) {
            $this->client->declineMyMaintainerInvitation($invitation['id']);
        }
    }

    public function testSuperAdminCanInvite(): void
    {
        $maintainerId = $this->maintainer['id'];

        $invitations = $this->client->listMaintainerInvitations($maintainerId);
        $this->assertCount(0, $invitations);

        $member = $this->findMaintainerMember($maintainerId, $this->normalUser['email']);
        $this->assertNull($member);

        $invitation = $this->client->inviteUserToMaintainer($maintainerId, ['email' => $this->normalUser['email']]);

        $this->assertEquals($this->normalUser['id'], $invitation['user']['id']);
        $this->assertEquals($this->normalUser['email'], $invitation['user']['email']);
        $this->assertEquals($this->normalUser['name'], $invitation['user']['name']);

        $this->assertEquals($this->superAdmin['id'], $invitation['creator']['id']);
        $this->assertEquals($this->superAdmin['email'], $invitation['creator']['email']);
        $this->assertEquals($this->superAdmin['name'], $invitation['creator']['name']);

        $invitations = $this->client->listMaintainerInvitations($maintainerId);
        $this->assertCount(1, $invitations);

        $this->assertEquals($invitation, reset($invitations));

        $this->assertEquals($invitation, $this->client->getMaintainerInvitation($maintainerId, $invitation['id']));

        $this->client->cancelMaintainerInvitation($maintainerId, $invitation['id']);

        $invitations = $this->client->listMaintainerInvitations($maintainerId);
        $this->assertCount(0, $invitations);

        $member = $this->findMaintainerMember($maintainerId, $this->normalUser['email']);
        $this->assertNull($member);
    }

    public function testMaintainerAdminCanInvite(): void
    {
        $maintainerId = $this->maintainer['id'];

        $this->client->addUserToMaintainer($maintainerId, ['email' => $this->normalUser['email']]);

        $invitations = $this->normalUserClient->listMaintainerInvitations($maintainerId);
        $this->assertCount(0, $invitations);

        $member = $this->findMaintainerMember($maintainerId, $this->superAdmin['email']);
        $this->assertNull($member);

        $invitation= $this->normalUserClient->inviteUserToMaintainer($maintainerId, ['email' => $this->superAdmin['email']]);

        $this->assertEquals($this->superAdmin['id'], $invitation['user']['id']);
        $this->assertEquals($this->superAdmin['email'], $invitation['user']['email']);
        $this->assertEquals($this->superAdmin['name'], $invitation['user']['name']);

        $this->assertEquals($this->normalUser['id'], $invitation['creator']['id']);
        $this->assertEquals($this->normalUser['email'], $invitation['creator']['email']);
        $this->assertEquals($this->normalUser['name'], $invitation['creator']['name']);

        $invitations = $this->normalUserClient->listMaintainerInvitations($maintainerId);
        $this->assertCount(1, $invitations);

        $this->assertEquals($invitation, reset($invitations));

        $this->assertEquals($invitation, $this->normalUserClient->getMaintainerInvitation($maintainerId, $invitation['id']));

        $this->normalUserClient->cancelMaintainerInvitation($maintainerId, $invitation['id']);

        $invitations = $this->normalUserClient->listMaintainerInvitations($maintainerId);
        $this->assertCount(0, $invitations);

        $member = $this->findMaintainerMember($maintainerId, $this->superAdmin['email']);
        $this->assertNull($member);
    }

    public function testRandomAdminCannotInvite(): void
    {
        $maintainerId = $this->maintainer['id'];

        $invitations = $this->client->listMaintainerInvitations($maintainerId);
        $this->assertCount(0, $invitations);

        $member = $this->findMaintainerMember($maintainerId, $this->superAdmin['email']);
        $this->assertNull($member);

        try {
            $this->normalUserClient->listMaintainerInvitations($maintainerId);
            $this->fail('List invitations should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        try {
            $this->normalUserClient->inviteUserToMaintainer($maintainerId, ['email' => $this->superAdmin['email']]);
            $this->fail('Invite someone should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $invitations = $this->client->listMaintainerInvitations($maintainerId);
        $this->assertCount(0, $invitations);

        $member = $this->findMaintainerMember($maintainerId, $this->superAdmin['email']);
        $this->assertNull($member);
    }

    public function testAdminAcceptsInvitation(): void
    {
        $maintainerId = $this->maintainer['id'];

        $invitations = $this->client->listMaintainerInvitations($maintainerId);
        $this->assertCount(0, $invitations);

        $this->client->inviteUserToMaintainer($maintainerId, ['email' => $this->normalUser['email']]);

        $invitations = $this->normalUserClient->listMyMaintainerInvitations();
        $this->assertCount(1, $invitations);

        $invitation = reset($invitations);

        $this->assertEquals($maintainerId, $invitation['maintainer']['id']);
        $this->assertEquals($this->maintainer['name'], $invitation['maintainer']['name']);

        $this->assertEquals($this->superAdmin['id'], $invitation['creator']['id']);
        $this->assertEquals($this->superAdmin['email'], $invitation['creator']['email']);
        $this->assertEquals($this->superAdmin['name'], $invitation['creator']['name']);

        $this->assertEquals($invitation, $this->normalUserClient->getMyMaintainerInvitation($invitation['id']));

        $this->normalUserClient->acceptMyMaintainerInvitation($invitation['id']);

        $invitations = $this->normalUserClient->listMyMaintainerInvitations();
        $this->assertCount(0, $invitations);

        $member = $this->findMaintainerMember($maintainerId, $this->normalUser['email']);
        $this->assertNotNull($member);

        $this->assertArrayHasKey('invitor', $member);

        $this->assertNotEmpty($member['invitor']);
        $this->assertNotEmpty($member['created']);
        $this->assertEquals($this->superAdmin['id'], $member['invitor']['id']);
        $this->assertEquals($this->superAdmin['email'], $member['invitor']['email']);
        $this->assertEquals($this->superAdmin['name'], $member['invitor']['name']);
    }

    public function testAdminDeclinesInvitation(): void
    {
        $maintainerId = $this->maintainer['id'];

        $invitations = $this->client->listMaintainerInvitations($maintainerId);
        $this->assertCount(0, $invitations);

        $this->client->inviteUserToMaintainer($maintainerId, ['email' => $this->normalUser['email']]);

        $invitations = $this->normalUserClient->listMyMaintainerInvitations();
        $this->assertCount(1, $invitations);

        $invitation = reset($invitations);

        $this->assertEquals($maintainerId, $invitation['maintainer']['id']);
        $this->assertEquals($this->maintainer['name'], $invitation['maintainer']['name']);

        $this->assertEquals($this->superAdmin['id'], $invitation['creator']['id']);
        $this->assertEquals($this->superAdmin['email'], $invitation['creator']['email']);
        $this->assertEquals($this->superAdmin['name'], $invitation['creator']['name']);

        $this->normalUserClient->declineMyMaintainerInvitation($invitation['id']);

        $invitations = $this->normalUserClient->listMyMaintainerInvitations();
        $this->assertCount(0, $invitations);

        $member = $this->findMaintainerMember($maintainerId, $this->normalUser['email']);
        $this->assertNull($member);
    }

    public function testCannotInviteAlreadyInvitedUser(): void
    {
        $maintainerId = $this->maintainer['id'];

        $this->client->addUserToMaintainer($maintainerId, ['email' => $this->normalUser['email']]);

        $this->normalUserClient->inviteUserToMaintainer($maintainerId, ['email' => $this->superAdmin['email']]);

        $invitations = $this->normalUserClient->listMaintainerInvitations($maintainerId);
        $this->assertCount(1, $invitations);

        // send invitation twice
        try {
            $this->normalUserClient->inviteUserToMaintainer($maintainerId, ['email' => $this->superAdmin['email']]);
            $this->fail('Invite user to maintainer twice should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertContains('already', $e->getMessage());
            $this->assertContains('invited', $e->getMessage());
        }

        $invitations = $this->normalUserClient->listMaintainerInvitations($maintainerId);
        $this->assertCount(1, $invitations);
    }

    public function testCannotInviteExistingMember(): void
    {
        $maintainerId = $this->maintainer['id'];

        $this->client->addUserToMaintainer($maintainerId, ['email' => $this->normalUser['email']]);

        $this->normalUserClient->addUserToMaintainer($maintainerId, ['email' => $this->superAdmin['email']]);

        try {
            $this->normalUserClient->inviteUserToMaintainer($maintainerId, ['email' => $this->superAdmin['email']]);
            $this->fail('Invite existing member to maintainer should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertContains('already', $e->getMessage());
            $this->assertContains('member', $e->getMessage());
        }

        $invitations = $this->normalUserClient->listMaintainerInvitations($maintainerId);
        $this->assertCount(0, $invitations);
    }

    public function testRandomAdminCannotManageInvitationsInMaintainer(): void
    {
        $maintainerId = $this->maintainer['id'];

        $this->client->addUserToMaintainer($maintainerId, ['email' => $this->normalUser['email']]);

        $invitations = $this->normalUserClient->listMaintainerInvitations($maintainerId);
        $this->assertCount(0, $invitations);

        $invitation = $this->normalUserClient->inviteUserToMaintainer($maintainerId, ['email' => $this->superAdmin['email']]);

        $invitations = $this->client->listMaintainerInvitations($maintainerId);
        $this->assertCount(1, $invitations);

        $this->assertEquals($invitation, reset($invitations));

        $this->normalUserClient->removeUserFromMaintainer($maintainerId, $this->normalUser['id']);

        try {
            $this->normalUserClient->cancelMaintainerInvitation($maintainerId, $invitation['id']);
            $this->fail('Cancel invitations should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $invitations = $this->client->listMaintainerInvitations($maintainerId);
        $this->assertCount(1, $invitations);
    }

    public function testDeletingMaintainerRemovesInvitations(): void
    {
        $maintainerId = $this->maintainer['id'];

        $this->client->addUserToMaintainer($maintainerId, ['email' => $this->normalUser['email']]);

        $this->normalUserClient->inviteUserToMaintainer($maintainerId, ['email' => $this->superAdmin['email']]);

        $invitations = $this->client->listMyMaintainerInvitations();
        $this->assertCount(1, $invitations);

        $this->client->deleteMaintainer($maintainerId);

        $invitations = $this->client->listMyMaintainerInvitations();
        $this->assertCount(0, $invitations);
    }

    public function testAddingAdminToMaintainerDeletesCorrespondingInvitation(): void
    {
        $inviteeEmail = $this->normalUser['email'];
        $secondInviteeEmail = 'spam@keboola.com';
        $maintainerId = $this->maintainer['id'];

        $this->client->joinMaintainer($maintainerId);

        $invitations = $this->client->listMaintainerInvitations($maintainerId);
        $this->assertCount(0, $invitations);

        $this->client->inviteUserToMaintainer($maintainerId, ['email' => $inviteeEmail]);
        $this->client->inviteUserToMaintainer($maintainerId, ['email' => $secondInviteeEmail]);

        $invitations = $this->client->listMaintainerInvitations($maintainerId);
        $this->assertCount(2, $invitations);

        $this->client->addUserToMaintainer($maintainerId, ['email' => $inviteeEmail]);

        $member = $this->findMaintainerMember($maintainerId, $inviteeEmail);
        $this->assertNotNull($member);

        $invitations = $this->client->listMaintainerInvitations($maintainerId);
        $this->assertCount(1, $invitations);

        $invitation = reset($invitations);

        $this->assertEquals($secondInviteeEmail, $invitation['user']['email']);

        $this->assertEquals($this->superAdmin['id'], $invitation['creator']['id']);
        $this->assertEquals($this->superAdmin['email'], $invitation['creator']['email']);
        $this->assertEquals($this->superAdmin['name'], $invitation['creator']['name']);
    }
}
