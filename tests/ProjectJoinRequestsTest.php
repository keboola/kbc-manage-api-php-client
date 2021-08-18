<?php
namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

class ProjectJoinRequestsTest extends ClientTestCase
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

    public function testSuperAdminCanRequestAccessIfAllowAutoJoinIsDisabled(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $this->normalUserClient->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => 0,
        ]);

        $projectId = $this->createProjectWithNormalAdminMember($this->organization['id']);

        $projectUser = $this->findProjectUser($projectId, $this->superAdmin['email']);
        $this->assertNull($projectUser);

        $joinRequests = $this->client->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);

        $joinRequest = $this->client->requestAccessToProject($projectId);
        $joinRequest = $this->client->getProjectJoinRequest($projectId, $joinRequest['id']);

        $this->assertEquals('', $joinRequest['reason']);
        $this->assertEmpty($joinRequest['expires']);
        $this->assertEquals($this->superAdmin['id'], $joinRequest['user']['id']);
        $this->assertEquals($this->superAdmin['email'], $joinRequest['user']['email']);

        $joinRequests = $this->client->listProjectJoinRequests($projectId);
        $this->assertCount(1, $joinRequests);

        $this->assertEquals($joinRequest, reset($joinRequests));

        try {
            $this->client->approveProjectJoinRequest($projectId, $joinRequest['id']);
            $this->fail('Approve join request should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        try {
            $this->client->rejectProjectJoinRequest($projectId, $joinRequest['id']);
            $this->fail('Reject join request should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $joinRequests = $this->client->listProjectJoinRequests($projectId);
        $this->assertCount(1, $joinRequests);

        $this->assertEquals($joinRequest, reset($joinRequests));
    }

    public function testMaintainerAdminCanRequestAccessIfAllowAutoJoinIsDisabled(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $this->client->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => 0,
        ]);

        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNull($projectUser);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);

        $joinRequest = $this->normalUserClient->requestAccessToProject($projectId);
        $joinRequest = $this->normalUserClient->getProjectJoinRequest($projectId, $joinRequest['id']);

        $this->assertEquals('', $joinRequest['reason']);
        $this->assertEmpty($joinRequest['expires']);
        $this->assertEquals($this->normalUser['id'], $joinRequest['user']['id']);
        $this->assertEquals($this->normalUser['email'], $joinRequest['user']['email']);

        $joinRequests = $this->normalUserClient->listProjectJoinRequests($projectId);
        $this->assertCount(1, $joinRequests);

        $this->assertEquals($joinRequest, reset($joinRequests));

        try {
            $this->normalUserClient->approveProjectJoinRequest($projectId, $joinRequest['id']);
            $this->fail('Approve join request should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        try {
            $this->normalUserClient->rejectProjectJoinRequest($projectId, $joinRequest['id']);
            $this->fail('Reject join request should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $joinRequests = $this->normalUserClient->listProjectJoinRequests($projectId);
        $this->assertCount(1, $joinRequests);

        $this->assertEquals($joinRequest, reset($joinRequests));
    }

    /**
     * @dataProvider autoJoinProvider
     * @param bool $allowAutoJoin
     */
    public function testOrganizationAdminCannotRequestAccessRegardlessOfAllowAutoJoin(bool $allowAutoJoin): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $this->normalUserClient->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => $allowAutoJoin,
        ]);

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNull($projectUser);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);

        try {
            $this->normalUserClient->requestAccessToProject($projectId);
            $this->fail('Request access should produce error');
        } catch (ClientException $e) {
            $this->assertContains('use join-project method', $e->getMessage());
            $this->assertEquals(400, $e->getCode());
        }

        $joinRequests = $this->normalUserClient->listProjectJoinRequests($projectId);
        $this->assertCount(0, $joinRequests);
    }

    /**
     * @dataProvider autoJoinProvider
     * @param bool $allowAutoJoin
     */
    public function testRandomAdminCannotRequestAccessRegardlessOfAllowAutoJoin(bool $allowAutoJoin): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $this->client->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => $allowAutoJoin,
        ]);

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNull($projectUser);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);

        try {
            $this->normalUserClient->requestAccessToProject($projectId);
            $this->fail('Request access should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);
    }

    /**
     * @dataProvider autoJoinProvider
     * @param bool $allowAutoJoin
     */
    public function testProjectMemberCannotRequestAccessRegardlessOfAllowAutoJoin(bool $allowAutoJoin): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember($this->organization['id']);

        $this->client->removeUserFromOrganization($this->organization['id'], $this->normalUser['id']);

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNotNull($projectUser);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);

        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $this->client->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => $allowAutoJoin,
        ]);

        try {
            $this->normalUserClient->requestAccessToProject($projectId);
            $this->fail('Request access should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertContains('already', $e->getMessage());
            $this->assertContains('member', $e->getMessage());
        }

        $joinRequests = $this->normalUserClient->listProjectJoinRequests($projectId);
        $this->assertCount(0, $joinRequests);
    }

    public function testSuperAdminCannotRequestAccess(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember($this->organization['id']);

        $projectUser = $this->findProjectUser($projectId, $this->superAdmin['email']);
        $this->assertNull($projectUser);

        $joinRequests = $this->client->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);

        try {
            $this->client->requestAccessToProject($projectId);
            $this->fail('Request access should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
        }

        $joinRequests = $this->client->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);
    }

    public function testMaintainerAdminCannotRequestAccess(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNull($projectUser);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);

        try {
            $this->normalUserClient->requestAccessToProject($projectId);
            $this->fail('Request access should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
        }

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);
    }

    /**
     * @dataProvider autoJoinProvider
     * @param bool $allowAutoJoin
     */
    public function testOrganizationAdminCannotManageJoinRequestsInProjectRegardlessOfAllowAutoJoin(bool $allowAutoJoin): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNull($projectUser);

        $this->client->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => 0,
        ]);

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $joinRequest = $this->normalUserClient->requestAccessToProject($projectId);
        $joinRequest = $this->client->getProjectJoinRequest($projectId, $joinRequest['id']);

        $this->assertEquals('', $joinRequest['reason']);
        $this->assertEmpty($joinRequest['expires']);
        $this->assertEquals($this->normalUser['id'], $joinRequest['user']['id']);
        $this->assertEquals($this->normalUser['email'], $joinRequest['user']['email']);

        $this->client->removeUserFromMaintainer($this->testMaintainerId, $this->normalUser['id']);
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $joinRequests = $this->normalUserClient->listProjectJoinRequests($projectId);
        $this->assertCount(1, $joinRequests);

        $this->assertEquals($joinRequest, reset($joinRequests));

        $this->client->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => $allowAutoJoin,
        ]);

        try {
            $this->normalUserClient->approveProjectJoinRequest($projectId, $joinRequest['id']);
            $this->fail('Approve join request should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        try {
            $this->normalUserClient->rejectProjectJoinRequest($projectId, $joinRequest['id']);
            $this->fail('Reject join request should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $joinRequests = $this->client->listProjectJoinRequests($projectId);
        $this->assertCount(1, $joinRequests);

        $this->assertEquals($joinRequest, reset($joinRequests));
    }

    /**
     * @dataProvider autoJoinProvider
     * @param bool $allowAutoJoin
     */
    public function testMaintainerAdminCannotManageJoinRequestsInProjectRegardlessOfAllowAutoJoin(bool $allowAutoJoin): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNull($projectUser);

        $this->client->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => 0,
        ]);

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $joinRequest = $this->normalUserClient->requestAccessToProject($projectId);
        $joinRequest = $this->client->getProjectJoinRequest($projectId, $joinRequest['id']);

        $this->assertEquals('', $joinRequest['reason']);
        $this->assertEmpty($joinRequest['expires']);
        $this->assertEquals($this->normalUser['id'], $joinRequest['user']['id']);
        $this->assertEquals($this->normalUser['email'], $joinRequest['user']['email']);

        $joinRequests = $this->normalUserClient->listProjectJoinRequests($projectId);
        $this->assertCount(1, $joinRequests);

        $this->assertEquals($joinRequest, reset($joinRequests));

        $this->client->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => $allowAutoJoin,
        ]);

        try {
            $this->normalUserClient->approveProjectJoinRequest($projectId, $joinRequest['id']);
            $this->fail('Approve join request should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        try {
            $this->normalUserClient->rejectProjectJoinRequest($projectId, $joinRequest['id']);
            $this->fail('Reject join request should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $joinRequests = $this->client->listProjectJoinRequests($projectId);
        $this->assertCount(1, $joinRequests);

        $this->assertEquals($joinRequest, reset($joinRequests));
    }

    /**
     * @dataProvider autoJoinProvider
     * @param bool $allowAutoJoin
     */
    public function testSuperAdminCannotManageJoinRequestsInProjectRegardlessOfAllowAutoJoin(bool $allowAutoJoin): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember($this->organization['id']);

        $projectUser = $this->findProjectUser($projectId, $this->superAdmin['email']);
        $this->assertNull($projectUser);

        $this->normalUserClient->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => 0,
        ]);

        $joinRequest = $this->client->requestAccessToProject($projectId);
        $joinRequest = $this->client->getProjectJoinRequest($projectId, $joinRequest['id']);

        $this->assertEquals('', $joinRequest['reason']);
        $this->assertEmpty($joinRequest['expires']);
        $this->assertEquals($this->superAdmin['id'], $joinRequest['user']['id']);
        $this->assertEquals($this->superAdmin['email'], $joinRequest['user']['email']);

        $joinRequests = $this->client->listProjectJoinRequests($projectId);
        $this->assertCount(1, $joinRequests);

        $this->assertEquals($joinRequest, reset($joinRequests));

        $this->normalUserClient->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => $allowAutoJoin,
        ]);

        try {
            $this->client->approveProjectJoinRequest($projectId, $joinRequest['id']);
            $this->fail('Approve join request should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        try {
            $this->client->rejectProjectJoinRequest($projectId, $joinRequest['id']);
            $this->fail('Reject join request should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $joinRequests = $this->client->listProjectJoinRequests($projectId);
        $this->assertCount(1, $joinRequests);

        $this->assertEquals($joinRequest, reset($joinRequests));
    }

    /**
     * @dataProvider autoJoinProvider
     * @param bool $allowAutoJoin
     */
    public function testRandomAdminCannotManageJoinRequestsInProjectRegardlessOfAllowAutoJoin(bool $allowAutoJoin): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $projectUser = $this->findProjectUser($projectId, $this->normalUser['email']);
        $this->assertNull($projectUser);

        $this->client->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => 0,
        ]);

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $joinRequest = $this->normalUserClient->requestAccessToProject($projectId);
        $joinRequest = $this->client->getProjectJoinRequest($projectId, $joinRequest['id']);

        $this->assertEquals('', $joinRequest['reason']);
        $this->assertEmpty($joinRequest['expires']);
        $this->assertEquals($this->normalUser['id'], $joinRequest['user']['id']);
        $this->assertEquals($this->normalUser['email'], $joinRequest['user']['email']);

        $this->client->removeUserFromMaintainer($this->testMaintainerId, $this->normalUser['id']);

        $joinRequests = $this->client->listProjectJoinRequests($projectId);
        $this->assertCount(1, $joinRequests);

        $this->assertEquals($joinRequest, reset($joinRequests));

        $this->client->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => $allowAutoJoin,
        ]);

        try {
            $this->normalUserClient->listProjectJoinRequests($projectId);
            $this->fail('List join requests should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        try {
            $this->normalUserClient->approveProjectJoinRequest($projectId, $joinRequest['id']);
            $this->fail('Approve join request should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        try {
            $this->normalUserClient->rejectProjectJoinRequest($projectId, $joinRequest['id']);
            $this->fail('Reject join request should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $joinRequests = $this->client->listProjectJoinRequests($projectId);
        $this->assertCount(1, $joinRequests);

        $this->assertEquals($joinRequest, reset($joinRequests));
    }

    public function testProjectMemberCanApproveJoinRequest()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember($this->organization['id']);

        $this->normalUserClient->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => 0,
        ]);

        $this->client->removeUserFromOrganization($this->organization['id'], $this->normalUser['id']);

        $joinRequest = $this->client->requestAccessToProject(
            $projectId,
            [
                'reason' => 'Testing reason propagation',
                'expirationSeconds' => 3600,
            ]
        );

        $joinRequests = $this->client->listMyProjectJoinRequests();
        $this->assertCount(1, $joinRequests);

        $joinRequest = $this->normalUserClient->getProjectJoinRequest($projectId, $joinRequest['id']);

        $this->assertEquals('Testing reason propagation', $joinRequest['reason']);
        $this->assertNotEmpty($joinRequest['expires']);
        $this->assertEquals($this->superAdmin['id'], $joinRequest['user']['id']);
        $this->assertEquals($this->superAdmin['email'], $joinRequest['user']['email']);

        $joinRequests = $this->normalUserClient->listProjectJoinRequests($projectId);
        $this->assertCount(1, $joinRequests);

        $this->assertEquals($joinRequest, reset($joinRequests));

        $projectUser = $this->findProjectUser($projectId, $this->superAdmin['email']);
        $this->assertNull($projectUser);

        $this->normalUserClient->approveProjectJoinRequest($projectId, $joinRequest['id']);

        $projectUser = $this->findProjectUser($projectId, $this->superAdmin['email']);
        $this->assertNotNull($projectUser);

        $this->assertArrayHasKey('approver', $projectUser);

        $this->assertEquals($this->normalUser['id'], $projectUser['approver']['id']);
        $this->assertEquals($this->normalUser['email'], $projectUser['approver']['email']);
        $this->assertEquals($this->normalUser['name'], $projectUser['approver']['name']);
        $this->assertEquals($projectUser['reason'], $joinRequest['reason']);
        $this->assertEquals($projectUser['expires'], $joinRequest['expires']);
        $this->assertNotEmpty($projectUser['expires']);

        $joinRequests = $this->normalUserClient->listProjectJoinRequests($projectId);
        $this->assertCount(0, $joinRequests);
    }

    public function testProjectMemberCanRejectJoinRequest()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember($this->organization['id']);

        $this->normalUserClient->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => 0,
        ]);

        $this->client->removeUserFromOrganization($this->organization['id'], $this->normalUser['id']);

        $joinRequest = $this->client->requestAccessToProject($projectId);

        $joinRequest = $this->normalUserClient->getProjectJoinRequest($projectId, $joinRequest['id']);

        $this->assertEquals('', $joinRequest['reason']);
        $this->assertEmpty($joinRequest['expires']);
        $this->assertEquals($this->superAdmin['id'], $joinRequest['user']['id']);
        $this->assertEquals($this->superAdmin['email'], $joinRequest['user']['email']);

        $joinRequests = $this->normalUserClient->listProjectJoinRequests($projectId);
        $this->assertCount(1, $joinRequests);

        $this->assertEquals($joinRequest, reset($joinRequests));

        $projectUser = $this->findProjectUser($projectId, $this->superAdmin['email']);
        $this->assertNull($projectUser);

        $this->normalUserClient->rejectProjectJoinRequest($projectId, $joinRequest['id']);

        $projectUser = $this->findProjectUser($projectId, $this->superAdmin['email']);
        $this->assertNull($projectUser);

        $joinRequests = $this->normalUserClient->listProjectJoinRequests($projectId);
        $this->assertCount(0, $joinRequests);
    }

    public function testDeletingProjectRemovesJoinRequests()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $this->client->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => 0,
        ]);

        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);

        $this->normalUserClient->requestAccessToProject($projectId);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(1, $joinRequests);

        $this->client->deleteProject($projectId);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);

        $this->client->undeleteProject($projectId);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);
    }

    public function testJoinRequestExpiration()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $this->client->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => 0,
        ]);

        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);

        $this->normalUserClient->requestAccessToProject($projectId, [
            'expirationSeconds' => 20,
        ]);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(1, $joinRequests);

        // the next time the cron runs the invitation should be removed.
        sleep(120);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);
    }

    public function testAdminCanCancelHisOwnJoinRequest()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $this->client->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => 0,
        ]);

        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);
        $project = $this->client->getProject($projectId);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);

        $joinRequest = $this->normalUserClient->requestAccessToProject($projectId);

        $this->assertEquals('', $joinRequest['reason']);
        $this->assertEmpty($joinRequest['expires']);
        $this->assertEquals($project['id'], $joinRequest['project']['id']);
        $this->assertEquals($project['name'], $joinRequest['project']['name']);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(1, $joinRequests);

        $this->assertEquals($joinRequest, reset($joinRequests));

        $this->assertEquals($joinRequest, $this->normalUserClient->getMyProjectJoinRequest($joinRequest['id']));

        $this->normalUserClient->deleteMyProjectJoinRequest($joinRequest['id']);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);
    }

    public function testCannotRequestAccessTwoTimes()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $this->client->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => 0,
        ]);

        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $this->normalUserClient->requestAccessToProject($projectId);

        $joinRequests = $this->client->listProjectJoinRequests($projectId);
        $this->assertCount(1, $joinRequests);

        try {
            $this->normalUserClient->requestAccessToProject($projectId);
            $this->fail('Request access to project twice should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertContains('already', $e->getMessage());
            $this->assertContains('sent', $e->getMessage());
        }

        $joinRequests = $this->client->listProjectJoinRequests($projectId);
        $this->assertCount(1, $joinRequests);
    }

    public function testInvitedAdminCannotRequestAccess()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $this->client->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => 0,
        ]);

        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $this->client->inviteUserToProject($projectId, ['email' => $this->normalUser['email']]);

        try {
            $this->normalUserClient->requestAccessToProject($projectId);
            $this->fail('Request access of invited user should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertContains('You have been already invited', $e->getMessage());
        }

        $joinRequests = $this->client->listProjectJoinRequests($projectId);
        $this->assertCount(0, $joinRequests);
    }
}
