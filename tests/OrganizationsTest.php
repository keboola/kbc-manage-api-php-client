<?php
/**
 * Created by PhpStorm.
 * User: martinhalamicek
 * Date: 15/10/15
 * Time: 15:29
 */

namespace Keboola\ManageApiTest;

class OrganizationsTest extends ClientTestCase
{

    public function testListMaintainers()
    {
        $organizations = $this->client->listOrganizations();

        $this->assertGreaterThan(0, count($organizations));

        $organization = $organizations[0];
        $this->assertInternalType('int', $organization['id']);
        $this->assertNotEmpty($organization['name']);
        $this->assertNotEmpty($organization['created']);
        $this->assertArrayHasKey('maintainer', $organization);
    }

    public function testOrganizationCreateAndDelete()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
           'name' => 'My org',
        ]);

        $fromList = array_values(array_filter($this->client->listOrganizations(), function($org) use($organization) {
            return $org['id'] === $organization['id'];
        }));
        $this->assertNotEmpty($fromList);
        $this->assertCount(1, $fromList);
        $this->assertEquals($organization['id'], $fromList[0]['id']);

        $this->client->deleteOrganization($organization['id']);
    }

    public function testOrganizationDelete()
    {

    }
}