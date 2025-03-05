<?php
namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Client;
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
            $this->assertStringContainsString('This project requires users to have multi-factor authentication enabled', $e->getMessage());
        }

        $member = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNull($member);
    }

    public function testLockAccessForOrganizationAdminIfMfaWasForced()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $projectId = $this->createProjectWithAdminHavingMfaEnabled($this->organization['id']);

        $this->normalUserWithMfaClient->enableOrganizationMfa($this->organization['id']);

        $this->assertAccessLocked($this->normalUserClient, $projectId);
    }

    public function testLockAccessForMaintainerAdminsIfMfaWasForced()
    {
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $projectId = $this->createProjectWithAdminHavingMfaEnabled($this->organization['id']);

        $this->normalUserWithMfaClient->enableOrganizationMfa($this->organization['id']);

        $this->assertAccessLocked($this->normalUserClient, $projectId);
    }

    public function testLockAccessForSuperAdminIfMfaWasForced()
    {
        $projectId = $this->createProjectWithAdminHavingMfaEnabled($this->organization['id']);

        $this->normalUserWithMfaClient->enableOrganizationMfa($this->organization['id']);

        $this->assertAccessLocked($this->client, $projectId);
    }

    public function testLockAccessForAdminIfMfaWasForced()
    {
        $projectId = $this->createProjectWithAdminHavingMfaEnabled($this->organization['id']);

        $this->normalUserWithMfaClient->addUserToProject($projectId, ['email' => $this->normalUser['email']]);

        $this->normalUserWithMfaClient->enableOrganizationMfa($this->organization['id']);

        $this->assertAccessLocked($this->normalUserClient, $projectId);
    }

    public function testSuperAdminCanDeleteOrganizationIfMfaWasForced()
    {
        $projectId = $this->createProjectWithAdminHavingMfaEnabled($this->organization['id']);

        $this->normalUserWithMfaClient->addUserToProject($projectId, ['email' => $this->normalUser['email']]);

        $this->normalUserWithMfaClient->enableOrganizationMfa($this->organization['id']);

        $this->client->deleteProject($projectId);

        try {
            $this->normalUserWithMfaClient->getProject($projectId);
            $this->fail('Project should be deleted');
        } catch (ClientException $e) {
            $this->assertEquals(404, $e->getCode());
        }
    }

    private function assertAccessLocked(Client $userClient, int $projectId): void
    {
        try {
            $userClient->getProject($projectId);
            $this->fail('Admin having MFA disabled should not have access to the project');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }

        try {
            $userClient->updateProject($projectId, []);
            $this->fail('Admin having MFA disabled should not have access to the project');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }

        $tokenInfo = $userClient->verifyToken();
        $this->assertArrayHasKey('user', $tokenInfo);
        if ($tokenInfo['user']['isSuperAdmin'] !== true) {
            try {
                $userClient->deleteProject($projectId);
                $this->fail('Admin having MFA disabled should not have access to the project');
            } catch (ClientException $e) {
                $this->assertEquals('manage.mfaRequired', $e->getStringCode());
            }
        }

        try {
            $userClient->listProjectJoinRequests($projectId);
            $this->fail('Admin having MFA disabled should not have access to the project');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }

        try {
            $userClient->listProjectInvitations($projectId);
            $this->fail('Admin having MFA disabled should not have access to the project');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }

        try {
            $userClient->listProjectUsers($projectId);
            $this->fail('Admin having MFA disabled should not have access to the project');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }

        try {
            $userClient->createProjectStorageToken($projectId, []);
            $this->fail('Admin having MFA disabled should not have access to the project');
        } catch (ClientException $e) {
            $this->assertEquals('manage.mfaRequired', $e->getStringCode());
        }
    }
}
