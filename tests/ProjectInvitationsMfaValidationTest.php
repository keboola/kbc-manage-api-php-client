<?php
namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Client;
use Keboola\ManageApi\ClientException;

class ProjectInvitationsMfaValidationTest extends ClientTestCase
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
     * - Decline all project invitations for super admin and normal user
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


        foreach ($this->normalUserClient->listMyProjectInvitations() as $invitation) {
            $this->normalUserClient->declineMyProjectInvitation($invitation['id']);
        }

        foreach ($this->client->listMyProjectInvitations() as $invitation) {
            $this->client->declineMyProjectInvitation($invitation['id']);
        }
    }

    public function testInvitedAdminCannotAcceptInvitation()
    {
        $projectId = $this->createProjectWithAdminHavingMfaEnabled();

        $this->normalUserWithMfaClient->updateOrganization(
            $this->organization['id'],
            [
                'mfaRequired' => 1,
            ]
        );

        $invitation = $this->normalUserWithMfaClient->inviteUserToProject($projectId, ['email' => $this->normalUser['email']]);

        $invitations = $this->normalUserWithMfaClient->listProjectInvitations($projectId);
        $this->assertCount(1, $invitations);

        $this->assertEquals($invitation, reset($invitations));

        try {
            $this->normalUserClient->acceptMyProjectInvitation($invitation['id']);
            $this->fail('Accept invitation to project should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertContains('Project requires users to have multi-factor authentication enabled', $e->getMessage());
        }

        $invitations = $this->normalUserWithMfaClient->listProjectInvitations($projectId);
        $this->assertCount(1, $invitations);

        $member = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNull($member);
    }

    public function testInvitedAdminCanDeclineInvitation()
    {
        $projectId = $this->createProjectWithAdminHavingMfaEnabled();

        $this->normalUserWithMfaClient->updateOrganization(
            $this->organization['id'],
            [
                'mfaRequired' => 1,
            ]
        );

        $invitation = $this->normalUserWithMfaClient->inviteUserToProject($projectId, ['email' => $this->normalUser['email']]);

        $invitations = $this->normalUserWithMfaClient->listProjectInvitations($projectId);
        $this->assertCount(1, $invitations);

        $this->assertEquals($invitation, reset($invitations));

        $this->normalUserClient->declineMyProjectInvitation($invitation['id']);

        $invitations = $this->normalUserWithMfaClient->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);
    }

    private function createProjectWithAdminHavingMfaEnabled(): int
    {
        $project = $this->normalUserWithMfaClient->createProject($this->organization['id'], [
            'name' => 'My test',
        ]);

        return $project['id'];
    }
}
