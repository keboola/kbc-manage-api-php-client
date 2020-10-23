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

        $this->guestRoleMemberClient = $this->normalUserClient;
        $this->guestUser = $this->normalUser;
    }

    public function limitedRolesData(): array
    {
        return [
            [
                ProjectRole::GUEST,
            ],
        ];
    }

    private function addNormalUserToProjectAsAdministrorWithLimitedRole(string $role): void
    {
        $this->normalUserWithMfaClient->addUserToProject(
            $this->project['id'],
            [
                'email' => $this->normalUser['email'],
                'role' => $role,
            ]
        );
    }

    /**
     * @dataProvider limitedRolesData
     */
    public function testAdministrorWithLimitedRoleCanViewProjectDetails(string $role): void
    {
        $this->addNormalUserToProjectAsAdministrorWithLimitedRole($role);

        $project = $this->guestRoleMemberClient->getProject($this->project['id']);
        $this->assertEquals($this->project['id'], $project['id']);
    }

    /**
     * @dataProvider limitedRolesData
     */
    public function testAdministrorWithLimitedRoleCannotDeleteProject(string $role): void
    {
        $this->addNormalUserToProjectAsAdministrorWithLimitedRole($role);

        try {
            $this->guestRoleMemberClient->deleteProject($this->project['id']);
            $this->fail('Action should not be allowed to guest users');
        } catch (ClientException $e) {
            $this->restrictedActionTest($e);
        }

        $project = $this->normalUserWithMfaClient->getProject($this->project['id']);
        $this->assertEquals($this->project['id'], $project['id']);
    }

    /**
     * @dataProvider limitedRolesData
     */
    public function testAdministrorWithLimitedRoleCannotUpdateProject(string $role): void
    {
        $this->addNormalUserToProjectAsAdministrorWithLimitedRole($role);

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

    /**
     * @dataProvider limitedRolesData
     */
    public function testAdministrorWithLimitedRoleCanListAndGetProjectInvitations(string $role): void
    {
        $this->addNormalUserToProjectAsAdministrorWithLimitedRole($role);

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

    /**
     * @dataProvider limitedRolesData
     */
    public function testAdministrorWithLimitedRoleCannotInviteAdministrator(string $role): void
    {
        $this->addNormalUserToProjectAsAdministrorWithLimitedRole($role);

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

    /**
     * @dataProvider limitedRolesData
     */
    public function testAdministrorWithLimitedRoleCannotCancelAdministratorInvitation(string $role): void
    {
        $this->addNormalUserToProjectAsAdministrorWithLimitedRole($role);

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

    /**
     * @dataProvider limitedRolesData
     */
    public function testAdministrorWithLimitedRoleCanListProjectUsers(string $role): void
    {
        $this->addNormalUserToProjectAsAdministrorWithLimitedRole($role);

        $members = $this->guestRoleMemberClient->listProjectUsers($this->project['id']);
        $this->assertCount(2, $members);
    }

    /**
     * @dataProvider limitedRolesData
     */
    public function testAdministrorWithLimitedRoleCannotAddAdministrator(string $role): void
    {
        $this->addNormalUserToProjectAsAdministrorWithLimitedRole($role);

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

    /**
     * @dataProvider limitedRolesData
     */
    public function testAdministrorWithLimitedRoleCannotRemoveAdministrator(string $role): void
    {
        $this->addNormalUserToProjectAsAdministrorWithLimitedRole($role);

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

    /**
     * @dataProvider limitedRolesData
     */
    public function testAdministrorWithLimitedRoleCanLeaveProject(string $role): void
    {
        $this->addNormalUserToProjectAsAdministrorWithLimitedRole($role);

        $this->guestRoleMemberClient->removeUserFromProject(
            $this->project['id'],
            $this->guestUser['id']
        );

        $membership = $this->findProjectUser($this->project['id'], $this->guestUser['email']);
        $this->assertNull($membership);
    }

    /**
     * @dataProvider limitedRolesData
     */
    public function testAdministrorWithLimitedRoleCanListAndGetProjectJoinRequests(string $role): void
    {
        $this->addNormalUserToProjectAsAdministrorWithLimitedRole($role);

        $this->assertCount(0, $this->guestRoleMemberClient->listProjectJoinRequests($this->project['id']));

        $this->normalUserWithMfaClient->updateOrganization($this->organization['id'], ['allowAutoJoin' => false]);

        $this->client->requestAccessToProject($this->project['id']);

        $joinRequests = $this->guestRoleMemberClient->listProjectJoinRequests($this->project['id']);
        $this->assertCount(1, $joinRequests);

        $joinRequest = reset($joinRequests);
        $this->assertEquals($joinRequest, $this->guestRoleMemberClient->getProjectJoinRequest($this->project['id'], $joinRequest['id']));
    }

    /**
     * @dataProvider limitedRolesData
     */
    public function testAdministrorWithLimitedRoleCannotApproveOrDeclineJoinRequest(string $role): void
    {
        $this->addNormalUserToProjectAsAdministrorWithLimitedRole($role);

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

    /**
     * @dataProvider limitedRolesData
     */
    public function testAdministrorWithLimitedRoleCannotCreateStorageToken(string $role): void
    {
        $this->addNormalUserToProjectAsAdministrorWithLimitedRole($role);

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

    /**
     * @dataProvider limitedRolesData
     */
    public function testAdministrorWithLimitedRoleCannotChangeMembershipRole(string $role): void
    {
        $this->addNormalUserToProjectAsAdministrorWithLimitedRole($role);

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

    /**
     * @dataProvider limitedRolesData
     */
    public function testAdministrorWithLimitedRoleCannotChangeOrganization(string $role): void
    {
        $this->addNormalUserToProjectAsAdministrorWithLimitedRole($role);

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
