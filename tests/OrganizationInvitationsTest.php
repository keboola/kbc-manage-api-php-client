<?php

declare(strict_types=1);

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

final class OrganizationInvitationsTest extends ClientTestCase
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

        $this->client->addUserToOrganization($this->organization['id'], ['email' => 'devel-tests+spam@keboola.com']);
        $this->client->removeUserFromOrganization($this->organization['id'], $this->superAdmin['id']);

        foreach ($this->normalUserClient->listMyOrganizationInvitations() as $invitation) {
            $this->normalUserClient->declineMyOrganizationInvitation($invitation['id']);
        }

        foreach ($this->client->listMyOrganizationInvitations() as $invitation) {
            $this->client->declineMyOrganizationInvitation($invitation['id']);
        }
    }

    public function autoJoinProvider(): \Iterator
    {
        yield [
            true,
        ];
        yield [
            false,
        ];
    }

    /**
     * @dataProvider autoJoinProvider
     * @param bool $allowAutoJoin
     */
    public function testSuperAdminCannotInviteRegardlessOfAllowAutoJoin(bool $allowAutoJoin): void
    {
        $inviteeEmail = 'devel-tests@keboola.com';
        $organizationId = $this->organization['id'];

        $this->client->addUserToOrganization($organizationId, ['email' => $this->normalUser['email']]);

        $this->normalUserClient->updateOrganization($organizationId, [
            'allowAutoJoin' => $allowAutoJoin,
        ]);

        $invitations = $this->client->listOrganizationInvitations($organizationId);
        $this->assertCount(0, $invitations);

        $member = $this->findOrganizationMember($organizationId, $inviteeEmail);
        $this->assertNull($member);

        try {
            $this->client->inviteUserToOrganization($organizationId, ['email' => $inviteeEmail]);
            $this->fail('Invite someone should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $invitations = $this->client->listOrganizationInvitations($organizationId);
        $this->assertCount(0, $invitations);

        $member = $this->findOrganizationMember($organizationId, $inviteeEmail);
        $this->assertNull($member);
    }

    /**
     * @dataProvider autoJoinProvider
     * @param bool $allowAutoJoin
     */
    public function testMaintainerCannotInviteRegardlessOfAllowAutoJoin(bool $allowAutoJoin): void
    {
        $inviteeEmail = 'devel-tests@keboola.com';
        $organizationId = $this->organization['id'];

        $this->client->addUserToOrganization($organizationId, ['email' => $this->superAdmin['email']]);

        $this->client->updateOrganization($organizationId, [
            'allowAutoJoin' => $allowAutoJoin,
        ]);

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $invitations = $this->normalUserClient->listOrganizationInvitations($organizationId);
        $this->assertCount(0, $invitations);

        $member = $this->findOrganizationMember($organizationId, $inviteeEmail);
        $this->assertNull($member);

        try {
            $this->normalUserClient->inviteUserToOrganization($organizationId, ['email' => $inviteeEmail]);
            $this->fail('Invite someone should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $invitations = $this->normalUserClient->listOrganizationInvitations($organizationId);
        $this->assertCount(0, $invitations);

        $member = $this->findOrganizationMember($organizationId, $inviteeEmail);
        $this->assertNull($member);
    }

    /**
     * @dataProvider autoJoinProvider
     * @param bool $allowAutoJoin
     */
    public function testRandomAdminCannotInviteRegardlessOfAllowAutoJoin(bool $allowAutoJoin): void
    {
        $inviteeEmail = 'devel-tests@keboola.com';
        $organizationId = $this->organization['id'];

        $this->client->addUserToOrganization($organizationId, ['email' => $this->superAdmin['email']]);

        $this->client->updateOrganization($organizationId, [
            'allowAutoJoin' => $allowAutoJoin,
        ]);

        $invitations = $this->client->listOrganizationInvitations($organizationId);
        $this->assertCount(0, $invitations);

        $member = $this->findOrganizationMember($organizationId, $inviteeEmail);
        $this->assertNull($member);

        try {
            $this->normalUserClient->listOrganizationInvitations($organizationId);
            $this->fail('List invitations should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        try {
            $this->normalUserClient->inviteUserToOrganization($organizationId, ['email' => $inviteeEmail]);
            $this->fail('Invite someone should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $invitations = $this->client->listOrganizationInvitations($organizationId);
        $this->assertCount(0, $invitations);

        $member = $this->findOrganizationMember($organizationId, $inviteeEmail);
        $this->assertNull($member);
    }

    /**
     * @dataProvider autoJoinProvider
     * @param bool $allowAutoJoin
     */
    public function testOrganizationAdminCanInviteRegardlessOfAllowAutoJoin(bool $allowAutoJoin): void
    {
        $inviteeEmail = 'devel-tests@keboola.com';
        $organizationId = $this->organization['id'];

        $this->client->addUserToOrganization($organizationId, ['email' => $this->normalUser['email']]);

        $this->normalUserClient->updateOrganization($organizationId, [
            'allowAutoJoin' => $allowAutoJoin,
        ]);

        $invitations = $this->normalUserClient->listOrganizationInvitations($organizationId);
        $this->assertCount(0, $invitations);

        $member = $this->findOrganizationMember($organizationId, $inviteeEmail);
        $this->assertNull($member);

        $invitation = $this->normalUserClient->inviteUserToOrganization($organizationId, ['email' => $inviteeEmail]);

        $invitee = $this->client->getUser($inviteeEmail);

        $this->assertEquals($invitee['id'], $invitation['user']['id']);
        $this->assertEquals($invitee['email'], $invitation['user']['email']);
        $this->assertEquals($invitee['name'], $invitation['user']['name']);

        $this->assertEquals($this->normalUser['id'], $invitation['creator']['id']);
        $this->assertEquals($this->normalUser['email'], $invitation['creator']['email']);
        $this->assertEquals($this->normalUser['name'], $invitation['creator']['name']);

        $invitations = $this->normalUserClient->listOrganizationInvitations($organizationId);
        $this->assertCount(1, $invitations);

        $this->assertEquals($invitation, reset($invitations));

        $this->assertEquals($invitation, $this->normalUserClient->getOrganizationInvitation($organizationId, $invitation['id']));

        $this->normalUserClient->cancelOrganizationInvitation($organizationId, $invitation['id']);

        $invitations = $this->normalUserClient->listOrganizationInvitations($organizationId);
        $this->assertCount(0, $invitations);

        $member = $this->findOrganizationMember($organizationId, $inviteeEmail);
        $this->assertNull($member);
    }

    public function testAdminAcceptsInvitation(): void
    {
        $organizationId = $this->organization['id'];

        $this->client->addUserToOrganization($organizationId, ['email' => $this->superAdmin['email']]);

        $invitations = $this->client->listOrganizationInvitations($organizationId);
        $this->assertCount(0, $invitations);

        $this->client->inviteUserToOrganization($organizationId, ['email' => $this->normalUser['email']]);

        $invitations = $this->normalUserClient->listMyOrganizationInvitations();
        $this->assertCount(1, $invitations);

        $invitation = reset($invitations);

        $this->assertEquals($organizationId, $invitation['organization']['id']);
        $this->assertEquals($this->organization['name'], $invitation['organization']['name']);

        $this->assertEquals($this->superAdmin['id'], $invitation['creator']['id']);
        $this->assertEquals($this->superAdmin['email'], $invitation['creator']['email']);
        $this->assertEquals($this->superAdmin['name'], $invitation['creator']['name']);

        $this->assertEquals($invitation, $this->normalUserClient->getMyOrganizationInvitation($invitation['id']));

        $this->normalUserClient->acceptMyOrganizationInvitation($invitation['id']);

        $invitations = $this->normalUserClient->listMyOrganizationInvitations();
        $this->assertCount(0, $invitations);

        $member = $this->findOrganizationMember($organizationId, $this->normalUser['email']);
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
        $organizationId = $this->organization['id'];

        $this->client->addUserToOrganization($organizationId, ['email' => $this->superAdmin['email']]);

        $invitations = $this->client->listOrganizationInvitations($organizationId);
        $this->assertCount(0, $invitations);

        $this->client->inviteUserToOrganization($organizationId, ['email' => $this->normalUser['email']]);

        $invitations = $this->normalUserClient->listMyOrganizationInvitations();
        $this->assertCount(1, $invitations);

        $invitation = reset($invitations);

        $this->assertEquals($organizationId, $invitation['organization']['id']);
        $this->assertEquals($this->organization['name'], $invitation['organization']['name']);

        $this->assertEquals($this->superAdmin['id'], $invitation['creator']['id']);
        $this->assertEquals($this->superAdmin['email'], $invitation['creator']['email']);
        $this->assertEquals($this->superAdmin['name'], $invitation['creator']['name']);

        $this->normalUserClient->declineMyOrganizationInvitation($invitation['id']);

        $invitations = $this->normalUserClient->listMyOrganizationInvitations();
        $this->assertCount(0, $invitations);

        $member = $this->findOrganizationMember($organizationId, $this->normalUser['email']);
        $this->assertNull($member);
    }

    public function testCannotInviteAlreadyInvitedUser(): void
    {
        $organizationId = $this->organization['id'];

        $this->client->addUserToOrganization($organizationId, ['email' => $this->normalUser['email']]);

        $this->normalUserClient->inviteUserToOrganization($organizationId, ['email' => $this->superAdmin['email']]);

        $invitations = $this->normalUserClient->listOrganizationInvitations($organizationId);
        $this->assertCount(1, $invitations);

        // send invitation twice
        try {
            $this->normalUserClient->inviteUserToOrganization($organizationId, ['email' => $this->superAdmin['email']]);
            $this->fail('Invite user to organization twice should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertStringContainsString('already', $e->getMessage());
            $this->assertStringContainsString('invited', $e->getMessage());
        }

        $invitations = $this->normalUserClient->listOrganizationInvitations($organizationId);
        $this->assertCount(1, $invitations);
    }

    public function testCannotInviteExistingMember(): void
    {
        $inviteeEmail = 'devel-tests@keboola.com';
        $organizationId = $this->organization['id'];

        $this->client->addUserToOrganization($organizationId, ['email' => $this->normalUser['email']]);

        $this->normalUserClient->addUserToOrganization($organizationId, ['email' => $inviteeEmail]);

        try {
            $this->normalUserClient->inviteUserToOrganization($organizationId, ['email' => $inviteeEmail]);
            $this->fail('Invite existing member to organization should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertStringContainsString('already', $e->getMessage());
            $this->assertStringContainsString('member', $e->getMessage());
        }

        $invitations = $this->normalUserClient->listOrganizationInvitations($organizationId);
        $this->assertCount(0, $invitations);
    }

    public function testRandomAdminCannotManageInvitationsInOrganization(): void
    {
        $organizationId = $this->organization['id'];

        $this->client->addUserToOrganization($organizationId, ['email' => $this->normalUser['email']]);

        $invitations = $this->normalUserClient->listOrganizationInvitations($organizationId);
        $this->assertCount(0, $invitations);

        $invitation = $this->normalUserClient->inviteUserToOrganization($organizationId, ['email' => $this->superAdmin['email']]);

        $invitations = $this->client->listOrganizationInvitations($organizationId);
        $this->assertCount(1, $invitations);

        $this->assertEquals($invitation, reset($invitations));

        $this->normalUserClient->removeUserFromOrganization($organizationId, $this->normalUser['id']);

        try {
            $this->normalUserClient->cancelOrganizationInvitation($organizationId, $invitation['id']);
            $this->fail('Cancel invitations should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $invitations = $this->client->listOrganizationInvitations($organizationId);
        $this->assertCount(1, $invitations);
    }

    public function testDeletingOrganizationRemovesInvitations(): void
    {
        $organizationId = $this->organization['id'];

        $this->client->addUserToOrganization($organizationId, ['email' => $this->normalUser['email']]);

        $this->normalUserClient->inviteUserToOrganization($organizationId, ['email' => $this->superAdmin['email']]);

        $invitations = $this->client->listMyOrganizationInvitations();
        $this->assertCount(1, $invitations);

        $this->client->deleteOrganization($organizationId);

        $invitations = $this->client->listMyOrganizationInvitations();
        $this->assertCount(0, $invitations);
    }

    public function testAddingAdminToOrganizationDeletesCorrespondingInvitation(): void
    {
        $inviteeEmail = $this->normalUser['email'];
        $secondInviteeEmail = 'devel-tests@keboola.com';
        $organizationId = $this->organization['id'];

        $this->client->joinOrganization($organizationId);

        $invitations = $this->client->listOrganizationInvitations($organizationId);
        $this->assertCount(0, $invitations);

        $this->client->inviteUserToOrganization($organizationId, ['email' => $inviteeEmail]);
        $this->client->inviteUserToOrganization($organizationId, ['email' => $secondInviteeEmail]);

        $invitations = $this->client->listOrganizationInvitations($organizationId);
        $this->assertCount(2, $invitations);

        $this->client->addUserToOrganization($organizationId, ['email' => $inviteeEmail]);

        $member = $this->findOrganizationMember($organizationId, $inviteeEmail);
        $this->assertNotNull($member);

        $invitations = $this->client->listOrganizationInvitations($organizationId);
        $this->assertCount(1, $invitations);

        $invitation = reset($invitations);

        $this->assertEquals($secondInviteeEmail, $invitation['user']['email']);

        $this->assertEquals($this->superAdmin['id'], $invitation['creator']['id']);
        $this->assertEquals($this->superAdmin['email'], $invitation['creator']['email']);
        $this->assertEquals($this->superAdmin['name'], $invitation['creator']['name']);
    }
}
