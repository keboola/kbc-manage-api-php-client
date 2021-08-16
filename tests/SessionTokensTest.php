<?php

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

class SessionTokensTest extends ClientTestCase
{
    /** @var array */
    private $sessionToken;

    /** @var \Keboola\ManageApi\Client */
    private $sessionTokenClient;

    public function setUp()
    {
        parent::setUp();

        $this->sessionToken = $this->normalUserClient->createSessionToken();

        $this->sessionTokenClient = $this->getClient([
            'token' => $this->sessionToken['token'],
            'url' => getenv('KBC_MANAGE_API_URL'),
            'backoffMaxTries' => 0,
        ]);
    }

    public function testSessionTokenProperties()
    {
        $this->assertEquals($this->normalUser['id'], $this->sessionToken['user']['id']);
        $this->assertEquals($this->normalUser['email'], $this->sessionToken['user']['email']);

        $this->assertEquals('session', $this->sessionToken['type']);
        $this->assertTrue($this->sessionToken['isSessionToken']);
        $this->assertTrue(strtotime($this->sessionToken['expires']) <= strtotime($this->sessionToken['created']) + 3600);
    }

    public function testMaintainersManipulation()
    {
        // get maintainers
        $maintainers = $this->sessionTokenClient->listMaintainers();
        $this->assertGreaterThanOrEqual(0, count($maintainers));

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        // get maintainers (after adding)
        $maintainersAfterAdding = $this->sessionTokenClient->listMaintainers();
        $this->assertGreaterThanOrEqual(1, count($maintainersAfterAdding));

        // get maintainer organizations
        $organizations = $this->sessionTokenClient->listMaintainerOrganizations($this->testMaintainerId);
        $this->assertGreaterThanOrEqual(0, count($organizations));

        $organization = $this->normalUserClient->createOrganization($this->testMaintainerId, [
            'name' => 'Session Tokens - maintainer',
        ]);
        $this->assertEquals('Session Tokens - maintainer', $organization['name']);

        // get maintainer organizations (after adding)
        $organizationsAfterAdding = $this->sessionTokenClient->listMaintainerOrganizations($this->testMaintainerId);
        $this->assertGreaterThanOrEqual(1, count($organizationsAfterAdding));

        // create maintainer
        try {
            $this->sessionTokenClient->createMaintainer(['name' => 'maintainer create fail']);
            $this->fail('User authorized with session token should not be able to create maintainer');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }
    }

    public function testOrganizationsManipulation()
    {
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $organization = $this->normalUserClient->createOrganization($this->testMaintainerId, [
            'name' => 'Session Tokens - organization',
        ]);
        $this->assertEquals('Session Tokens - organization', $organization['name']);

        // get organizations
        $organizations = $this->sessionTokenClient->listOrganizations();
        $this->assertGreaterThanOrEqual(1, count($organizations));

        // create organization
        try {
            $this->sessionTokenClient->createOrganization($this->testMaintainerId, ['name' => 'org create fail']);
            $this->fail('User authorized with session token should not be able to create organization');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }
    }

    public function testProjectManipulation()
    {
        $organization1 = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Session Tokens - project manipulation 1',
        ]);
        $organization2 = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Session Tokens - project manipulation 2',
        ]);
        $project = $this->client->createProject($organization1['id'], ['name' => 'Test project']);
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);
        $this->client->addUserToProject($project['id'], ['email' => $this->normalUser['email']]);

        // get project detail
        $projectDetail = $this->sessionTokenClient->getProject($project['id']);
        $this->assertEquals('Test project', $projectDetail['name']);

        // update project
        $updatedProject = $this->sessionTokenClient->updateProject($project['id'], [
            'name' => 'Test project 1',
        ]);
        $this->assertEquals('Test project 1', $updatedProject['name']);

        // update project organization
        $projectWithChangedOrg = $this->sessionTokenClient->changeProjectOrganization($project['id'], $organization2['id']);
        $this->assertEquals($organization2['id'], $projectWithChangedOrg['organization']['id']);

        // delete project
        $this->sessionTokenClient->deleteProject($project['id']);

        try {
            $this->sessionTokenClient->getProject($project['id']);
            $this->fail('Retrieve of deleted project should throw 404');
        } catch (ClientException $e) {
            $this->assertEquals(404, $e->getCode());
        }
    }

    public function testProjectUsersManipulation()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Session Tokens - users manipulation',
        ]);
        $project = $this->client->createProject($organization['id'], ['name' => 'Test project']);
        $this->client->addUserToProject($project['id'], ['email' => $this->normalUser['email']]);

        // list project users
        $projectUsers = $this->sessionTokenClient->listProjectUsers($project['id']);
        $this->assertEquals(2, count($projectUsers));

        // delete project user
        $this->sessionTokenClient->removeUserFromProject($project['id'], $this->superAdmin['id']);
        $projectUsers = $this->sessionTokenClient->listProjectUsers($project['id']);
        $this->assertEquals(1, count($projectUsers));

        // add user to project
        try {
            $email = 'devel-tests+remove' . uniqid() . '@keboola.com';
            $this->sessionTokenClient->addUserToProject($project['id'], ['email' => $email]);
            $this->fail('User authorized with session token should not have permissions to add user to project');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }
    }

    public function testProjectInvitationsManipulation()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Session Tokens - invitations manipulation',
        ]);
        $project = $this->client->createProject($organization['id'], ['name' => 'Test project']);
        $this->client->addUserToProject($project['id'], ['email' => $this->normalUser['email']]);

        // invite user to project via session token
        $inviteEmail = 'devel-tests+remove' . uniqid() . '@keboola.com';
        $this->sessionTokenClient->inviteUserToProject($project['id'], ['email' => $inviteEmail]);

        // list invitations via session token
        $invitationsAfterInvite = $this->sessionTokenClient->listProjectInvitations($project['id']);
        $this->assertEquals(1, count($invitationsAfterInvite));

        // delete invitation via session token
        $this->sessionTokenClient->cancelProjectInvitation($project['id'], $invitationsAfterInvite[0]['id']);
        $invitationsAfterCancel = $this->sessionTokenClient->listProjectInvitations($project['id']);
        $this->assertEquals(0, count($invitationsAfterCancel));
    }

    public function testProjectJoinRequestsManipulation()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Session Tokens - join requests manipulation',
        ]);
        $this->client->updateOrganization($organization['id'], [
            'allowAutoJoin' => 0,
        ]);
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUserWithMfa['email']]);
        $project = $this->client->createProject($organization['id'], ['name' => 'Test project']);
        $this->client->addUserToProject($project['id'], ['email' => $this->normalUser['email']]);

        $this->normalUserWithMfaClient->requestAccessToProject($project['id']);

        // list join requests
        $joinRequests = $this->sessionTokenClient->listProjectJoinRequests($project['id']);
        $this->assertEquals(1, count($joinRequests));

        // approve join request
        $this->sessionTokenClient->approveProjectJoinRequest($project['id'], $joinRequests[0]['id']);

        // list join requests
        $joinRequestsAfterApproval = $this->sessionTokenClient->listProjectJoinRequests($project['id']);
        $this->assertEquals(0, count($joinRequestsAfterApproval));

        $this->client->removeUserFromProject($project['id'], $this->normalUserWithMfa['id']);
        $this->normalUserWithMfaClient->requestAccessToProject($project['id']);

        // list join requests
        $joinRequestsAfter2ndRequest = $this->sessionTokenClient->listProjectJoinRequests($project['id']);
        $this->assertEquals(1, count($joinRequestsAfter2ndRequest));

        // reject join request
        $this->sessionTokenClient->rejectProjectJoinRequest($project['id'], $joinRequestsAfter2ndRequest[0]['id']);

        // list join requests
        $joinRequestsAfterRejection = $this->sessionTokenClient->listProjectJoinRequests($project['id']);
        $this->assertEquals(0, count($joinRequestsAfterRejection));
    }
}
