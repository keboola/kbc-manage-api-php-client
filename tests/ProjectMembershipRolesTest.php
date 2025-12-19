<?php

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Client;
use Keboola\ManageApi\ClientException;
use Keboola\ManageApi\ProjectRole;

class ProjectMembershipRolesTest extends ClientMfaTestCase
{
    private const SHARE_ROLE_EXPECTED_ERROR = 'Only member of the project\'s organization can grant "share" role to other users.';

    private array $organization;

    private array $project;

    private \Keboola\ManageApi\Client $guestRoleMemberClient;

    private array $guestUser;

    public function setUp(): void
    {
        parent::setUp();

        $featuresToRemoveFromUsers = [
            self::CAN_MANAGE_PROJECT_SETTINGS_FEATURE_NAME,
        ];

        foreach ($featuresToRemoveFromUsers as $feature) {
            $this->client->removeUserFeature($this->normalUser['email'], $feature);
        }

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUserWithMfa['email']]);

        $this->organization = $this->normalUserWithMfaClient->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $this->project = $this->createRedshiftProjectForClient($this->normalUserWithMfaClient, $this->organization['id'], ['name' => ProjectMembershipRolesTest::class]);

        $this->guestRoleMemberClient = $this->normalUserClient;
        $this->guestUser = $this->normalUser;
    }

    public function limitedRolesData(): array
    {
        return [
            [
                ProjectRole::GUEST,
            ],
            [
                ProjectRole::READ_ONLY,
            ],
        ];
    }

    public function adminRolesData(): array
    {
        return [
            [
                ProjectRole::SHARE,
            ],
            [
                ProjectRole::ADMIN,
            ],
        ];
    }

    private function addNormalUserToProjectAsAdministratorWithLimitedRole(string $role): void
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
    public function testAdministratorWithLimitedRoleCanViewProjectDetails(string $role): void
    {
        $this->addNormalUserToProjectAsAdministratorWithLimitedRole($role);

        $project = $this->guestRoleMemberClient->getProject($this->project['id']);
        $this->assertEquals($this->project['id'], $project['id']);
    }

    /**
     * @dataProvider limitedRolesData
     */
    public function testAdministratorWithLimitedRoleCannotDeleteProject(string $role): void
    {
        $this->addNormalUserToProjectAsAdministratorWithLimitedRole($role);

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
    public function testAdministratorWithLimitedRoleCannotUpdateProject(string $role): void
    {
        $this->addNormalUserToProjectAsAdministratorWithLimitedRole($role);

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
    public function testAdministratorWithLimitedRoleCanListAndGetProjectInvitations(string $role): void
    {
        $this->addNormalUserToProjectAsAdministratorWithLimitedRole($role);

        $this->assertCount(0, $this->guestRoleMemberClient->listProjectInvitations($this->project['id']));

        $invitation = $this->normalUserWithMfaClient->inviteUserToProject(
            $this->project['id'],
            [
                'email' => 'devel-tests@keboola.com',
                'role' => $role,
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
    public function testAdministratorWithLimitedRoleCannotInviteAdministrator(string $role): void
    {
        $this->addNormalUserToProjectAsAdministratorWithLimitedRole($role);

        try {
            $this->guestRoleMemberClient->inviteUserToProject(
                $this->project['id'],
                [
                    'email' => 'devel-tests@keboola.com',
                    'role' => $role,
                ]
            );
            $this->fail('Action should not be allowed to guest users');
        } catch (ClientException $e) {
            $this->restrictedActionTest($e);
        }

        $this->assertCount(0, $this->normalUserWithMfaClient->listProjectInvitations($this->project['id']));
    }

    /**
     * @dataProvider adminRolesData
     */
    public function testNonOrganizationAdministratorCannotInviteAdministratorWithShareRole(string $role): void
    {
        $this->addNormalUserToProjectAsAdministratorWithLimitedRole($role);

        try {
            $this->normalUserClient->inviteUserToProject(
                $this->project['id'],
                [
                    'email' => 'devel-tests@keboola.com',
                    'role' => ProjectRole::SHARE,
                ]
            );
            $this->fail('Only org. admins should be able to set share role for users');
        } catch (ClientException $e) {
            $expectedMessage = 'Only member of the project\'s organization can grant "share" role to other users.';
            $this->assertSame(400, $e->getCode());
            $this->assertSame(self::SHARE_ROLE_EXPECTED_ERROR, $e->getMessage());
        }

        $this->assertCount(0, $this->normalUserWithMfaClient->listProjectInvitations($this->project['id']));
    }

    /**
     * @dataProvider limitedRolesData
     */
    public function testAdministratorWithLimitedRoleCannotCancelAdministratorInvitation(string $role): void
    {
        $this->addNormalUserToProjectAsAdministratorWithLimitedRole($role);

        $this->assertCount(0, $this->normalUserWithMfaClient->listProjectInvitations($this->project['id']));

        $invitation = $this->normalUserWithMfaClient->inviteUserToProject(
            $this->project['id'],
            [
                'email' => 'devel-tests@keboola.com',
                'role' => $role,
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
    public function testAdministratorWithLimitedRoleCanListProjectUsers(string $role): void
    {
        $this->addNormalUserToProjectAsAdministratorWithLimitedRole($role);

        $members = $this->guestRoleMemberClient->listProjectUsers($this->project['id']);
        $this->assertCount(2, $members);
    }

    /**
     * @dataProvider limitedRolesData
     */
    public function testAdministratorWithLimitedRoleCannotAddAdministrator(string $role): void
    {
        $this->addNormalUserToProjectAsAdministratorWithLimitedRole($role);

        try {
            $this->guestRoleMemberClient->addUserToProject(
                $this->project['id'],
                [
                    'email' => 'devel-tests@keboola.com',
                    'role' => $role,
                ]
            );
            $this->fail('Action should not be allowed to guest users');
        } catch (ClientException $e) {
            $this->restrictedActionTest($e);
        }

        $membership = $this->findProjectUser($this->project['id'], 'devel-tests@keboola.com');
        $this->assertNull($membership);
    }

    /**
     * @dataProvider adminRolesData
     */
    public function testNonOrganizationAdministratorCannotAddAdministratorWithShareRole(string $role): void
    {
        $this->addNormalUserToProjectAsAdministratorWithLimitedRole($role);

        try {
            $this->normalUserClient->addUserToProject(
                $this->project['id'],
                [
                    'email' => 'devel-tests@keboola.com',
                    'role' => ProjectRole::SHARE,
                ]
            );
            $this->fail('Only org. admins should be able to set share role for users');
        } catch (ClientException $e) {
            $this->assertSame(400, $e->getCode());
            $this->assertSame(self::SHARE_ROLE_EXPECTED_ERROR, $e->getMessage());
        }

        $membership = $this->findProjectUser($this->project['id'], 'devel-tests@keboola.com');
        $this->assertNull($membership);
    }

    /**
     * @dataProvider adminRolesData
     */
    public function testNonOrganizationAdministratorCannotChangeMembershipToShareRole(string $role): void
    {
        $this->addNormalUserToProjectAsAdministratorWithLimitedRole($role);

        try {
            $this->normalUserClient->updateUserProjectMembership(
                $this->project['id'],
                $this->normalUserWithMfa['id'],
                ['role' => ProjectRole::SHARE]
            );
            $this->fail('Only org. admins should be able to set share role for users');
        } catch (ClientException $e) {
            $this->assertSame(400, $e->getCode());
            $this->assertSame(self::SHARE_ROLE_EXPECTED_ERROR, $e->getMessage());
        }

        $membership = $this->findProjectUser($this->project['id'], $this->normalUserWithMfa['email']);
        $this->assertNotNull($membership);
        $this->assertEquals('admin', $membership['role']);
    }


    /**
     * @dataProvider limitedRolesData
     */
    public function testAdministratorWithLimitedRoleCannotRemoveAdministrator(string $role): void
    {
        $this->addNormalUserToProjectAsAdministratorWithLimitedRole($role);

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
    public function testAdministratorWithLimitedRoleCanLeaveProject(string $role): void
    {
        $this->addNormalUserToProjectAsAdministratorWithLimitedRole($role);

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
    public function testAdministratorWithLimitedRoleCanListAndGetProjectJoinRequests(string $role): void
    {
        $this->addNormalUserToProjectAsAdministratorWithLimitedRole($role);

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
    public function testAdministratorWithLimitedRoleCannotApproveOrDeclineJoinRequest(string $role): void
    {
        $this->addNormalUserToProjectAsAdministratorWithLimitedRole($role);

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
    public function testAdministratorWithLimitedRoleCannotCreateStorageToken(string $role): void
    {
        $this->addNormalUserToProjectAsAdministratorWithLimitedRole($role);

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
    public function testAdministratorWithLimitedRoleCannotChangeMembershipRole(string $role): void
    {
        $this->addNormalUserToProjectAsAdministratorWithLimitedRole($role);

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
        $this->assertNotNull($membership);
        $this->assertEquals('admin', $membership['role']);
    }

    /**
     * @dataProvider limitedRolesData
     */
    public function testAdministratorWithLimitedRoleCannotChangeOrganization(string $role): void
    {
        $this->addNormalUserToProjectAsAdministratorWithLimitedRole($role);

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

    private function restrictedActionTest(ClientException $e): void
    {
        $this->assertEquals(403, $e->getCode());
        $this->assertStringContainsString('Action is restricted for your role', $e->getMessage());
    }
}
