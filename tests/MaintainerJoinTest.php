<?php
namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

class MaintainerJoinTest extends ClientTestCase
{
    private $maintainer;

    /**
     * Create empty maintainer without admins, remove admins from test maintainer
     */
    public function setUp()
    {
        parent::setUp();

        $this->maintainer = $this->client->createMaintainer([
            'name' => self::TESTS_MAINTAINER_PREFIX . ' - MaintainerJoinTest',
        ]);

        $this->client->addUserToMaintainer($this->maintainer['id'], ['email' => 'spam+spam@keboola.com']);
        $this->client->removeUserFromMaintainer($this->maintainer['id'], $this->superAdmin['id']);
    }

    public function testSuperAdminCanJoinMaintainer(): void
    {
        $maintainerId = $this->maintainer['id'];

        $member = $this->findMaintainerMember($maintainerId, $this->superAdmin['email']);
        $this->assertNull($member);

        $this->client->joinMaintainer($maintainerId);

        $member = $this->findMaintainerMember($maintainerId, $this->superAdmin['email']);
        $this->assertNotNull($member);

        $this->assertArrayHasKey('invitor', $member);
        $this->assertEmpty($member['invitor']);
    }

    public function testSuperAdminAdminJoiningMaintainerDeletesCorrespondingInvitation(): void
    {
        $maintainerId = $this->maintainer['id'];
        $secondInviteeEmail = 'spam@keboola.com';

        $this->client->addUserToMaintainer($maintainerId, ['email' => $this->normalUser['email']]);

        $invitations = $this->client->listMyMaintainerInvitations();
        $this->assertCount(0, $invitations);

        $this->normalUserClient->inviteUserToMaintainer($maintainerId, ['email' => $this->superAdmin['email']]);
        $this->normalUserClient->inviteUserToMaintainer($maintainerId, ['email' => $secondInviteeEmail]);

        $invitations = $this->client->listMyMaintainerInvitations();
        $this->assertCount(1, $invitations);

        $member = $this->findMaintainerMember($maintainerId, $this->superAdmin['email']);
        $this->assertNull($member);

        $this->client->joinMaintainer($maintainerId);

        $member = $this->findMaintainerMember($maintainerId, $this->superAdmin['email']);
        $this->assertNotNull($member);

        $invitations = $this->client->listMyMaintainerInvitations();
        $this->assertCount(0, $invitations);

        $invitations = $this->client->listMaintainerInvitations($maintainerId);
        $this->assertCount(1, $invitations);

        $invitation = reset($invitations);

        $this->assertEquals($secondInviteeEmail, $invitation['user']['email']);

        $this->assertEquals($this->normalUser['id'], $invitation['creator']['id']);
        $this->assertEquals($this->normalUser['email'], $invitation['creator']['email']);
        $this->assertEquals($this->normalUser['name'], $invitation['creator']['name']);
    }

    public function testRandomAdminCannotJoinMaintainer(): void
    {
        $maintainerId = $this->maintainer['id'];

        $member = $this->findMaintainerMember($maintainerId, $this->normalUser['email']);
        $this->assertNull($member);

        try {
            $this->normalUserClient->joinMaintainer($maintainerId);
            $this->fail('Maintainer join should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $member = $this->findMaintainerMember($maintainerId, $this->normalUser['email']);
        $this->assertNull($member);
    }

    public function testMaintainerMemberCannotJoinMaintainerAgain(): void
    {
        $maintainerId = $this->maintainer['id'];

        $this->client->addUserToMaintainer($maintainerId, ['email' => $this->normalUser['email']]);

        $member = $this->findMaintainerMember($maintainerId, $this->normalUser['email']);
        $this->assertNotNull($member);

        try {
            $this->normalUserClient->joinMaintainer($maintainerId);
            $this->fail('Maintainer join should produce error');
        } catch (ClientException $e) {
            $this->assertContains('already a member', $e->getMessage());
            $this->assertEquals(400, $e->getCode());
        }

        $member = $this->findMaintainerMember($maintainerId, $this->normalUser['email']);
        $this->assertNotNull($member);
    }
}
