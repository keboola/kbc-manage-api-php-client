<?php

declare(strict_types=1);

namespace Keboola\ManageApiTest;

use Generator;
use Keboola\ManageApi\Backend;
use Keboola\ManageApi\Client;
use Keboola\ManageApi\ClientException;
use Keboola\ManageApi\ProjectRole;
use Keboola\ManageApiTest\Utils\EnvVariableHelper;

final class ProjectWithProtectedDefaultBranchTest extends ClientTestCase
{
    private $organization;

    /**
     * Create empty organization without admins, remove admins from test maintainer and delete all their join requests
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => 'devel-tests+spam@keboola.com']);

        foreach ($this->client->listMaintainerMembers($this->testMaintainerId) as $member) {
            if ($member['id'] === $this->normalUser['id']) {
                $this->client->removeUserFromMaintainer($this->testMaintainerId, $member['id']);
            }

            if ($member['id'] === $this->superAdmin['id']) {
                $this->client->removeUserFromMaintainer($this->testMaintainerId, $member['id']);
            }
        }

        $this->organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $this->client->addUserToOrganization($this->organization['id'], ['email' => 'devel-tests@keboola.com']);
        $this->client->removeUserFromOrganization($this->organization['id'], $this->superAdmin['id']);

        foreach ($this->normalUserClient->listMyProjectInvitations() as $invitation) {
            $this->normalUserClient->declineMyProjectInvitation($invitation['id']);
        }

        foreach ($this->client->listMyProjectInvitations() as $invitation) {
            $this->client->declineMyProjectInvitation($invitation['id']);
        }
        $this->cleanupFeatures($this->testFeatureName(), 'project');
    }

    private function createProject(string $userEmail, ?Client $client = null): array
    {
        if (!$client instanceof \Keboola\ManageApi\Client) {
            $client = $this->client;
        }
        $this->client->addUserToOrganization(
            $this->organization['id'],
            ['email' => $userEmail]
        );
        $project = $client->createProject($this->organization['id'], [
            'name' => 'My test',
            'defaultBackend' => Backend::REDSHIFT,
            'type' => 'sox',
        ]);

        $this->assertContains('protected-default-branch', $project['features']);

        $admins = $this->client->listProjectUsers($project['id']);
        $this->assertCount(1, $admins);
        $this->assertSame('productionManager', $admins[0]['role']);
        return $project;
    }

    public function autoJoinProvider(): array
    {
        return [
            [
                true,
            ],
            [
                false,
            ],
        ];
    }

    public function inviteUserToProjectWithRoleData(): array
    {
        return [
            [
                ProjectRole::PRODUCTION_MANAGER,
            ],
            [
                ProjectRole::DEVELOPER,
            ],
            [
                ProjectRole::READ_ONLY,
            ],
            [
                ProjectRole::REVIEWER,
            ],
        ];
    }

    public function inviteUserToProjectInvalidRoleData(): array
    {
        return [
            [
                ProjectRole::ADMIN,
            ],
            [
                ProjectRole::SHARE,
            ],
            [
                ProjectRole::GUEST,
            ],
        ];
    }

    /**
     * @dataProvider autoJoinProvider
     * @param bool $allowAutoJoin
     */
    public function testNobodyCanInviteRegardlessOfAllowAutoJoin(bool $allowAutoJoin): void
    {
        $inviteeEmail = 'devel-tests@keboola.com';
        ['id' => $projectId] = $this->createProject($this->superAdmin['email']);

        $this->client->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => $allowAutoJoin,
        ]);

        $invitations = $this->client->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);

        $projectUser = $this->findProjectUser($projectId, $inviteeEmail);
        $this->assertNull($projectUser);

        try {
            $this->normalUserClient->listProjectInvitations($projectId);
            $this->fail('List invitations should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        try {
            $this->normalUserClient->inviteUserToProject($projectId, ['email' => $inviteeEmail]);
            $this->fail('Invite someone should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $invitations = $this->client->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);

        $projectUser = $this->findProjectUser($projectId, $inviteeEmail);
        $this->assertNull($projectUser);
    }

    /**
     * @dataProvider autoJoinProvider
     * @param bool $allowAutoJoin
     */
    public function testOrganizationAdminCanInviteRegardlessOfAllowAutoJoin(bool $allowAutoJoin): void
    {
        ['id' => $projectId] = $this->createProject($this->superAdmin['email']);

        $inviteeEmail = 'devel-tests@keboola.com';
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $this->normalUserClient->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => $allowAutoJoin,
        ]);

        $invitations = $this->normalUserClient->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);

        $projectUser = $this->findProjectUser($projectId, $inviteeEmail);
        $this->assertNull($projectUser);

        $invitation = $this->normalUserClient->inviteUserToProject($projectId, ['email' => $inviteeEmail]);

        $this->assertEquals('', $invitation['reason']);
        $this->assertEmpty($invitation['expires']);

        $invitee = $this->client->getUser($inviteeEmail);

        $this->assertEquals($invitee['id'], $invitation['user']['id']);
        $this->assertEquals($invitee['email'], $invitation['user']['email']);
        $this->assertEquals($invitee['name'], $invitation['user']['name']);

        $this->assertEquals($this->normalUser['id'], $invitation['creator']['id']);
        $this->assertEquals($this->normalUser['email'], $invitation['creator']['email']);
        $this->assertEquals($this->normalUser['name'], $invitation['creator']['name']);

        $invitations = $this->normalUserClient->listProjectInvitations($projectId);
        $this->assertCount(1, $invitations);

        $this->assertEquals($invitation, reset($invitations));

        $this->assertEquals($invitation, $this->normalUserClient->getProjectInvitation($projectId, $invitation['id']));

        $this->normalUserClient->cancelProjectInvitation($projectId, $invitation['id']);

        $invitations = $this->normalUserClient->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);

        $projectUser = $this->findProjectUser($projectId, $inviteeEmail);
        $this->assertNull($projectUser);
    }

    public function userCannotInviteProvider(): Generator
    {
        foreach ($this->autoJoinProvider() as $autoJoin) {
            $autoJoinTF = $autoJoin[0] ? 'true' : 'false';
            yield 'autojoin ' . $autoJoinTF . ' role developer' => [
                $autoJoin[0],
                ProjectRole::DEVELOPER,
            ];
            yield 'autojoin ' . ($autoJoinTF) . ' role reviewer' => [
                $autoJoin[0],
                ProjectRole::REVIEWER,
            ];
            yield 'autojoin ' . ($autoJoinTF) . ' role readOnly' => [
                $autoJoin[0],
                ProjectRole::READ_ONLY,
            ];
        }
    }

    /**
     * @dataProvider userCannotInviteProvider
     * @param bool $allowAutoJoin
     */
    public function testProjectMemberCannotInviteRegardlessOfAllowAutoJoin(bool $allowAutoJoin, string $role): void
    {
        $inviteeEmail = 'devel-tests@keboola.com';
        $project = $this->createProject($this->superAdmin['email']);
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $project['id'];

        $this->normalUserClient->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => $allowAutoJoin,
        ]);

        $invitations = $this->normalUserClient->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);

        $projectUser = $this->findProjectUser($projectId, $inviteeEmail);
        $this->assertNull($projectUser);

        $this->client->addUserToProject(
            $projectId,
            [
                'email' => $this->normalUser['email'],
                'role' => $role,
            ]
        );
        $this->expectExceptionMessage('Action is restricted for your role');
        $this->normalUserClient->inviteUserToProject(
            $projectId,
            [
                'email' => $inviteeEmail,
                'role' => 'developer',
            ]
        );
    }

    public function testAdminAcceptsInvitation(): void
    {
        $project = $this->createProject($this->superAdmin['email']);
        $projectId = $project['id'];

        $invitations = $this->client->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);

        $this->client->inviteUserToProject($projectId, ['email' => $this->normalUser['email']]);

        $invitations = $this->normalUserClient->listMyProjectInvitations();
        $this->assertCount(1, $invitations);

        $invitation = reset($invitations);

        $this->assertEquals('', $invitation['reason']);
        $this->assertEmpty($invitation['expires']);

        $this->assertEquals($project['id'], $invitation['project']['id']);
        $this->assertEquals($project['name'], $invitation['project']['name']);

        $this->assertEquals($this->superAdmin['id'], $invitation['creator']['id']);
        $this->assertEquals($this->superAdmin['email'], $invitation['creator']['email']);
        $this->assertEquals($this->superAdmin['name'], $invitation['creator']['name']);

        $this->assertEquals($invitation, $this->normalUserClient->getMyProjectInvitation($invitation['id']));

        $this->normalUserClient->acceptMyProjectInvitation($invitation['id']);

        $invitations = $this->normalUserClient->listMyProjectInvitations();
        $this->assertCount(0, $invitations);

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNotNull($projectUser);

        $this->assertArrayHasKey('invitor', $projectUser);
        $this->assertArrayHasKey('approver', $projectUser);

        $this->assertNotEmpty($projectUser['invitor']);
        $this->assertEquals($this->superAdmin['id'], $projectUser['invitor']['id']);
        $this->assertEquals($this->superAdmin['email'], $projectUser['invitor']['email']);
        $this->assertEquals($this->superAdmin['name'], $projectUser['invitor']['name']);
        $this->assertSame('developer', $projectUser['role']);

        $this->assertNull($projectUser['approver']);
    }

    /**
     * @dataProvider inviteUserToProjectWithRoleData
     */
    public function testInvitationAttributesPropagationToProjectMembership(string $role): void
    {
        ['id' => $projectId] = $this->createProject($this->superAdmin['email']);

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNull($projectUser);

        $invitations = $this->client->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);

        $invitation = $this->client->inviteUserToProject($projectId, [
            'email' => $this->normalUser['email'],
            'reason' => 'Testing reason propagation',
            'role' => $role,
            'expirationSeconds' => 3600,
        ]);

        $this->assertEquals('Testing reason propagation', $invitation['reason']);
        $this->assertEquals($role, $invitation['role']);
        $this->assertNotEmpty($invitation['expires']);

        $this->normalUserClient->acceptMyProjectInvitation($invitation['id']);

        $invitations = $this->normalUserClient->listMyProjectInvitations();
        $this->assertCount(0, $invitations);

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNotNull($projectUser);

        $this->assertEquals($invitation['reason'], $projectUser['reason']);
        $this->assertEquals($role, $projectUser['role']);
        $this->assertNotEmpty($projectUser['expires']);
    }

    /**
     * @dataProvider inviteUserToProjectInvalidRoleData
     */
    public function testInvitationClassicProjectRolesAreDisabled(string $role): void
    {
        ['id' => $projectId] = $this->createProject($this->superAdmin['email']);

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNull($projectUser);

        $invitations = $this->client->listProjectInvitations($projectId);
        $this->assertCount(0, $invitations);

        $this->expectExceptionMessage(sprintf(
            'Role "%s" is not valid. Allowed roles are: readOnly, developer, reviewer, productionManager',
            $role
        ));
        $this->client->inviteUserToProject($projectId, [
            'email' => $this->normalUser['email'],
            'reason' => 'Testing reason propagation',
            'role' => $role,
            'expirationSeconds' => 3600,
        ]);
    }

    public function testSuperAdminJoinProjectAsDeveloper(): void
    {
        ['id' => $projectId] = $this->createProject($this->normalUser['email'], $this->normalUserClient);

        $projectUser = $this->findProjectUser($projectId, $this->superAdmin['email']);
        $this->assertNull($projectUser);

        $this->client->joinProject($projectId);

        $projectUser = $this->findProjectUser($projectId, $this->superAdmin['email']);
        $this->assertNotNull($projectUser);
        $this->assertSame($projectUser['role'], 'developer');

        $this->assertArrayHasKey('approver', $projectUser);

        $this->assertEquals($this->superAdmin['id'], $projectUser['approver']['id']);
        $this->assertEquals($this->superAdmin['email'], $projectUser['approver']['email']);
        $this->assertEquals($this->superAdmin['name'], $projectUser['approver']['name']);
    }

    public function testAddFeatureToProject(): void
    {
        ['id' => $projectId] = $this->createProject($this->superAdmin['email'], $this->client);

        $featureName = $this->testFeatureName();
        $this->client->createFeature(
            $featureName,
            'project',
            $featureName,
            $featureName,
            true,
            true
        );

        $getClient = function (Client $client): Client {
            $sessionToken = $client->createSessionToken();
            return $this->getClient([
                'token' => $sessionToken['token'],
                'url' => EnvVariableHelper::getKbcManageApiUrl(),
                'backoffMaxTries' => 0,
            ]);
        };

        $productionManagerClient = $getClient($this->client);
        $productionManagerClient->addProjectFeature($projectId, $featureName);
        $project = $this->client->getProject($projectId);
        $this->assertProjectHasFeature($featureName, $project['features']);
        $productionManagerClient->removeProjectFeature($projectId, $featureName);

        $this->client->addUserToProject(
            $projectId,
            [
                'email' => $this->normalUser['email'],
                'role' => 'developer',
            ]
        );
        $developerClient = $getClient($this->normalUserClient);
        try {
            $developerClient->addProjectFeature($projectId, $featureName);
            $this->fail('Developer shouldn\'t be able to add project feature');
        } catch (ClientException $e) {
            $this->assertSame(403, $e->getCode());
        }
        $this->client->removeUserFromProject($projectId, $this->normalUser['id']);

        $this->client->addUserToProject(
            $projectId,
            [
                'email' => $this->normalUser['email'],
                'role' => 'reviewer',
            ]
        );
        $reviewerClient = $getClient($this->normalUserClient);
        try {
            $reviewerClient->addProjectFeature($projectId, $featureName);
            $this->fail('Reviewer shouldn\'t be able to add project feature');
        } catch (ClientException $e) {
            $this->assertSame(403, $e->getCode());
        }

        // super admin did create project so he is member remove it
        $this->client->removeUserFromProject($projectId, $this->superAdmin['id']);

        // test is super admin can add feature
        $this->client->addProjectFeature($projectId, $featureName);
        $this->client->removeProjectFeature($projectId, $featureName);
        // super admin joins project test add feature
        $this->client->joinProject($projectId);
        $this->client->addProjectFeature($projectId, $featureName);
        $this->client->removeProjectFeature($projectId, $featureName);
    }

    private function assertProjectHasFeature(string $featureName, array $features): void
    {
        $featureFound = null;
        if (in_array($featureName, $features, true)) {
            $featureFound = $featureName;
        }
        $this->assertNotNull($featureFound);
    }
}
