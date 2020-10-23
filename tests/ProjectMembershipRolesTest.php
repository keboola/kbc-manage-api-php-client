<?php

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Client;
use Keboola\ManageApi\ClientException;
use Keboola\ManageApi\ProjectRole;

class ProjectMembershipRolesTest extends ClientMfaTestCase
{
    /** @var array */
    private $organization;

    /** @var array */
    private $project;

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
                'role' => ProjectRole::GUEST,
            ]
        );

        $this->guestRoleMemberClient = $this->normalUserClient;
        $this->guestUser = $this->normalUser;
    }

    public function testAdministrorWithLimitedRoleCanViewProjectDetails()
    {
        $project = $this->guestRoleMemberClient->getProject($this->project['id']);
        $this->assertEquals($this->project['id'], $project['id']);
    }

    public function testAdministrorWithLimitedRoleCannotDeleteProject()
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

    public function testAdministrorWithLimitedRoleCannotUpdateProject()
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

    public function testAdministrorWithLimitedRoleCanListAndGetProjectInvitations()
    {
        $this->assertCount(0, $this->guestRoleMemberClient->listProjectInvitations($this->project['id']));

        $invitation = $this->normalUserWithMfaClient->inviteUserToProject(
            $this->project['id'],
            [
                'email' => 'spam@keboola.com',
                'role' => ProjectRole::GUEST,
            ]
        );

        $invitations = $this->guestRoleMemberClient->listProjectInvitations($this->project['id']);
        $this->assertCount(1, $invitations);

        $this->assertEquals($invitation, reset($invitations));

        $this->assertEquals($invitation, $this->guestRoleMemberClient->getProjectInvitation($this->project['id'], $invitation['id']));
    }

    public function testAdministrorWithLimitedRoleCannotInviteAdministrator()
    {
        try {
            $this->guestRoleMemberClient->inviteUserToProject(
                $this->project['id'],
                [
                    'email' => 'spam@keboola.com',
                    'role' => ProjectRole::GUEST,
                ]
            );
            $this->fail('Action should not be allowed to guest users');
        } catch (ClientException $e) {
            $this->restrictedActionTest($e);
        }

        $this->assertCount(0, $this->normalUserWithMfaClient->listProjectInvitations($this->project['id']));
    }

    public function testAdministrorWithLimitedRoleCannotCancelAdministratorInvitation()
    {
        $this->assertCount(0, $this->normalUserWithMfaClient->listProjectInvitations($this->project['id']));

        $invitation = $this->normalUserWithMfaClient->inviteUserToProject(
            $this->project['id'],
            [
                'email' => 'spam@keboola.com',
                'role' => ProjectRole::GUEST,
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

    public function testAdministrorWithLimitedRoleCanListProjectUsers()
    {
        $members = $this->guestRoleMemberClient->listProjectUsers($this->project['id']);
        $this->assertCount(2, $members);
    }

    public function testAdministrorWithLimitedRoleCannotAddAdministrator()
    {
        try {
            $this->guestRoleMemberClient->addUserToProject(
                $this->project['id'],
                [
                    'email' => 'spam@keboola.com',
                    'role' => ProjectRole::GUEST,
                ]
            );
            $this->fail('Action should not be allowed to guest users');
        } catch (ClientException $e) {
            $this->restrictedActionTest($e);
        }

        $membership = $this->findProjectUser($this->project['id'], 'spam@keboola.com');
        $this->assertNull($membership);
    }

    public function testAdministrorWithLimitedRoleCannotRemoveAdministrator()
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

    public function testAdministrorWithLimitedRoleCanLeaveProject()
    {
        $this->guestRoleMemberClient->removeUserFromProject(
            $this->project['id'],
            $this->guestUser['id']
        );

        $membership = $this->findProjectUser($this->project['id'], $this->guestUser['email']);
        $this->assertNull($membership);
    }

    public function testAdministrorWithLimitedRoleCanListAndGetProjectJoinRequests()
    {
        $this->assertCount(0, $this->guestRoleMemberClient->listProjectJoinRequests($this->project['id']));

        $this->normalUserWithMfaClient->updateOrganization($this->organization['id'], ['allowAutoJoin' => false]);

        $this->client->requestAccessToProject($this->project['id']);

        $joinRequests = $this->guestRoleMemberClient->listProjectJoinRequests($this->project['id']);
        $this->assertCount(1, $joinRequests);

        $joinRequest = reset($joinRequests);
        $this->assertEquals($joinRequest, $this->guestRoleMemberClient->getProjectJoinRequest($this->project['id'], $joinRequest['id']));
    }

    public function testAdministrorWithLimitedRoleCannotApproveOrDeclineJoinRequest()
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

    public function testAdministrorWithLimitedRoleCannotCreateStorageToken()
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

    public function testAdministrorWithLimitedRoleCannotChangeMembershipRole()
    {
        try {
            $this->guestRoleMemberClient->updateUserProjectMembership(
                $this->project['id'],
                $this->normalUserWithMfa['id'],
                ['role' => ProjectRole::GUEST]
            );
            $this->fail('Action should not be allowed to guest users');
        } catch (ClientException $e) {
            $this->restrictedActionTest($e);
        }

        $membership = $this->findProjectUser($this->project['id'], $this->normalUserWithMfa['email']);
        $this->assertEquals('admin', $membership['role']);
    }

    public function testAdministrorWithLimitedRoleCannotChangeOrganization()
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
