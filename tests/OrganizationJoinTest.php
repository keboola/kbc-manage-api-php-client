<?php
namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

class OrganizationJoinTest extends ClientTestCase
{
    private $organization;

    /**
     * Create empty organization without admins, remove admins from test maintainer
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
    }

    public function testSuperAdminCanJoinOrganization(): void
    {
        $organizationId = $this->organization['id'];

        $member = $this->findOrganizationMember($organizationId, $this->superAdmin['email']);
        $this->assertNull($member);

        $this->client->joinOrganization($organizationId);

        $member = $this->findOrganizationMember($organizationId, $this->superAdmin['email']);
        $this->assertNotNull($member);

        $this->assertArrayHasKey('invitor', $member);
        $this->assertEmpty($member['invitor']);
    }

    public function testMaintainerAdminCanJoinOrganization(): void
    {
        $organizationId = $this->organization['id'];

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $member = $this->findOrganizationMember($organizationId, $this->normalUser['email']);
        $this->assertNull($member);

        $this->normalUserClient->joinOrganization($organizationId);

        $member = $this->findOrganizationMember($organizationId, $this->normalUser['email']);
        $this->assertNotNull($member);

        $this->assertArrayHasKey('invitor', $member);
        $this->assertEmpty($member['invitor']);
    }

    public function testSuperAdminCannotJoinOrganizationIfAllowAutoJoinIsDisabled(): void
    {
        $organizationId = $this->organization['id'];

        $this->client->addUserToOrganization($organizationId, ['email' => $this->normalUser['email']]);

        $this->normalUserClient->updateOrganization($organizationId, [
            'allowAutoJoin' => 0
        ]);

        $member = $this->findOrganizationMember($organizationId, $this->superAdmin['email']);
        $this->assertNull($member);

        try {
            $this->client->joinOrganization($organizationId);
            $this->fail('Organization join should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $member = $this->findOrganizationMember($organizationId, $this->superAdmin['email']);
        $this->assertNull($member);
    }

    public function testMaintainerAdminCannotJoinOrganizationIfAllowAutoJoinIsDisabled(): void
    {
        $organizationId = $this->organization['id'];

        $this->client->addUserToOrganization($organizationId, ['email' => $this->superAdmin['email']]);

        $this->client->updateOrganization($organizationId, [
            'allowAutoJoin' => 0
        ]);

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $member = $this->findOrganizationMember($organizationId, $this->normalUser['email']);
        $this->assertNull($member);

        try {
            $this->normalUserClient->joinOrganization($organizationId);
            $this->fail('Organization join should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $member = $this->findOrganizationMember($organizationId, $this->normalUser['email']);
        $this->assertNull($member);
    }

    public function testOrganizationAdminJoiningOrganizationDeletesCorrsepondingInvitation()
    {
        $organizationId = $this->organization['id'];
        $secondInviteeEmail = 'spam@keboola.com';

        $this->client->addUserToOrganization($organizationId, ['email' => $this->superAdmin['email']]);
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $invitations = $this->normalUserClient->listMyOrganizationInvitations();
        $this->assertCount(0, $invitations);

        $this->client->inviteUserToOrganization($organizationId, ['email' => $this->normalUser['email']]);
        $this->client->inviteUserToOrganization($organizationId, ['email' => $secondInviteeEmail]);

        $invitations = $this->normalUserClient->listMyOrganizationInvitations();
        $this->assertCount(1, $invitations);

        $member = $this->findOrganizationMember($organizationId, $this->normalUser['email']);
        $this->assertNull($member);

        $this->normalUserClient->joinOrganization($organizationId);

        $member = $this->findOrganizationMember($organizationId, $this->normalUser['email']);
        $this->assertNotNull($member);

        $invitations = $this->normalUserClient->listMyOrganizationInvitations();
        $this->assertCount(0, $invitations);

        $invitations = $this->normalUserClient->listOrganizationInvitations($organizationId);
        $this->assertCount(1, $invitations);

        $invitation = reset($invitations);

        $this->assertEquals($secondInviteeEmail, $invitation['user']['email']);

        $this->assertEquals($this->superAdmin['id'], $invitation['creator']['id']);
        $this->assertEquals($this->superAdmin['email'], $invitation['creator']['email']);
        $this->assertEquals($this->superAdmin['name'], $invitation['creator']['name']);

    }

    public function testRandomAdminCannotJoinOrganization(): void
    {
        $organizationId = $this->organization['id'];

        $this->client->addUserToOrganization($organizationId, ['email' => $this->superAdmin['email']]);

        $member = $this->findOrganizationMember($organizationId, $this->normalUser['email']);
        $this->assertNull($member);

        try {
            $this->normalUserClient->joinOrganization($organizationId);
            $this->fail('Organization join should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $member = $this->findOrganizationMember($organizationId, $this->normalUser['email']);
        $this->assertNull($member);

        // project without auto-join
        $this->client->updateOrganization($organizationId, [
            'allowAutoJoin' => 0
        ]);

        try {
            $this->normalUserClient->joinOrganization($organizationId);
            $this->fail('Organization join should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $member = $this->findOrganizationMember($organizationId, $this->normalUser['email']);
        $this->assertNull($member);
    }

    public function testOrganizationMemberCannotJoinOrganizationAgainRegardlessOfAllowAutoJoin(): void
    {
        $organizationId = $this->organization['id'];

        $this->client->addUserToOrganization($organizationId, ['email' => $this->normalUser['email']]);

        $member = $this->findOrganizationMember($organizationId, $this->normalUser['email']);
        $this->assertNotNull($member);

        try {
            $this->normalUserClient->joinOrganization($organizationId);
            $this->fail('Organization join should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
        }

        // project without auto-join
        $this->normalUserClient->updateOrganization($organizationId, [
            'allowAutoJoin' => 0
        ]);

        try {
            $this->normalUserClient->joinOrganization($organizationId);
            $this->fail('Organization join should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
        }
    }

    private function findOrganizationMember(int $organizationId, string $userEmail): ?array
    {
        $members = $this->client->listOrganizationUsers($organizationId);

        foreach ($members as $member) {
            if ($member['email'] === $userEmail) {
                return $member;
            }
        }

        return null;
    }
}
