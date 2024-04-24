<?php

namespace Keboola\ManageApiTest;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Keboola\ManageApi\Backend;
use Keboola\ManageApi\ClientException;
use Keboola\StorageApi\Client;

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

        $client = new GuzzleClient([
            'base_uri' => getenv('KBC_MANAGE_API_URL'),
        ]);

        $requestOptions = [
            'headers' => [
                'X-KBC-ManageApiToken' => getenv('KBC_MANAGE_API_TOKEN'),
                'Accept-Encoding' => 'gzip',
                'Content-Type' => 'application/json',
                'User-Agent' => 'Keboola Manage API PHP Client',
            ],
        ];

        try {
            $requestOptions['body'] = '{"storageBackendId": "non-numeric"}';
            $client->post('/manage/projects/' . $project['id'] . '/storage-backend', $requestOptions);
            $this->fail('Should fail with 400');
        } catch (GuzzleException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertStringContainsString('storageBackendId must be an integer.', $e->getMessage());
        }

        $this->client->removeProjectStorageBackend($project['id'], $project['backends']['snowflake']['id']);
        $backend = $this->client->createStorageBackend($this->getBackendCreateOptions());

        // ensure, that backend ID is passed as string into body
        $requestOptions['body'] = '{"storageBackendId": "'.$backend['id'].'"}';
        $response = $client->post('/manage/projects/' . $project['id'] . '/storage-backend', $requestOptions);
        $this->assertEquals(200, $response->getStatusCode());

        $this->client->deleteProject($project['id']);
        $this->client->purgeDeletedProject($project['id']);
    }

    public function getBackendCreateOptions(): array
    {
        return [
            'backend' => 'snowflake',
            'host' => getenv('KBC_TEST_SNOWFLAKE_HOST'),
            'warehouse' => getenv('KBC_TEST_SNOWFLAKE_WAREHOUSE'),
            'username' => getenv('KBC_TEST_SNOWFLAKE_BACKEND_NAME'),
            'password' => getenv('KBC_TEST_SNOWFLAKE_BACKEND_PASSWORD'),
            'region' => getenv('KBC_TEST_SNOWFLAKE_BACKEND_REGION'),
            'owner' => 'keboola',
        ];
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
