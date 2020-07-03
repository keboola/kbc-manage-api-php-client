<?php

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Backend;
use Keboola\ManageApi\ClientException;

class StorageBackendTest extends ClientTestCase
{
    public function supportedNonDefaultBackends(): array
    {
        return [
            [Backend::REDSHIFT],
            [Backend::SYNAPSE],
        ];
    }

    /**
     * @dataProvider supportedNonDefaultBackends
     * @param string $backendName
     */
    public function testStorageAssignBackend(string $backendName): void
    {
        // get redshift and synapse backend
        $backends = $this->client->listStorageBackend();
        $backendToAssign = null;
        foreach ($backends as $item) {
            if ($item['backend'] === $backendName) {
                $backendToAssign = $item;
            }
        }
        if (!$backendToAssign) {
            $this->fail(sprintf('%s backend not found', ucfirst($backendName)));
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

        if ($backendName === Backend::SYNAPSE) {
            $absFileStorages = $this->client->listAbsFileStorage();
            foreach ($absFileStorages as $storage) {
                if ($storage['provider'] === 'azure') {
                    $this->client->assignFileStorage($project['id'], $storage['id']);
                    break;
                }
            }
        }

        $backends = $project['backends'];

        $this->assertArrayHasKey('snowflake', $backends);
        $this->assertArrayNotHasKey($backendName, $backends);

        $this->client->assignProjectStorageBackend($project['id'], $backendToAssign['id']);

        $project = $this->client->getProject($project['id']);
        $backends = $project['backends'];

        $this->assertArrayHasKey($backendName, $backends);
        $this->assertEquals($backendName, $project['defaultBackend']);

        $this->assertEquals($backendToAssign['id'], $backends[$backendName]['id']);

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
        $this->assertEquals($backendName, $bucket['backend']);

        $sapiClient->dropBucket($bucketId);

        $this->client->removeProjectStorageBackend($project['id'], $backendToAssign['id']);

        $this->client->deleteProject($project['id']);
        $this->client->purgeDeletedProject($project['id']);
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
