<?php

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Backend;
use Keboola\ManageApi\Client as ManageClient;
use Keboola\ManageApi\ClientException;
use Keboola\StorageApi\Client;
use ReflectionMethod;

class ProjectStorageBackendTest extends ClientTestCase
{
    public function supportedNonDefaultBackends(): array
    {
        return [
            [Backend::REDSHIFT],
//            [Backend::SYNAPSE],
        // synapse isnt available on e2e testing
            [Backend::EXASOL],
//            [Backend::TERADATA],
        ];
    }

    /**
     * @group skipOnGcp
     * @dataProvider supportedNonDefaultBackends
     * @param string $backendName
     */
    public function testProjectStorageAssignBackend(string $backendName): void
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
            'dataRetentionTimeInDays' => 1,
        ]);

        $this->assertArrayHasKey('backends', $project);
        $this->assertEquals('snowflake', $project['defaultBackend']);

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

        $sapiClient = new Client([
            'url' => getenv('KBC_MANAGE_API_URL'),
            'token' => $token['token'],
        ]);
        $bucketId = $sapiClient->createBucket('test', 'in');
        $bucket = $sapiClient->getBucket($bucketId);
        $this->assertEquals($backendName, $bucket['backend']);

        $sapiClient->dropBucket($bucketId, ['async' => true]);

        $this->client->removeProjectStorageBackend($project['id'], $backendToAssign['id']);

        $this->client->deleteProject($project['id']);
        $this->client->purgeDeletedProject($project['id']);
    }

    public function testProjectStorageAssignBackendFailedWithNonNumericBackendId(): void
    {
        $name = 'My org';
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => $name,
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
            'dataRetentionTimeInDays' => 1,
        ]);

        $apiPostMethod = new ReflectionMethod(ManageClient::class, 'apiPost');
        $apiPostMethod->setAccessible(true);

        try {
            $apiPostMethod->invoke($this->client, '/manage/projects/' . $project['id'] . '/storage-backend', [
                'storageBackendId' => 'non-numeric',
            ]);
            // @phpstan-ignore-next-line calling by `invoke` is not recognized by PHPStan as this is dynamic call by reflection
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertEquals('storageBackendId must be an integer.', $e->getMessage());
        }

        try {
            $apiPostMethod->invoke($this->client, '/manage/projects/' . $project['id'] . '/storage-backend', [
                'storageBackendId' => '666',
            ]);
            // @phpstan-ignore-next-line calling by `invoke` is not recognized by PHPStan as this is dynamic call by reflection
        } catch (ClientException $e) {
            // backend storage '666' not found
            // here we're only testing the format of `storageBackendId` so we don't care about the existence of backend storage
            $this->assertEquals(500, $e->getCode());
            $this->assertEquals('Application error.', $e->getMessage());
        }

        $this->client->deleteProject($project['id']);
        $this->client->purgeDeletedProject($project['id']);
    }

    public function testStorageBackendRemove()
    {
        $name = 'My org';
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => $name,
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
            'dataRetentionTimeInDays' => 1,
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
            'dataRetentionTimeInDays' => 1,
        ]);

        $token = $this->client->createProjectStorageToken($project['id'], [
            'description' => 'test',
            'expiresIn' => 60,
            'canManageBuckets' => true,
        ]);

        $sapiClient = new Client([
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
