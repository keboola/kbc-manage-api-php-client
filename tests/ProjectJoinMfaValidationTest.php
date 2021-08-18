<?php
namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

class ProjectJoinMfaValidationTest extends ClientMfaTestCase
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

    public function testSuperAdminWithoutMfaCannotJoinProject()
    {
        $projectId = $this->createProjectWithAdminHavingMfaEnabled($this->organization['id']);

        $this->normalUserWithMfaClient->updateOrganization(
            $this->organization['id'],
            [
                'mfaRequired' => 1,
            ]
        );

        try {
            $this->client->joinProject($projectId);
            $this->fail('Joining a project should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertContains('This project requires users to have multi-factor authentication enabled', $e->getMessage());
        }

        $projectUser = $this->findProjectUser($projectId, $this->superAdmin['email']);
        $this->assertNull($projectUser);
    }

    public function testOrganizationAdminWithMfaCanJoinProject()
    {
        $projectId = $this->createProjectWithAdminHavingMfaEnabled($this->organization['id']);

        $this->normalUserWithMfaClient->updateOrganization(
            $this->organization['id'],
            [
                'mfaRequired' => 1,
            ]
        );

        $this->normalUserWithMfaClient->removeUserFromProject($projectId, $this->normalUserWithMfa['id']);

        $projectUser = $this->findProjectUser($projectId, $this->normalUserWithMfa['email']);
        $this->assertNull($projectUser);

        $this->normalUserWithMfaClient->joinProject($projectId);

        $projectUser = $this->findProjectUser($projectId, $this->normalUserWithMfa['email']);
        $this->assertNotNull($projectUser);
    }
}
