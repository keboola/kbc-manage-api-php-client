<?php
/**
 * Created by PhpStorm.
 * User: martinhalamicek
 * Date: 15/10/15
 * Time: 15:29
 */

namespace Keboola\ManageApiTest;

class ProjectsTest extends ClientTestCase
{

    public function testProjectCreate()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
           'name' => 'My test',
        ]);
        $this->assertArrayHasKey('id', $project);
        $this->assertEquals('My test', $project['name']);
    }

}