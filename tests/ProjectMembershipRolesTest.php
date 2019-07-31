<?php

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Client;
use Keboola\ManageApi\ClientException;

class ProjectMembershipRolesTest extends ClientMfaTestCase
{
    /** @var array */
    private $organization;

    /** @var array */
    private $project;

    private const ROLE_GUEST = 'guest';

    /** @var Client */
    private $guestRoleMemberClient;

    /** @var array */
    private $guestUser;

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

        $this->guestRoleMemberClient = $this->normalUserClient;
        $this->guestUser = $this->normalUser;
    }

    public function testGuestAdministratorCanViewProjectDetails()
    {
        $project = $this->guestRoleMemberClient->getProject($this->project['id']);
        $this->assertEquals($this->project['id'], $project['id']);
    }

    public function testGuestAdministratorCannotDeleteProject()
    {
        try {
            $this->guestRoleMemberClient->deleteProject($this->project['id']);
            $this->fail('Action should not be allowed to guest users');
        } catch (ClientException $e) {
            $this->restrictedActionTest($e);
        }

        $project = $this->normalUserWithMfaClient->getProject($this->project['id']);
        $this->assertEquals($this->project['id'], $project['id']);
    }

    public function testGuestAdministratorCannotUpdateProject()
    {
        try {
            $this->guestRoleMemberClient->updateProject(
                $this->project['id'],
                [
                    'name' => 'Test',
                ]
            );
            $this->fail('Action should not be allowed to guest users');
        } catch (ClientException $e) {
            $this->restrictedActionTest($e);
        }

        $project = $this->normalUserWithMfaClient->getProject($this->project['id']);
        $this->assertEquals($this->project['name'], $project['name']);
    }

    public function testGuestAdministratorCanListAndGetProjectInvitations()
    {
        $this->assertCount(0, $this->guestRoleMemberClient->listProjectInvitations($this->project['id']));

        $invitation = $this->normalUserWithMfaClient->inviteUserToProject(
            $this->project['id'],
            [
                'email' => 'spam@keboola.com',
                'role' => self::ROLE_GUEST,
            ]
        );

        $invitations = $this->guestRoleMemberClient->listProjectInvitations($this->project['id']);
        $this->assertCount(1, $invitations);

        $this->assertEquals($invitation, reset($invitations));

        $this->assertEquals($invitation, $this->guestRoleMemberClient->getProjectInvitation($this->project['id'], $invitation['id']));
    }

    public function testGuestAdministratorCannotInviteAdministrator()
    {
        try {
            $this->guestRoleMemberClient->inviteUserToProject(
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

    public function testGuestAdministratorCannotCancelAdministratorInvitation()
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
            $this->guestRoleMemberClient->cancelProjectInvitation($this->project['id'], $invitation['id']);
            $this->fail('Action should not be allowed to guest users');
        } catch (ClientException $e) {
            $this->restrictedActionTest($e);
        }

        $this->assertCount(1, $this->normalUserWithMfaClient->listProjectInvitations($this->project['id']));
    }

    public function testGuestAdministratorCanListProjectUsers()
    {
        $members = $this->guestRoleMemberClient->listProjectUsers($this->project['id']);
        $this->assertCount(2, $members);
    }

    public function testGuestAdministratorCannotAddAdministrator()
    {
        try {
            $this->guestRoleMemberClient->addUserToProject(
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

    public function testGuestAdministratorCannotRemoveAdministrator()
    {
        try {
            $this->guestRoleMemberClient->removeUserFromProject(
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

    public function testGuestAdministratorCanLeaveProject()
    {
        $this->guestRoleMemberClient->removeUserFromProject(
            $this->project['id'],
            $this->guestUser['id']
        );

        $membership = $this->findProjectUser($this->project['id'], $this->guestUser['email']);
        $this->assertNull($membership);
    }

    public function testGuestAdministratorCanListAndGetProjectJoinRequests()
    {
        $this->assertCount(0, $this->guestRoleMemberClient->listProjectJoinRequests($this->project['id']));

        $this->normalUserWithMfaClient->updateOrganization($this->organization['id'], ['allowAutoJoin' => false]);

        $this->client->requestAccessToProject($this->project['id']);

        $joinRequests = $this->guestRoleMemberClient->listProjectJoinRequests($this->project['id']);
        $this->assertCount(1, $joinRequests);

        $joinRequest = reset($joinRequests);
        $this->assertEquals($joinRequest, $this->guestRoleMemberClient->getProjectJoinRequest($this->project['id'], $joinRequest['id']));
    }

    public function testGuestAdministratorCannotApproveOrDeclineJoinRequest()
    {
        $this->normalUserWithMfaClient->updateOrganization($this->organization['id'], ['allowAutoJoin' => false]);

        $this->client->requestAccessToProject($this->project['id']);

        $joinRequests = $this->normalUserWithMfaClient->listProjectJoinRequests($this->project['id']);
        $this->assertCount(1, $joinRequests);

        $joinRequest = reset($joinRequests);

        try {
            $this->guestRoleMemberClient->rejectProjectJoinRequest($this->project['id'], $joinRequest['id']);
            $this->fail('Action should not be allowed to guest users');
        } catch (ClientException $e) {
            $this->restrictedActionTest($e);
        }

        $joinRequests = $this->normalUserWithMfaClient->listProjectJoinRequests($this->project['id']);
        $this->assertCount(1, $joinRequests);

        try {
            $this->guestRoleMemberClient->approveProjectJoinRequest($this->project['id'], $joinRequest['id']);
            $this->fail('Action should not be allowed to guest users');
        } catch (ClientException $e) {
            $this->restrictedActionTest($e);
        }

        $joinRequests = $this->normalUserWithMfaClient->listProjectJoinRequests($this->project['id']);
        $this->assertCount(1, $joinRequests);
    }

    public function testGuestAdministratorCannotCreateStorageToken()
    {
        try {
            $this->guestRoleMemberClient->createProjectStorageToken(
                $this->project['id'],
                [
                ]
            );
            $this->fail('Action should not be allowed to guest users');
        } catch (ClientException $e) {
            $this->restrictedActionTest($e);
        }
    }

    public function testGuestAdministratorCannotChangeMembershipRole()
    {
        try {
            $this->guestRoleMemberClient->updateUserProjectMembership(
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

    public function testGuestAdministratorCannotChangeOrganization()
    {
        $organization = $this->normalUserWithMfaClient->createOrganization($this->testMaintainerId, [
            'name' => 'My destination org',
        ]);

        try {
            $this->guestRoleMemberClient->changeProjectOrganization(
                $this->project['id'],
                $organization['id']
            );
            $this->fail('Action should not be allowed to guest users');
        } catch (ClientException $e) {
            $this->restrictedActionTest($e);
        }

        $project = $this->guestRoleMemberClient->getProject($this->project['id']);
        $this->assertEquals($this->organization['id'], $project['organization']['id']);
    }

    private function restrictedActionTest(ClientException $e)
    {
        $this->assertEquals(403, $e->getCode());
        $this->assertContains('Action is restricted for your role', $e->getMessage());
    }
}
