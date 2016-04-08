<?php
/**
 * Created by PhpStorm.
 * User: martinhalamicek
 * Date: 15/10/15
 * Time: 15:29
 */

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;
use Keboola\StorageApi\Client;

class StorageBackendTest extends ClientTestCase
{

    public function testStorageBackendCreate()
    {
        $this->markTestSkipped('just for development');
        $backend = $this->client->createStorageBackend([
            'region' => 'us-east-1',
            'backend' => 'mysql',
            'host' => 'rds-devel-a.c97npkkbezqf.eu-west-1.rds.amazonaws.com',
            'username' => '',
            'password' => '',
        ]);
    }

    public function testStorageAssignBackend()
    {
        $this->markTestSkipped('just for development');
        $name = 'My org';
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => $name,
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
        ]);

        $this->assertArrayHasKey('backends', $project);

        $backends = $project['backends'];

        $this->assertArrayHasKey('mysql', $backends);
        $this->assertArrayNotHasKey('redshift', $backends);
        
        $this->client->assignProjectStorageBackend($project['id'], 6);

        $project = $this->client->getProject($project['id']);
        $backends = $project['backends'];

        $this->assertArrayHasKey('redshift', $backends);

        $redshift = $backends['redshift'];
        $this->assertEquals(6, $redshift['id']);

        $this->client->assignProjectStorageBackend($project['id'], 4);
    }

    public function testStorageBackendList()
    {
        $backends = $this->client->listStorageBackend();

        $this->assertNotEmpty($backends);

        $backend = reset($backends);
        $this->assertInternalType('int', $backend['id']);
        $this->assertArrayHasKey('host', $backend);
        $this->assertArrayHasKey('backend', $backend);
        $this->assertArrayNotHasKey('login', $backend);
    }

    public function testStorageBackendListWithLogins()
    {
        $backends = $this->client->listStorageBackend([
            'logins' => true,
        ]);

        $this->assertNotEmpty($backends);

        $backend = reset($backends);
        $this->assertInternalType('int', $backend['id']);
        $this->assertArrayHasKey('host', $backend);
        $this->assertArrayHasKey('backend', $backend);
        $this->assertArrayHasKey('login', $backend);
        $this->assertArrayHasKey('region', $backend);
    }

}