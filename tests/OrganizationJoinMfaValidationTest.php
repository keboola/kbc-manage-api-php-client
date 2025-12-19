<?php
namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

class OrganizationJoinMfaValidationTest extends ClientMfaTestCase
{
    private $organization;

    /**
     * Test setup
     * - Create empty organization
     * - Add dummy user to organization and maintainer. Remove all other members
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => self::DUMMY_USER_EMAIL]);

        foreach ($this->client->listMaintainerMembers($this->testMaintainerId) as $member) {
            if ($member['email'] !== self::DUMMY_USER_EMAIL) {
                $this->client->removeUserFromMaintainer($this->testMaintainerId, $member['id']);
            }
        }

        $this->organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $this->client->addUserToOrganization($this->organization['id'], ['email' => self::DUMMY_USER_EMAIL]);
        $this->client->removeUserFromOrganization($this->organization['id'], $this->superAdmin['id']);
    }

    public function testSuperAdminWithoutMfaCannotJoinOrganization(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUserWithMfa['email']]);

        $member = $this->findOrganizationMember($this->organization['id'], self::DUMMY_USER_EMAIL);
        $this->assertIsArray($member);
        $this->client->removeUserFromOrganization($this->organization['id'], $member['id']);

        $this->normalUserWithMfaClient->updateOrganization($this->organization['id'], ['mfaRequired' => 1]);

        try {
            $this->client->joinOrganization($this->organization['id']);
            $this->fail('Organization join should be restricted for admins without MFA');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertStringContainsString('This organization requires users to have multi-factor authentication enabled', $e->getMessage());
        }

        $member = $this->findOrganizationMember($this->organization['id'], $this->superAdmin['email']);
        $this->assertNull($member);
    }

    public function testMaintainerAdminWithoutMfaCannotJoinOrganization(): void
    {
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUserWithMfa['email']]);

        $member = $this->findOrganizationMember($this->organization['id'], self::DUMMY_USER_EMAIL);
        $this->assertIsArray($member);
        $this->client->removeUserFromOrganization($this->organization['id'], $member['id']);

        $this->normalUserWithMfaClient->updateOrganization($this->organization['id'], ['mfaRequired' => 1]);

        try {
            $this->normalUserClient->joinOrganization($this->organization['id']);
            $this->fail('Organization join should be restricted for admins without MFA');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertStringContainsString('This organization requires users to have multi-factor authentication enabled', $e->getMessage());
        }

        $member = $this->findOrganizationMember($this->organization['id'], $this->normalUser['email']);
        $this->assertNull($member);
    }
}
