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
}