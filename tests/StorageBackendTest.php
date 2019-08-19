<?php

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;
use Keboola\StorageApi\Client;

class StorageBackendTest extends ClientTestCase
{

    public function testStorageAssignRedshiftBackend()
    {
        // get redshift backend
        $backends = $this->client->listStorageBackend();
        $redshiftBackend = null;
        foreach ($backends as $backend) {
            if ($backend['backend'] == 'redshift') {
                $redshiftBackend = $backend;
                break;
            }
        }
        if (!$redshiftBackend) {
            $this->fail('Redshift backend not found');
        }

        $name = 'My org';
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => $name,
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
        ]);

        $this->assertArrayHasKey('backends', $project);
        $this->assertEquals('snowflake', $project['defaultBackend']);

        $backends = $project['backends'];

        $this->assertArrayHasKey('snowflake', $backends);
        $this->assertArrayNotHasKey('redshift', $backends);

        $this->client->assignProjectStorageBackend($project['id'], $redshiftBackend['id']);

        $project = $this->client->getProject($project['id']);
        $backends = $project['backends'];

        $this->assertArrayHasKey('redshift', $backends);
        $this->assertEquals('redshift', $project['defaultBackend']);

        $redshift = $backends['redshift'];
        $this->assertEquals($redshiftBackend['id'], $redshift['id']);

        // let's try to create a bucket in project now

        $token = $this->client->createProjectStorageToken($project['id'], [
            'description' => 'test',
            'expiresIn' => 60,
            'canManageBuckets' => true,
        ]);

        $sapiClient = new \Keboola\StorageApi\Client([
            'url' => getenv('KBC_MANAGE_API_URL'),
            'token' => $token['token'],
        ]);
        $bucketId = $sapiClient->createBucket('test', 'in');
        $bucket = $sapiClient->getBucket($bucketId);
        $this->assertEquals('redshift', $bucket['backend']);

        $sapiClient->dropBucket($bucketId);

        $this->client->removeProjectStorageBackend($project['id'], $redshiftBackend['id']);
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

    public function testStorageBackendRemove()
    {
        $name = 'My org';
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => $name,
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
        ]);

        $this->assertArrayHasKey('backends', $project);
        $this->assertEquals('snowflake', $project['defaultBackend']);
        $this->assertCount(1, $project['backends']);

        $this->client->removeProjectStorageBackend($project['id'], reset($project['backends'])['id']);

        $project = $this->client->getProject($project['id']);
        $this->assertEmpty($project['backends']);
    }

    public function testStorageBackendShouldNotBeRemovedIfThereAreBuckets()
    {
        $name = 'My org';
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => $name,
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
        ]);

        $token = $this->client->createProjectStorageToken($project['id'], [
            'description' => 'test',
            'expiresIn' => 60,
            'canManageBuckets' => true,
        ]);

        $sapiClient = new \Keboola\StorageApi\Client([
            'url' => getenv('KBC_MANAGE_API_URL'),
            'token' => $token['token'],
        ]);
        $sapiClient->createBucket('test', 'in');

        try {
            $this->client->removeProjectStorageBackend($project['id'], reset($project['backends'])['id']);
            $this->fail('Backend should not be removed');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertEquals('bucketsExists', $e->getStringCode());
        }
    }
}
