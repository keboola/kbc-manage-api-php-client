<?php
namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Client;
use Keboola\ManageApi\ClientException;

class OrganizationMfaValidationTest extends ClientTestCase
{
    private const DUMMY_USER_EMAIL = 'spam+spam@keboola.com';

    /** @var Client */
    private $normalUserWithMfaClient;

    private $normalUserWithMfa;

    private $organization;

    /**
     * Test setup
     * - Create empty organization
     * - Add dummy user to organization and maintainer. Remove all other members
     */
    public function setUp()
    {
        parent::setUp();

        $this->normalUserWithMfaClient = new Client([
            'token' => getenv('KBC_TEST_ADMIN_WITH_MFA_TOKEN'),
            'url' => getenv('KBC_MANAGE_API_URL'),
        ]);

        $this->normalUserWithMfa = $this->normalUserWithMfaClient->verifyToken()['user'];

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

    public function testSuperAdminCannotChangeMfaAttribute()
    {
        $this->assertSame(false, $this->organization['mfaRequired']);

        try {
            $this->client->updateOrganization($this->organization['id'], ['mfaRequired' => 1]);
            $this->fail('Change of multi-factor authentication attribute should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertContains('Only organization members can change the \'mfaRequired\' parameter', $e->getMessage());
        }

        $this->assertSame(false, $this->organization['mfaRequired']);
    }

    public function testMaintainerAdminCannotChangeMfaAttribute()
    {
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $this->assertSame(false, $this->organization['mfaRequired']);

        try {
            $this->normalUserClient->updateOrganization($this->organization['id'], ['mfaRequired' => 1]);
            $this->fail('Change of multi-factor authentication attribute should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertContains('Only organization members can change the \'mfaRequired\' parameter', $e->getMessage());
        }

        $this->assertSame(false, $this->organization['mfaRequired']);
    }

    public function testOrganizationAdminCanChangeMfaAttribute()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUserWithMfa['email']]);

        $member = $this->findOrganizationMember($this->organization['id'], self::DUMMY_USER_EMAIL);
        $this->client->removeUserFromOrganization($this->organization['id'], $member['id']);

        $this->assertSame(false, $this->organization['mfaRequired']);

        $this->normalUserWithMfaClient->createProject($this->organization['id'], ['name' => 'Test']);

        $organization = $this->normalUserWithMfaClient->updateOrganization($this->organization['id'], ['mfaRequired' => 1]);
        $this->assertSame(true, $organization['mfaRequired']);
    }

    public function testAllOrganizationMembersHaveMfaEnabledValidation()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $this->assertSame(false, $this->organization['mfaRequired']);

        try {
            $this->normalUserClient->updateOrganization($this->organization['id'], ['mfaRequired' => 1]);
            $this->fail('Change of multi-factor authentication attribute should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertContains('Not all organization and project members have Multi-factor Authentication enabled', $e->getMessage());
        }

        $this->assertSame(false, $this->organization['mfaRequired']);
    }

    public function testAllProjectsMembersHaveMfaEnabledValidation()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUserWithMfa['email']]);

        $member = $this->findOrganizationMember($this->organization['id'], self::DUMMY_USER_EMAIL);
        $this->client->removeUserFromOrganization($this->organization['id'], $member['id']);

        $this->assertSame(false, $this->organization['mfaRequired']);

        $project = $this->normalUserWithMfaClient->createProject($this->organization['id'], ['name' => 'Test']);
        $this->normalUserWithMfaClient->addUserToProject($project['id'], ['email' => self::DUMMY_USER_EMAIL]);

        try {
            $this->normalUserWithMfaClient->updateOrganization($this->organization['id'], ['mfaRequired' => 1]);
            $this->fail('Change of multi-factor authentication attribute should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertContains('Not all organization and project members have Multi-factor Authentication enabled', $e->getMessage());
        }

        $this->assertSame(false, $this->organization['mfaRequired']);
    }

    public function testAdminWithoutMfaCannotBecameMember()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUserWithMfa['email']]);

        $member = $this->findOrganizationMember($this->organization['id'], self::DUMMY_USER_EMAIL);
        $this->client->removeUserFromOrganization($this->organization['id'], $member['id']);

        $this->normalUserWithMfaClient->updateOrganization($this->organization['id'], ['mfaRequired' => 1]);

        try {
            $this->normalUserWithMfaClient->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
            $this->fail('Adding admins without MFA to organization should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertContains('Organization requires users to have multi-factor authentication enabled', $e->getMessage());
        }
    }

    public function testLockAccessForOrganizationAdminIfMfaWasForced()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUserWithMfa['email']]);
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $this->normalUserWithMfaClient->enableOrganizationMfa($this->organization['id']);

        $organization = $this->normalUserWithMfaClient->getOrganization($this->organization['id']);
        $this->assertTrue($organization['mfaRequired']);

        try {
            $this->normalUserClient->getOrganization($this->organization['id']);
            $this->fail('Admin having MFA disabled should not have access to the organization');
        } catch (ClientException $e) {
            $this->assertEquals('storage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->normalUserClient->createProject($this->organization['id'], ['name' => 'Test']);
            $this->fail('Admin having MFA disabled should not have access to the organization');
        } catch (ClientException $e) {
            $this->assertEquals('storage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->normalUserClient->deleteOrganization($this->organization['id']);
            $this->fail('Admin having MFA disabled should not have access to the organization');
        } catch (ClientException $e) {
            $this->assertEquals('storage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->normalUserClient->updateOrganization($this->organization['id'], []);
            $this->fail('Admin having MFA disabled should not have access to the organization');
        } catch (ClientException $e) {
            $this->assertEquals('storage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->normalUserClient->listOrganizationUsers($this->organization['id']);
            $this->fail('Admin having MFA disabled should not have access to the organization');
        } catch (ClientException $e) {
            $this->assertEquals('storage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->normalUserClient->listOrganizationProjects($this->organization['id']);
            $this->fail('Admin having MFA disabled should not have access to the organization');
        } catch (ClientException $e) {
            $this->assertEquals('storage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->normalUserClient->listOrganizationInvitations($this->organization['id']);
            $this->fail('Admin having MFA disabled should not have access to the organization');
        } catch (ClientException $e) {
            $this->assertEquals('storage.mfaRequired', $e->getStringCode());
        }
    }

    public function testLockAccessForMaintainerAdminsIfMfaWasForced()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUserWithMfa['email']]);
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $this->normalUserWithMfaClient->enableOrganizationMfa($this->organization['id']);

        $organization = $this->normalUserWithMfaClient->getOrganization($this->organization['id']);
        $this->assertTrue($organization['mfaRequired']);

        try {
            $this->normalUserClient->getOrganization($this->organization['id']);
            $this->fail('Admin having MFA disabled should not have access to the organization');
        } catch (ClientException $e) {
            $this->assertEquals('storage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->normalUserClient->createProject($this->organization['id'], ['name' => 'Test']);
            $this->fail('Admin having MFA disabled should not have access to the organization');
        } catch (ClientException $e) {
            $this->assertEquals('storage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->normalUserClient->deleteOrganization($this->organization['id']);
            $this->fail('Admin having MFA disabled should not have access to the organization');
        } catch (ClientException $e) {
            $this->assertEquals('storage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->normalUserClient->updateOrganization($this->organization['id'], []);
            $this->fail('Admin having MFA disabled should not have access to the organization');
        } catch (ClientException $e) {
            $this->assertEquals('storage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->normalUserClient->listOrganizationUsers($this->organization['id']);
            $this->fail('Admin having MFA disabled should not have access to the organization');
        } catch (ClientException $e) {
            $this->assertEquals('storage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->normalUserClient->listOrganizationProjects($this->organization['id']);
            $this->fail('Admin having MFA disabled should not have access to the organization');
        } catch (ClientException $e) {
            $this->assertEquals('storage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->normalUserClient->listOrganizationInvitations($this->organization['id']);
            $this->fail('Admin having MFA disabled should not have access to the organization');
        } catch (ClientException $e) {
            $this->assertEquals('storage.mfaRequired', $e->getStringCode());
        }
    }

    public function testLockAccessForSuperAdminIfMfaWasForced()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUserWithMfa['email']]);

        $this->normalUserWithMfaClient->enableOrganizationMfa($this->organization['id']);

        try {
            $this->client->getOrganization($this->organization['id']);
            $this->fail('Admin having MFA disabled should not have access to the organization');
        } catch (ClientException $e) {
            $this->assertEquals('storage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->client->createProject($this->organization['id'], ['name' => 'Test']);
            $this->fail('Admin having MFA disabled should not have access to the organization');
        } catch (ClientException $e) {
            $this->assertEquals('storage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->client->updateOrganization($this->organization['id'], []);
            $this->fail('Admin having MFA disabled should not have access to the organization');
        } catch (ClientException $e) {
            $this->assertEquals('storage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->client->listOrganizationUsers($this->organization['id']);
            $this->fail('Admin having MFA disabled should not have access to the organization');
        } catch (ClientException $e) {
            $this->assertEquals('storage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->client->listOrganizationInvitations($this->organization['id']);
            $this->fail('Admin having MFA disabled should not have access to the organization');
        } catch (ClientException $e) {
            $this->assertEquals('storage.mfaRequired', $e->getStringCode());
        }
    }

    public function testSuperAdminCanDeleteOrganizationIfMfaWasForced()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUserWithMfa['email']]);

        $this->normalUserWithMfaClient->enableOrganizationMfa($this->organization['id']);

        $organization = $this->normalUserWithMfaClient->getOrganization($this->organization['id']);
        $this->assertTrue($organization['mfaRequired']);

        $organizationsListBeforeDelete = $this->client->listMaintainerOrganizations($this->testMaintainerId);

        $this->client->deleteOrganization($this->organization['id']);

        $organizationsListAfterDelete = $this->client->listMaintainerOrganizations($this->testMaintainerId);

        $this->assertCount(count($organizationsListBeforeDelete) - 1, $organizationsListAfterDelete);
    }

    public function testSuperAdminCanListOrganizationProjectsIfMfaWasForced()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUserWithMfa['email']]);

        $this->normalUserWithMfaClient->enableOrganizationMfa($this->organization['id']);

        $organization = $this->normalUserWithMfaClient->getOrganization($this->organization['id']);
        $this->assertTrue($organization['mfaRequired']);

        $this->normalUserWithMfaClient->createProject($this->organization['id'], ['name' => 'Test']);

        $projects = $this->client->listOrganizationProjects($this->organization['id']);
        $this->assertGreaterThan(0, count($projects));
    }
}
