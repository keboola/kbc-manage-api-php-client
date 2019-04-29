<?php
namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Client;
use Keboola\ManageApi\ClientException;

class ProjectJoinRequestsMfaValidationTest extends ClientTestCase
{
    private const DUMMY_USER_EMAIL = 'spam+spam@keboola.com';

    /** @var Client */
    private $normalUserWithMfaClient;

    private $normalUserWithMfa;

    private $organization;

    /**
     * Test setup
     * - Create empty organization
     * - Add dummy user to maintainer. Remove all other members
     * - Add user having MFA enabled to organization. Remove all other members
     * - Delete all project join requests of super admin and normal user
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

        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUserWithMfa['email']]);
        $this->client->removeUserFromOrganization($this->organization['id'], $this->superAdmin['id']);


        foreach ($this->normalUserClient->listMyProjectJoinRequests() as $joinRequest) {
            $this->normalUserClient->deleteMyProjectJoinRequest($joinRequest['id']);
        }

        foreach ($this->client->listMyProjectJoinRequests() as $joinRequest) {
            $this->client->deleteMyProjectJoinRequest($joinRequest['id']);
        }
    }

    public function testSuperAdminWithoutMfaCannotRequestAccess()
    {
        $projectId = $this->createProjectWithAdminHavingMfaEnabled();

        $this->normalUserWithMfaClient->updateOrganization(
            $this->organization['id'],
            [
                'mfaRequired' => 1,
                'allowAutoJoin' => 0,
            ]
        );

        $joinRequests = $this->normalUserWithMfaClient->listProjectJoinRequests($projectId);
        $this->assertCount(0, $joinRequests);

        try {
            $this->client->requestAccessToProject($projectId);
            $this->fail('Requesting access to a project should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertContains('Project requires users to have multi-factor authentication enabled', $e->getMessage());
        }

        $joinRequests = $this->normalUserWithMfaClient->listProjectJoinRequests($projectId);
        $this->assertCount(0, $joinRequests);
    }

    public function testJoinRequestOfUserWithoutMfaCannotBeApproved()
    {
        $projectId = $this->createProjectWithAdminHavingMfaEnabled();

        $this->normalUserWithMfaClient->updateOrganization(
            $this->organization['id'],
            [
                'allowAutoJoin' => 0,
            ]
        );

        $joinRequests = $this->client->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);

        $joinRequest = $this->client->requestAccessToProject($projectId);

        $joinRequests = $this->client->listMyProjectJoinRequests();
        $this->assertCount(1, $joinRequests);

        $this->assertEquals($joinRequest, reset($joinRequests));

        $this->normalUserWithMfaClient->updateOrganization(
            $this->organization['id'],
            [
                'mfaRequired' => 1,
            ]
        );

        try {
            $this->normalUserWithMfaClient->approveProjectJoinRequest($projectId, $joinRequest['id']);
            $this->fail('Approving a join request should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertContains('Project requires users to have multi-factor authentication enabled', $e->getMessage());
        }

        $joinRequests = $this->client->listMyProjectJoinRequests();
        $this->assertCount(1, $joinRequests);

        $projectUser = $this->findProjectUser($projectId, $this->superAdmin['email']);
        $this->assertNull($projectUser);
    }

    public function testJoinRequestReject()
    {
        $projectId = $this->createProjectWithAdminHavingMfaEnabled();

        $this->normalUserWithMfaClient->updateOrganization(
            $this->organization['id'],
            [
                'allowAutoJoin' => 0,
            ]
        );

        $joinRequests = $this->client->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);

        $joinRequest = $this->client->requestAccessToProject($projectId);

        $joinRequests = $this->client->listMyProjectJoinRequests();
        $this->assertCount(1, $joinRequests);

        $this->normalUserWithMfaClient->updateOrganization(
            $this->organization['id'],
            [
                'mfaRequired' => 1,
            ]
        );

        $this->normalUserWithMfaClient->rejectProjectJoinRequest($projectId, $joinRequest['id']);

        $joinRequests = $this->client->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);
    }


    private function createProjectWithAdminHavingMfaEnabled(): int
    {
        $project = $this->normalUserWithMfaClient->createProject($this->organization['id'], [
            'name' => 'My test',
        ]);

        return $project['id'];
    }
}
