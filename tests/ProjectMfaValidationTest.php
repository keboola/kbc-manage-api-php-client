<?php
namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

class ProjectMfaValidationTest extends ClientMfaTestCase
{
    private $organization;

    /**
     * Test setup
     * - Create empty organization
     * - Add dummy user to maintainer. Remove all other members
     * - Add user having MFA enabled to organization. Remove all other members
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

        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUserWithMfa['email']]);
        $this->client->removeUserFromOrganization($this->organization['id'], $this->superAdmin['id']);
    }

    public function testAdminWithoutMfaCannotBecameMember()
    {
        $projectId = $this->createProjectWithAdminHavingMfaEnabled($this->organization['id']);

        $this->normalUserWithMfaClient->updateOrganization(
            $this->organization['id'],
            [
                'mfaRequired' => 1,
            ]
        );

        try {
            $this->normalUserWithMfaClient->addUserToProject($projectId, ['email' => $this->normalUser['email']]);
            $this->fail('Adding admins without MFA to project should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertContains('This project requires users to have multi-factor authentication enabled', $e->getMessage());
        }

        $member = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNull($member);
    }

    public function testLockAccessForOrganizationAdminIfMfaWasForced()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $projectId = $this->createProjectWithAdminHavingMfaEnabled($this->organization['id']);

        $this->normalUserWithMfaClient->enableOrganizationMfa($this->organization['id']);

        $organization = $this->normalUserWithMfaClient->getOrganization($this->organization['id']);
        $this->assertTrue($organization['mfaRequired']);

        try {
            $this->normalUserClient->getProject($projectId);
            $this->fail('Admin having MFA disabled should not have access to the project');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->normalUserClient->updateProject($projectId, []);
            $this->fail('Admin having MFA disabled should not have access to the project');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->normalUserClient->deleteProject($projectId);
            $this->fail('Admin having MFA disabled should not have access to the project');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->normalUserClient->listProjectJoinRequests($projectId);
            $this->fail('Admin having MFA disabled should not have access to the project');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->normalUserClient->listProjectInvitations($projectId);
            $this->fail('Admin having MFA disabled should not have access to the project');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->normalUserClient->listProjectUsers($projectId);
            $this->fail('Admin having MFA disabled should not have access to the project');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->normalUserClient->createProjectStorageToken($projectId, []);
            $this->fail('Admin having MFA disabled should not have access to the project');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }
    }

    public function testLockAccessForMaintainerAdminsIfMfaWasForced()
    {
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $projectId = $this->createProjectWithAdminHavingMfaEnabled($this->organization['id']);

        $this->normalUserWithMfaClient->enableOrganizationMfa($this->organization['id']);

        $organization = $this->normalUserWithMfaClient->getOrganization($this->organization['id']);
        $this->assertTrue($organization['mfaRequired']);

        try {
            $this->normalUserClient->getProject($projectId);
            $this->fail('Admin having MFA disabled should not have access to the project');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->normalUserClient->updateProject($projectId, []);
            $this->fail('Admin having MFA disabled should not have access to the project');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->normalUserClient->deleteProject($projectId);
            $this->fail('Admin having MFA disabled should not have access to the project');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->normalUserClient->listProjectJoinRequests($projectId);
            $this->fail('Admin having MFA disabled should not have access to the project');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->normalUserClient->listProjectInvitations($projectId);
            $this->fail('Admin having MFA disabled should not have access to the project');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->normalUserClient->listProjectUsers($projectId);
            $this->fail('Admin having MFA disabled should not have access to the project');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->normalUserClient->createProjectStorageToken($projectId, []);
            $this->fail('Admin having MFA disabled should not have access to the project');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }
    }

    public function testLockAccessForSuperAdminIfMfaWasForced()
    {
        $projectId = $this->createProjectWithAdminHavingMfaEnabled($this->organization['id']);

        $this->normalUserWithMfaClient->enableOrganizationMfa($this->organization['id']);

        $organization = $this->normalUserWithMfaClient->getOrganization($this->organization['id']);
        $this->assertTrue($organization['mfaRequired']);

        try {
            $this->client->getProject($projectId);
            $this->fail('Admin having MFA disabled should not have access to the project');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->client->updateProject($projectId, []);
            $this->fail('Admin having MFA disabled should not have access to the project');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->client->listProjectJoinRequests($projectId);
            $this->fail('Admin having MFA disabled should not have access to the project');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->client->listProjectInvitations($projectId);
            $this->fail('Admin having MFA disabled should not have access to the project');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->client->listProjectUsers($projectId);
            $this->fail('Admin having MFA disabled should not have access to the project');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->client->createProjectStorageToken($projectId, []);
            $this->fail('Admin having MFA disabled should not have access to the project');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }
    }

    public function testLockAccessForAdminIfMfaWasForced()
    {
        $projectId = $this->createProjectWithAdminHavingMfaEnabled($this->organization['id']);

        $this->normalUserWithMfaClient->addUserToProject($projectId, ['email' => $this->normalUser['email']]);

        $this->normalUserWithMfaClient->enableOrganizationMfa($this->organization['id']);

        $organization = $this->normalUserWithMfaClient->getOrganization($this->organization['id']);
        $this->assertTrue($organization['mfaRequired']);

        try {
            $this->normalUserClient->getProject($projectId);
            $this->fail('Admin having MFA disabled should not have access to the project');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->normalUserClient->updateProject($projectId, []);
            $this->fail('Admin having MFA disabled should not have access to the project');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->normalUserClient->deleteProject($projectId);
            $this->fail('Admin having MFA disabled should not have access to the project');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->normalUserClient->listProjectJoinRequests($projectId);
            $this->fail('Admin having MFA disabled should not have access to the project');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->normalUserClient->listProjectInvitations($projectId);
            $this->fail('Admin having MFA disabled should not have access to the project');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->normalUserClient->listProjectUsers($projectId);
            $this->fail('Admin having MFA disabled should not have access to the project');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }

        try {
            $this->normalUserClient->createProjectStorageToken($projectId, []);
            $this->fail('Admin having MFA disabled should not have access to the project');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }
    }
}
