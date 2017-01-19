<?php
/**
 * Created by PhpStorm.
 * User: martinhalamicek
 * Date: 15/10/15
 * Time: 15:29
 */

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

class OrganizationsTest extends ClientTestCase
{
    public function testListOrganizations()
    {
        $organizations = $this->client->listOrganizations();

        $this->assertGreaterThan(0, count($organizations));

        $organization = $organizations[0];
        $this->assertInternalType('int', $organization['id']);
        $this->assertNotEmpty($organization['name']);
        $this->assertArrayHasKey('maintainer', $organization);
    }

    public function testOrganizationCreateAndDelete()
    {
        $organizations = $this->client->listMaintainerOrganizations($this->testMaintainerId);
        $initialOrgsCount = count($organizations);
        $organization = $this->client->createOrganization($this->testMaintainerId, [
           'name' => 'My org',
        ]);

        $fromList = array_values(array_filter($this->client->listOrganizations(), function($org) use($organization) {
            return $org['id'] === $organization['id'];
        }));
        $this->assertNotEmpty($fromList);
        $this->assertCount(1, $fromList);
        $this->assertEquals($organization['id'], $fromList[0]['id']);

        $projects = $this->client->listOrganizationProjects($organization['id']);
        $this->assertEmpty($projects);

        $organizations = $this->client->listMaintainerOrganizations($this->testMaintainerId);
        $this->assertEquals($initialOrgsCount + 1, count($organizations));

        $this->client->deleteOrganization($organization['id']);
    }

    public function testOrganizationDetail()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Test org',
        ]);

        $org = $this->client->getOrganization($organization['id']);

        $this->assertEquals($org['name'], $organization['name']);
        $this->assertEmpty($org['projects']);
        $this->assertNotEmpty($organization['created']);

        $this->client->deleteOrganization($organization['id']);

        try {
            $org = $this->client->getOrganization($organization['id']);
            $this->fail("Organisation has been deleted, should not exist.");
        } catch (ClientException $e) {
            $this->assertEquals(404, $e->getCode());
        }
    }
    
    public function testUpdateOrganization()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Test org',
        ]);

        $this->assertEquals("Test org", $organization['name']);
        $this->assertEquals(1, (int) $organization['allowAutoJoin']);

        $org = $this->client->updateOrganization($organization['id'], [
            "name" => "new name",
            "allowAutoJoin" => 0
        ]);

        $this->assertEquals("new name", $org['name']);
        $this->assertEquals(0, (int) $org['allowAutoJoin']);
    }

    public function testOrganizationUsers()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Test org',
        ]);
        $admins = $this->client->listOrganizationUsers($organization['id']);
        $this->assertCount(1, $admins);

        $this->client->addUserToOrganization($organization['id'], ['email' => 'spam@keboola.com']);

        $admins = $this->client->listOrganizationUsers($organization['id']);
        $this->assertCount(2, $admins);

        $foundUser = null;
        foreach ($admins as $user) {
            if ($user['email'] == 'spam@keboola.com') {
                $foundUser = $user;
                break;
            }
        }
        if (!$foundUser) {
            $this->fail('User should be in list');
        }

        $this->client->removeUserFromOrganization($organization['id'], $foundUser['id']);

        $admins = $this->client->listProjectUsers($organization['id']);
        $this->assertCount(1, $admins);
    }
}