<?php
namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Client;
use Keboola\ManageApi\ClientException;

class OrganizationMfaValidationTest extends ClientMfaTestCase
{
    private $organization;

    /**
     * Test setup
     * - Create empty organization
     * - Add dummy user to organization and maintainer. Remove all other members
     */
    public function setUp()
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

        $this->createProjectWithAdminHavingMfaEnabled($this->organization['id']);

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

        $project = $this->createProjectWithAdminHavingMfaEnabled($this->organization['id']);
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
            $this->assertContains('This organization requires users to have multi-factor authentication enabled', $e->getMessage());
        }
    }

    public function testOrganizationAdminCanForceEnableMfaForOrganization()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUserWithMfa['email']]);

        $organization = $this->normalUserWithMfaClient->getOrganization($this->organization['id']);
        $this->assertFalse($organization['mfaRequired']);

        $this->normalUserWithMfaClient->enableOrganizationMfa($this->organization['id']);

        $organization = $this->normalUserWithMfaClient->getOrganization($this->organization['id']);
        $this->assertTrue($organization['mfaRequired']);
    }

    public function testOrganizationAdminWithoutMfaCannotForceEnableMfaForOrganization()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $organization = $this->client->getOrganization($this->organization['id']);
        $this->assertFalse($organization['mfaRequired']);

        try {
            $this->normalUserClient->enableOrganizationMfa($this->organization['id']);
            $this->fail('Enabling MFA validation for organization should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertEquals('You must setup Multi-Factor Authentication on your account first', $e->getMessage());
        }

        $organization = $this->client->getOrganization($this->organization['id']);
        $this->assertFalse($organization['mfaRequired']);
    }

    public function testMaintainerAdminCannotForceEnableMfaForOrganization()
    {
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUserWithMfa['email']]);

        $organization = $this->client->getOrganization($this->organization['id']);
        $this->assertFalse($organization['mfaRequired']);

        try {
            $this->normalUserWithMfaClient->enableOrganizationMfa($this->organization['id']);
            $this->fail('Enabling MFA validation for organization should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertEquals('manage.updateOrganizationPermissionDenied', $e->getStringCode());
        }

        $organization = $this->client->getOrganization($this->organization['id']);
        $this->assertFalse($organization['mfaRequired']);
    }

    public function testAdminCannotForceEnableMfaForOrganization()
    {
        $organization = $this->client->getOrganization($this->organization['id']);
        $this->assertFalse($organization['mfaRequired']);

        try {
            $this->normalUserWithMfaClient->enableOrganizationMfa($this->organization['id']);
            $this->fail('Enabling MFA validation for organization should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $organization = $this->client->getOrganization($this->organization['id']);
        $this->assertFalse($organization['mfaRequired']);
    }

    public function testSuperAdminCannotForceEnableMfaForOrganization()
    {
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUserWithMfa['email']]);

        $organization = $this->client->getOrganization($this->organization['id']);
        $this->assertFalse($organization['mfaRequired']);

        try {
            $this->client->enableOrganizationMfa($this->organization['id']);
            $this->fail('Enabling MFA validation for organization should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertEquals('manage.updateOrganizationPermissionDenied', $e->getStringCode());
        }

        $organization = $this->client->getOrganization($this->organization['id']);
        $this->assertFalse($organization['mfaRequired']);
    }

    public function testLockAccessForOrganizationAdminIfMfaWasForced()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUserWithMfa['email']]);
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $this->normalUserWithMfaClient->enableOrganizationMfa($this->organization['id']);

        $this->assertAccessLocked($this->normalUserClient);
    }

    public function testLockAccessForMaintainerAdminsIfMfaWasForced()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUserWithMfa['email']]);
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $this->normalUserWithMfaClient->enableOrganizationMfa($this->organization['id']);

        $this->assertAccessLocked($this->normalUserClient);
    }

    public function testLockAccessForSuperAdminIfMfaWasForced()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUserWithMfa['email']]);

        $this->normalUserWithMfaClient->enableOrganizationMfa($this->organization['id']);

        $this->assertAccessLocked($this->client);
    }

    public function testSuperAdminCanDeleteOrganizationIfMfaWasForced()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUserWithMfa['email']]);

        $this->normalUserWithMfaClient->enableOrganizationMfa($this->organization['id']);

        $this->client->deleteOrganization($this->organization['id']);

        try {
            $this->normalUserWithMfaClient->getOrganization($this->organization['id']);
            $this->fail('Organization should be deleted');
        } catch (ClientException $e) {
            $this->assertEquals(404, $e->getCode());
        }
    }

    public function testSuperAdminCanListOrganizationProjectsIfMfaWasForced()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUserWithMfa['email']]);

        $this->normalUserWithMfaClient->enableOrganizationMfa($this->organization['id']);

        $this->createProjectWithAdminHavingMfaEnabled($this->organization['id']);

        $projects = $this->client->listOrganizationProjects($this->organization['id']);
        $this->assertEquals(1, count($projects));
    }

    public function testOrganizationAdminWithoutMfaCannotListProjectsUser()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUserWithMfa['email']]);
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $project = $this->createProjectWithAdminHavingMfaEnabled($this->organization['id']);
        $this->normalUserWithMfaClient->addUserToProject($project['id'], ['email' => $this->normalUser['email']]);

        $this->normalUserWithMfaClient->enableOrganizationMfa($this->organization['id']);

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('This organization requires users to have multi-factor authentication enabled');
        $this->expectExceptionCode(400);

        $this->normalUserClient->listOrganizationProjectsUsers($this->organization['id']);
    }

    private function assertAccessLocked(Client $userClient): void
    {
        try {
            $userClient->getOrganization($this->organization['id']);
            $this->fail('Admin having MFA disabled should not have access to the organization');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }

        try {
            $userClient->createProject($this->organization['id'], ['name' => 'Test']);
            $this->fail('Admin having MFA disabled should not have access to the organization');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }

        if ($userClient->verifyToken()['user']['isSuperAdmin'] !== true) {
            try {
                $userClient->deleteOrganization($this->organization['id']);
                $this->fail('Admin having MFA disabled should not have access to the organization');
            } catch (ClientException $e) {
                $this->assertEquals('manage.mfaRequired', $e->getStringCode());
            }

            try {
                $userClient->listOrganizationProjects($this->organization['id']);
                $this->fail('Admin having MFA disabled should not have access to the organization');
            } catch (ClientException $e) {
                $this->assertEquals('manage.mfaRequired', $e->getStringCode());
            }
        }

        try {
            $userClient->updateOrganization($this->organization['id'], []);
            $this->fail('Admin having MFA disabled should not have access to the organization');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }

        try {
            $userClient->listOrganizationUsers($this->organization['id']);
            $this->fail('Admin having MFA disabled should not have access to the organization');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }

        try {
            $userClient->listOrganizationInvitations($this->organization['id']);
            $this->fail('Admin having MFA disabled should not have access to the organization');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }
    }
}
