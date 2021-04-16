<?php
namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

class ProjectJoinTest extends ParallelClientTestCase
{
    private $organization;

    /**
     * Create empty organization without admins, remove admins from test maintainer and delete all their join requests
     */
    public function setUp()
    {
        parent::setUp();

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => 'spam+spam@keboola.com']);

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

        $this->client->addUserToOrganization($this->organization['id'], ['email' => 'spam@keboola.com']);
        $this->client->removeUserFromOrganization($this->organization['id'], $this->superAdmin['id']);

        foreach ($this->normalUserClient->listMyProjectJoinRequests() as $joinRequest) {
            $this->normalUserClient->deleteMyProjectJoinRequest($joinRequest['id']);
        }

        foreach ($this->client->listMyProjectJoinRequests() as $joinRequest) {
            $this->client->deleteMyProjectJoinRequest($joinRequest['id']);
        }
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

    public function testSuperAdminCanJoinProject(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember($this->organization['id']);

        $projectUser = $this->findProjectUser($projectId, $this->superAdmin['email']);
        $this->assertNull($projectUser);

        $this->client->joinProject($projectId);

        $projectUser = $this->findProjectUser($projectId, $this->superAdmin['email']);
        $this->assertNotNull($projectUser);

        $this->assertArrayHasKey('approver', $projectUser);

        $this->assertEquals($this->superAdmin['id'], $projectUser['approver']['id']);
        $this->assertEquals($this->superAdmin['email'], $projectUser['approver']['email']);
        $this->assertEquals($this->superAdmin['name'], $projectUser['approver']['name']);
    }

    public function testSuperAdminCanCreateStorageToken(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember($this->organization['id']);

        $projectUser = $this->findProjectUser($projectId, $this->superAdmin['email']);
        $this->assertNull($projectUser);

        $token = $this->client->createProjectStorageToken($projectId, [
            'description' => 'test',
            'expiresIn' => 60,
        ]);

        $this->assertArrayHasKey('token', $token);
    }

    public function testMaintainerAdminCanJoinProject(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNull($projectUser);

        $this->normalUserClient->joinProject($projectId);

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNotNull($projectUser);
        $this->assertArrayHasKey('approver', $projectUser);

        $this->assertEquals($this->normalUser['id'], $projectUser['approver']['id']);
        $this->assertEquals($this->normalUser['email'], $projectUser['approver']['email']);
        $this->assertEquals($this->normalUser['name'], $projectUser['approver']['name']);
    }

    public function testMaintainerAdminCanCreateStorageToken(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNull($projectUser);

        $token = $this->normalUserClient->createProjectStorageToken($projectId, [
            'description' => 'test',
            'expiresIn' => 60,
        ]);

        $this->assertArrayHasKey('token', $token);
    }

    public function testSuperAdminCannotJoinProjectIfAllowAutoJoinIsDisabled(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember($this->organization['id']);

        $this->normalUserClient->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => 0,
        ]);

        $projectUser = $this->findProjectUser($projectId, $this->superAdmin['email']);
        $this->assertNull($projectUser);

        try {
            $this->client->joinProject($projectId);
            $this->fail('Project join should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $projectUser = $this->findProjectUser($projectId, $this->superAdmin['email']);
        $this->assertNull($projectUser);
    }

    public function testSuperAdminCannotCreateStorageTokenIfAllowAutoJoinIsDisabled(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember($this->organization['id']);

        $this->normalUserClient->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => 0,
        ]);

        $projectUser = $this->findProjectUser($projectId, $this->superAdmin['email']);
        $this->assertNull($projectUser);

        try {
            $this->client->createProjectStorageToken($projectId, [
                'description' => 'test',
                'expiresIn' => 60,
            ]);

            $this->fail('Project token create should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
            $this->assertContains('You don\'t have access to project', $e->getMessage());
        }
    }

    public function testMaintainerAdminCannotJoinProjectIfAllowAutoJoinIsDisabled(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $this->client->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => 0,
        ]);

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNull($projectUser);

        try {
            $this->normalUserClient->joinProject($projectId);
            $this->fail('Project join should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNull($projectUser);
    }

    public function testMaintainerAdminCannotCreateStorageTokenIfAllowAutoJoinIsDisabled(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $this->client->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => 0,
        ]);

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNull($projectUser);

        try {
            $this->normalUserClient->createProjectStorageToken($projectId, [
                'description' => 'test',
                'expiresIn' => 60,
            ]);

            $this->fail('Project token create should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
            $this->assertContains('You don\'t have access to project', $e->getMessage());
        }
    }

    /**
     * @dataProvider autoJoinProvider
     * @param bool $allowAutoJoin
     */
    public function testOrganizationAdminCanJoinProjectRegardlessOfAllowAutoJoin(bool $allowAutoJoin): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $this->normalUserClient->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => $allowAutoJoin,
        ]);

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNull($projectUser);

        $this->normalUserClient->joinProject($projectId);

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNotNull($projectUser);
        $this->assertArrayHasKey('approver', $projectUser);

        $this->assertEquals($this->normalUser['id'], $projectUser['approver']['id']);
        $this->assertEquals($this->normalUser['email'], $projectUser['approver']['email']);
        $this->assertEquals($this->normalUser['name'], $projectUser['approver']['name']);
    }

    /**
     * @dataProvider autoJoinProvider
     * @param bool $allowAutoJoin
     */
    public function testOrganizationAdminCanCreateStorageTokenRegardlessOfAllowAutoJoin(bool $allowAutoJoin): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $this->normalUserClient->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => $allowAutoJoin,
        ]);

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNull($projectUser);

        $token = $this->normalUserClient->createProjectStorageToken($projectId, [
            'description' => 'test',
            'expiresIn' => 60,
        ]);

        $this->assertArrayHasKey('token', $token);
    }

    public function testOrganizationAdminJoiningProjectDeletesCorrespondingJoinRequest(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $this->client->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => 0,
        ]);

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);

        $this->normalUserClient->requestAccessToProject($projectId);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(1, $joinRequests);

        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $this->normalUserClient->joinProject($projectId);

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNotNull($projectUser);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);
    }

    public function testOrganizationAdminJoiningProjectDeletesCorrespondingInvitation(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $this->client->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => 0,
        ]);

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);

        $this->client->inviteUserToProject($projectId, ['email' => $this->normalUser['email']]);

        $joinRequests = $this->client->listProjectInvitations($projectId);
        $this->assertCount(1, $joinRequests);

        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $this->normalUserClient->joinProject($projectId);

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNotNull($projectUser);

        $joinRequests = $this->client->listProjectInvitations($projectId);
        $this->assertCount(0, $joinRequests);
    }

    /**
     * @dataProvider autoJoinProvider
     * @param bool $allowAutoJoin
     */
    public function testRandomAdminCannotJoinProjectRegardlessOfAllowAutoJoin(bool $allowAutoJoin): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $this->client->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => $allowAutoJoin,
        ]);

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNull($projectUser);

        try {
            $this->normalUserClient->joinProject($projectId);
            $this->fail('Project join should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNull($projectUser);
    }

    /**
     * @dataProvider autoJoinProvider
     * @param bool $allowAutoJoin
     */
    public function testRandomAdminCannotCreateStorageTokenRegardlessOfAllowAutoJoin(bool $allowAutoJoin): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $this->client->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => $allowAutoJoin,
        ]);

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNull($projectUser);

        try {
            $this->normalUserClient->createProjectStorageToken($projectId, [
                'description' => 'test',
                'expiresIn' => 60,
            ]);

            $this->fail('Project token create should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
            $this->assertContains('You don\'t have access to project', $e->getMessage());
        }
    }

    /**
     * @dataProvider autoJoinProvider
     * @param bool $allowAutoJoin
     */
    public function testProjectMemberCannotJoinProjectAgainRegardlessOfAllowAutoJoin(bool $allowAutoJoin): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember($this->organization['id']);

        $this->normalUserClient->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => $allowAutoJoin,
        ]);

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNotNull($projectUser);

        try {
            $this->normalUserClient->joinProject($projectId);
            $this->fail('Project join should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
        }
    }

    /**
     * @dataProvider autoJoinProvider
     * @param bool $allowAutoJoin
     */
    public function testProjectMemberCanCreateStorageTokenRegardlessOfAllowAutoJoin(bool $allowAutoJoin): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember($this->organization['id']);

        $this->normalUserClient->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => $allowAutoJoin,
        ]);

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNotNull($projectUser);

        $token = $this->normalUserClient->createProjectStorageToken($projectId, [
            'description' => 'test',
            'expiresIn' => 60,
        ]);

        $this->assertArrayHasKey('token', $token);
    }

    /**
     * @dataProvider autoJoinProvider
     * @param bool $allowAutoJoin
     */
    public function testMaintainerAdminCannotAddSelfToProjectRegardlessOfAllowAutoJoin(bool $allowAutoJoin): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);

        $this->client->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => $allowAutoJoin,
        ]);

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $testProject = $this->client->createProject($this->organization['id'], ['name' => 'Test Project']);

        $projectUser = $this->findProjectUser($testProject['id'], $this->normalUser['email']);
        $this->assertNull($projectUser);

        try {
            $this->normalUserClient->addUserToProject($testProject['id'], [
                'email' => $this->normalUser['email'],
            ]);
            $this->fail('Project join should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $projectUser = $this->findProjectUser($testProject['id'], $this->normalUser['email']);
        $this->assertNull($projectUser);
    }

    /**
     * @dataProvider autoJoinProvider
     * @param bool $allowAutoJoin
     */
    public function testRandomAdminCannotAddSelfToProjectRegardlessOfAllowAutoJoin(bool $allowAutoJoin): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);

        $this->client->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => $allowAutoJoin,
        ]);

        $testProject = $this->client->createProject($this->organization['id'], ['name' => 'Test Project']);

        $projectUser = $this->findProjectUser($testProject['id'], $this->normalUser['email']);
        $this->assertNull($projectUser);

        try {
            $this->normalUserClient->addUserToProject($testProject['id'], [
                'email' => $this->normalUser['email'],
            ]);
            $this->fail('Project join should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $projectUser = $this->findProjectUser($testProject['id'], $this->normalUser['email']);
        $this->assertNull($projectUser);
    }

    /**
     * @dataProvider autoJoinProvider
     * @param bool $allowAutoJoin
     */
    public function testSuperAdminCannotAddSelfToProjectRegardlessOfAllowAutoJoin(bool $allowAutoJoin): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $this->normalUserClient->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => $allowAutoJoin,
        ]);

        $testProject = $this->normalUserClient->createProject($this->organization['id'], [
            'name' => 'Test Project',
        ]);

        $projectUser = $this->findProjectUser($testProject['id'], $this->superAdmin['email']);
        $this->assertNull($projectUser);

        try {
            $this->client->addUserToProject($testProject['id'], ['email' => $this->superAdmin['email']]);
            $this->fail('Project join should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $projectUser = $this->findProjectUser($testProject['id'], $this->superAdmin['email']);
        $this->assertNull($projectUser);
    }
}
