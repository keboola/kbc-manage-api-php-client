<?php
/**
 * Created by PhpStorm.
 * User: martinhalamicek
 * Date: 15/10/15
 * Time: 15:29
 */

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

class ProjectMembershipRolesTest extends ClientMfaTestCase
{
    /** @var array */
    private $organization;

    /** @var array */
    private $project;

    private const ROLE_GUEST = 'guest';

    public function setUp()
    {
        parent::setUp();

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUserWithMfa['email']]);

        $this->organization = $this->normalUserWithMfaClient->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $this->project = $this->normalUserWithMfaClient->createProject($this->organization['id'], ['name' => ProjectMembershipRolesTest::class]);

        $this->normalUserWithMfaClient->addUserToProject(
            $this->project['id'],
            [
                'email' => $this->normalUser['email'],
                'role' => self::ROLE_GUEST,
            ]
        );

        foreach ($this->normalUserWithMfaClient->listProjectInvitations($this->project['id']) as $invitation) {
            $this->normalUserWithMfaClient->cancelProjectInvitation($this->project['id'], $invitation['id']);
        }

        foreach ($this->client->listMyProjectJoinRequests() as $joinRequest) {
            $this->client->deleteMyProjectJoinRequest($joinRequest['id']);
        }
    }

    private function restrictedActionTest(ClientException $e): void
    {
        $this->assertEquals(403, $e->getCode());
        $this->assertContains('Action is restricted for your role', $e->getMessage());
    }


    public function testGuestAdministratorCannotDeleteProject(): void
    {
        try {
            $this->normalUserClient->deleteProject($this->project['id']);
            $this->fail('Action should not be allowed to guest users');
        } catch (ClientException $e) {
            $this->restrictedActionTest($e);
        }

        $project = $this->normalUserWithMfaClient->getProject($this->project['id']);
        $this->assertEquals($this->project['id'], $project['id']);
    }

    public function testGuestAdministratorCannotAddAdministrator(): void
    {
        try {
            $this->normalUserClient->addUserToProject(
                $this->project['id'],
                [
                    'email' => 'spam@keboola.com',
                    'role' => self::ROLE_GUEST,
                ]
            );
            $this->fail('Action should not be allowed to guest users');
        } catch (ClientException $e) {
            $this->restrictedActionTest($e);
        }


        $membership = $this->findProjectUser($this->project['id'], 'spam@keboola.com');
        $this->assertNull($membership);
    }

    public function testGuestAdministratorCannotRemoveAdministrator(): void
    {
        try {
            $this->normalUserClient->removeUserFromProject(
                $this->project['id'],
                $this->normalUserWithMfa['email']
            );
            $this->fail('Action should not be allowed to guest users');
        } catch (ClientException $e) {
            $this->restrictedActionTest($e);
        }


        $membership = $this->findProjectUser($this->project['id'], $this->normalUserWithMfa['email']);
        $this->assertNotNull($membership);
    }

    public function testGuestAdministratorCannotUpdateProject(): void
    {
        try {
            $this->normalUserClient->updateProject(
                $this->project['id'],
                [
                    'name' => 'Test'
                ]
            );
            $this->fail('Action should not be allowed to guest users');
        } catch (ClientException $e) {
            $this->restrictedActionTest($e);
        }

        $project = $this->normalUserWithMfaClient->getProject($this->project['id']);
        $this->assertEquals($this->project['name'], $project['name']);
    }

    public function testGuestAdministratorCannotCreateStorageToken(): void
    {
        try {
            $this->normalUserClient->createProjectStorageToken(
                $this->project['id'],
                [
                ]
            );
            $this->fail('Action should not be allowed to guest users');
        } catch (ClientException $e) {
            $this->restrictedActionTest($e);
        }
    }

    public function testGuestAdministratorCannotInviteAdministrator(): void
    {
        try {
            $this->normalUserClient->inviteUserToProject(
                $this->project['id'],
                [
                    'email' => 'spam@keboola.com',
                    'role' => self::ROLE_GUEST,
                ]
            );
            $this->fail('Action should not be allowed to guest users');
        } catch (ClientException $e) {
            $this->restrictedActionTest($e);
        }

        $this->assertCount(0, $this->normalUserWithMfaClient->listProjectInvitations($this->project['id']));
    }

    public function testGuestAdministratorCannotCancelAdministratorInvitation(): void
    {
        $this->assertCount(0, $this->normalUserWithMfaClient->listProjectInvitations($this->project['id']));

        $invitation = $this->normalUserWithMfaClient->inviteUserToProject(
            $this->project['id'],
            [
                'email' => 'spam@keboola.com',
                'role' => self::ROLE_GUEST,
            ]
        );

        try {
            $this->normalUserClient->cancelProjectInvitation($this->project['id'], $invitation['id']);
            $this->fail('Action should not be allowed to guest users');
        } catch (ClientException $e) {
            $this->restrictedActionTest($e);
        }

        $this->assertCount(1, $this->normalUserWithMfaClient->listProjectInvitations($this->project['id']));
    }

    public function testGuestAdministratorCannotAcceptOrDeclineJoinRequest()
    {
        $this->normalUserWithMfaClient->updateOrganization($this->organization['id'], ['allowAutoJoin' => false]);

        $this->client->requestAccessToProject($this->project['id']);

        $joinRequests = $this->normalUserWithMfaClient->listProjectJoinRequests($this->project['id']);
        $this->assertCount(1, $joinRequests);

        $joinRequest = reset($joinRequests);

        try {
            $this->normalUserClient->rejectProjectJoinRequest($this->project['id'], $joinRequest['id']);
            $this->fail('Action should not be allowed to guest users');
        } catch (ClientException $e) {
            $this->restrictedActionTest($e);
        }

        $joinRequests = $this->normalUserWithMfaClient->listProjectJoinRequests($this->project['id']);
        $this->assertCount(1, $joinRequests);

        try {
            $this->normalUserClient->approveProjectJoinRequest($this->project['id'], $joinRequest['id']);
            $this->fail('Action should not be allowed to guest users');
        } catch (ClientException $e) {
            $this->restrictedActionTest($e);
        }

        $joinRequests = $this->normalUserWithMfaClient->listProjectJoinRequests($this->project['id']);
        $this->assertCount(1, $joinRequests);
    }

    public function testGuestAdministratorCannotChangeMembershipRole()
    {
        try {
            $this->normalUserClient->updateUserProjectMembership(
                $this->project['id'],
                $this->normalUserWithMfa['id'],
                ['role' => self::ROLE_GUEST]
            );
            $this->fail('Action should not be allowed to guest users');
        } catch (ClientException $e) {
            $this->restrictedActionTest($e);
        }


        $membership = $this->findProjectUser($this->project['id'], $this->normalUserWithMfa['email']);
        $this->assertEquals('admin', $membership['role']);
    }
}
