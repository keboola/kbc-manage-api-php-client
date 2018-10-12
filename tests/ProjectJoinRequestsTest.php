<?php
namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

class ProjectJoinRequestsTest extends ClientTestCase
{
    private $project;

    private $organization;

    public function setUp()
    {
        parent::setUp();

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $this->client->addUserToOrganization($organization['id'], ['email' => $this->normalUser['email']]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
        ]);

        $this->organization = $organization;
        $this->project = $project;

        foreach ($this->normalUserClient->listMyProjectJoinRequests() as $joinRequest) {
            $this->normalUserClient->deleteMyProjectJoinRequest($joinRequest['id']);
        }
    }

    public function testRequestAccess()
    {
        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);

        $joinRequest = $this->normalUserClient->requestAccessToProject($this->project['id']);

        $this->assertEquals('', $joinRequest['reason']);
        $this->assertEmpty($joinRequest['expires']);
        $this->assertEquals($this->project['id'], $joinRequest['project']['id']);
        $this->assertEquals($this->project['name'], $joinRequest['project']['name']);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(1, $joinRequests);

        $this->assertEquals($joinRequest, reset($joinRequests));

        $this->assertEquals($joinRequest, $this->normalUserClient->getMyProjectJoinRequest($joinRequest['id']));

        $this->normalUserClient->deleteMyProjectJoinRequest($joinRequest['id']);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);
    }

    public function testCreateJoinRequestError()
    {
        $joinRequest = $this->normalUserClient->requestAccessToProject($this->project['id']);

        try {
            $this->normalUserClient->requestAccessToProject($this->project['id']);
            $this->fail('Request access to project twice should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertContains('already', $e->getMessage());
            $this->assertContains('sent', $e->getMessage());
        }

        $this->client->addUserToProject($this->project['id'], ['email' => $this->normalUser['email']]);

        try {
            $this->normalUserClient->requestAccessToProject($this->project['id']);
            $this->fail('Request access of member should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertContains('already', $e->getMessage());
            $this->assertContains('member', $e->getMessage());
        }

        $this->normalUserClient->deleteMyProjectJoinRequest($joinRequest['id']);
        $this->client->removeUserFromOrganization($this->organization['id'], $this->normalUser['id']);
        $this->client->removeUserFromProject($this->project['id'], $this->normalUser['id']);

        try {
            $this->normalUserClient->requestAccessToProject($this->project['id']);
            $this->fail('Request access to project for non-admin should produce error');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }
    }

    public function testApproveJoinRequest()
    {
        $projectUsers = $this->client->listProjectUsers($this->project['id']);
        $this->assertCount(1, $projectUsers);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);

        $joinRequest = $this->normalUserClient->requestAccessToProject($this->project['id']);
        $joinRequest = $this->client->getProjectJoinRequest($this->project['id'], $joinRequest['id']);

        $this->assertEquals('', $joinRequest['reason']);
        $this->assertEmpty($joinRequest['expires']);
        $this->assertEquals($this->normalUser['id'], $joinRequest['user']['id']);
        $this->assertEquals($this->normalUser['email'], $joinRequest['user']['email']);

        $joinRequests = $this->client->listProjectJoinRequests($this->project['id']);
        $this->assertCount(1, $joinRequests);

        $this->assertEquals($joinRequest, reset($joinRequests));

        $this->client->approveProjectJoinRequest($this->project['id'], $joinRequest['id']);

        $projectUsers = $this->client->listProjectUsers($this->project['id']);
        $this->assertCount(2, $projectUsers);

        $admin = null;
        foreach ($projectUsers as $projectUser) {
            if ($projectUser['id'] === $this->normalUser['id']) {
                $admin = $projectUser;
            }
        }

        $this->assertNotNull($admin);

        $joinRequests = $this->client->listProjectJoinRequests($this->project['id']);
        $this->assertCount(0, $joinRequests);
    }

    public function testRejectJoinRequest()
    {
        $projectUsers = $this->client->listProjectUsers($this->project['id']);
        $this->assertCount(1, $projectUsers);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);

        $joinRequest = $this->normalUserClient->requestAccessToProject($this->project['id']);
        $joinRequest = $this->client->getProjectJoinRequest($this->project['id'], $joinRequest['id']);

        $this->assertEquals('', $joinRequest['reason']);
        $this->assertEmpty($joinRequest['expires']);
        $this->assertEquals($this->normalUser['id'], $joinRequest['user']['id']);
        $this->assertEquals($this->normalUser['email'], $joinRequest['user']['email']);

        $joinRequests = $this->client->listProjectJoinRequests($this->project['id']);
        $this->assertCount(1, $joinRequests);

        $this->assertEquals($joinRequest, reset($joinRequests));

        $this->client->rejectProjectJoinRequest($this->project['id'], $joinRequest['id']);

        $projectUsers = $this->client->listProjectUsers($this->project['id']);
        $this->assertCount(1, $projectUsers);

        $joinRequests = $this->client->listProjectJoinRequests($this->project['id']);
        $this->assertCount(0, $joinRequests);
    }

    public function testManageJoinRequestError()
    {
        $joinRequest = $this->normalUserClient->requestAccessToProject($this->project['id']);

        try {
            $this->normalUserClient->listProjectJoinRequests($this->project['id']);
            $this->fail('Join Request cannot be managed with non-project admin');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        try {
            $this->normalUserClient->rejectProjectJoinRequest($this->project['id'], $joinRequest['id']);
            $this->fail('Join Request cannot be managed with non-project admin');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        try {
            $this->normalUserClient->approveProjectJoinRequest($this->project['id'], $joinRequest['id']);
            $this->fail('Join Request cannot be managed with non-project admin');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

       $this->assertEquals($joinRequest, $this->normalUserClient->getMyProjectJoinRequest($joinRequest['id']));
    }

    public function testReasonAndExpiresPropagation()
    {
        $joinRequest = $this->normalUserClient->requestAccessToProject($this->project['id'], [
            'reason' => 'Testing reason propagation',
            'expirationSeconds' => 3600
        ]);

        $this->assertEquals('Testing reason propagation', $joinRequest['reason']);
        $this->assertNotEmpty($joinRequest['expires']);

        $this->client->approveProjectJoinRequest($this->project['id'], $joinRequest['id']);

        $admin = null;
        foreach ($this->client->listProjectUsers($this->project['id']) as $projectUser) {
            if ($projectUser['id'] === $this->normalUser['id']) {
                $admin = $projectUser;
            }
        }

        $this->assertNotNull($admin);

        $this->assertEquals($admin['reason'], $joinRequest['reason']);
        $this->assertNotEmpty($admin['expires']);
    }

    public function testProjectDeleteRemovesJoinRequests()
    {
        $this->normalUserClient->requestAccessToProject($this->project['id']);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(1, $joinRequests);

        $this->client->deleteProject($this->project['id']);

        $invitations = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $invitations);

        $this->client->undeleteProject($this->project['id']);

        $invitations = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $invitations);
    }
}