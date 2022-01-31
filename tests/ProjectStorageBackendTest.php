<?php

namespace Keboola\ManageApiTest;

use Keboola\Csv\CsvFile;
use Keboola\ManageApi\Backend;
use Keboola\ManageApi\ClientException;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Workspaces;

class ProjectStorageBackendTest extends ClientTestCase
{
    public function supportedNonDefaultBackends(): array
    {
        return [
            [Backend::REDSHIFT],
            [Backend::SYNAPSE],
            //[Backend::EXASOL],
        ];
    }

    /**
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

        $sapiClient = new Client([
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

    public function testROToggleWithoutBuckets()
    {
        $name = 'My orgXXX';
        $organization = $this->client->createOrganization(2, [
            'name' => $name,
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
            'defaultBackend' => Backend::SNOWFLAKE,
            'dataRetentionTimeInDays' => 1,
        ]);

        $token1 = $this->client->createProjectStorageToken($project['id'], [
            'description' => 'test',
            'expiresIn' => 60,
            'canManageBuckets' => true,
        ]);


        $out = $this->client->runCommand([
            'command' => 'storage:tmp:toggle-read-only-role',
            'parameters' => [
                (string) $project['id'],
                '--force',
            ],
        ]);
        sleep(10);

        $project = $this->client->getProject($project['id']);
        $this->assertContains('input-mapping-read-only-storage', $project['features']);
    }

    public function testROToggleWithBuckets()
    {
        $name = 'My orgXXX';
        $organization = $this->client->createOrganization(2, [
            'name' => $name,
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
            'defaultBackend' => Backend::SNOWFLAKE,
            'dataRetentionTimeInDays' => 1,
        ]);

        $token1 = $this->client->createProjectStorageToken($project['id'], [
            'description' => 'test',
            'expiresIn' => 60,
            'canManageBuckets' => true,
        ]);

        $sapiClient = new Client([
            'url' => getenv('KBC_MANAGE_API_URL'),
            'token' => $token1['token'],
        ]);
        $bucketId = $sapiClient->createBucket('test', 'in');
        $importFile = __DIR__ . '/_data/users.csv';

        $sapiClient->createTable($bucketId, 'tabulka',
            new CsvFile($importFile)
        );

        $out = $this->client->runCommand([
            'command' => 'storage:tmp:toggle-read-only-role',
            'parameters' => [
                (string) $project['id'],
                '--force',
            ],
        ]);
        sleep(10);

        $project = $this->client->getProject($project['id']);
        $this->assertNotContains('input-mapping-read-only-storage', $project['features']);
    }


    public function testEnableROWithBuckets()
    {
        // naming:
        // Sara = Sharing project (shares to others)
        // Tom: Target project
        // Noe: No feature enabled -> will be shared, but RO should have no effect

        $name = 'My orgXXX';
        $organization = $this->client->createOrganization(2, [
            'name' => $name,
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
            'defaultBackend' => Backend::SNOWFLAKE,
            'dataRetentionTimeInDays' => 1,
        ]);

        $token1 = $this->client->createProjectStorageToken($project['id'], [
            'description' => 'test',
            'expiresIn' => 60,
            'canManageBuckets' => true,
        ]);

        $sapiClient = new Client([
            'url' => getenv('KBC_MANAGE_API_URL'),
            'token' => $token1['token'],
        ]);
        $bucketId = $sapiClient->createBucket('test', 'in');
        $importFile = __DIR__ . '/_data/users.csv';

        $sapiClient->createTable($bucketId, 'tabulka',
            new CsvFile($importFile)
        );

        $this->client->runCommand([
            'command' => 'storage:tmp:enable-read-only-role',
            'parameters' => [
                (string) $project['id'],
                '--force',
            ],
        ]);
        sleep(10);

        $project = $this->client->getProject($project['id']);
        $this->assertContains('input-mapping-read-only-storage', $project['features']);
    }

    public function testEnableROWithBucketsAndShare()
    {
        $importFile = __DIR__ . '/_data/users.csv';

        $name = 'My orgXXX';
        $organization = $this->client->createOrganization(2, [
            'name' => $name,
        ]);

        $saraProject1 = $this->client->createProject($organization['id'], [
            'name' => 'Sara - Source project (sharing)',
            'defaultBackend' => Backend::SNOWFLAKE,
            'dataRetentionTimeInDays' => 1,
        ]);

        $token1 = $this->client->createProjectStorageToken($saraProject1['id'], [
            'description' => 'token Sara',
            'expiresIn' => 600,
            'canManageBuckets' => true,
        ]);

        $tomProject2 = $this->client->createProject($organization['id'], [
            'name' => 'Tom - Target project (linking)',
            'defaultBackend' => Backend::SNOWFLAKE,
            'dataRetentionTimeInDays' => 1,
        ]);

        $token2 = $this->client->createProjectStorageToken($tomProject2['id'], [
            'description' => 'token Tom',
            'expiresIn' => 600,
            'canManageBuckets' => true,
        ]);

        $noeProject3 = $this->client->createProject($organization['id'], [
            'name' => 'Noe - project without RO (wanna share, but no RO)',
            'defaultBackend' => Backend::SNOWFLAKE,
            'dataRetentionTimeInDays' => 1,
        ]);

        $token3 = $this->client->createProjectStorageToken($noeProject3['id'], [
            'description' => 'token Noe',
            'expiresIn' => 600,
            'canManageBuckets' => true,
        ]);

        $saraClient = new Client([
            'url' => getenv('KBC_MANAGE_API_URL'),
            'token' => $token1['token'],
        ]);
        $tomClient = new Client([
            'url' => getenv('KBC_MANAGE_API_URL'),
            'token' => $token2['token'],
        ]);
        $noeClient = new Client([
            'url' => getenv('KBC_MANAGE_API_URL'),
            'token' => $token3['token'],
        ]);

        $saraBucket = $saraClient->createBucket('sara-bucket', 'in');
        $saraClient->createTable($saraBucket, 'sara-table',
            new CsvFile($importFile)
        );

        $noeBucket = $noeClient->createBucket('noe-bucket', 'in');
        $noeClient->createTable($noeBucket, 'noe-table',
            new CsvFile($importFile)
        );

        $tomBucket = $tomClient->createBucket('tom-bucket', 'in');
        $tomClient->createTable($tomBucket, 'tom-table',
            new CsvFile($importFile)
        );

        // sara shares bucket, tom links
        $saraClient->shareBucket($saraBucket);
        $tomClient->linkBucket('linkedBucketFromSara', 'in', $saraProject1['id'], $saraBucket);

        // noe shares bucket, tom links
        $noeClient->shareBucket($noeBucket);
        $tomClient->linkBucket('linkedBucketFromNoe', 'in', $noeProject3['id'], $noeBucket);

        // Sara enables RO. Future grants on $saraBucket
        // scenario 1 - share my bucket
        $this->client->runCommand([
            'command' => 'storage:tmp:enable-read-only-role',
            'parameters' => [
                (string) $saraProject1['id'],
                '--force',
                '--lock',
            ],
        ]);
        // it has to wait a while because the whole command has to finish before next step
        sleep(40);

        // tom shares bucket, sara links
        $tomClient->shareBucket($tomBucket);
        $saraClient->linkBucket('linkedBucketFromTom', 'in', $tomProject2['id'], $tomBucket);

        // noe links Tom's bucket
        $noeClient->linkBucket('linkedBucketFromTomToNoe', 'in', $tomProject2['id'], $tomBucket);

        $tomBucketToUnshare = $tomClient->createBucket('tom-bucket-to-unshare', 'in');
        $tomClient->createTable($tomBucketToUnshare, 'tom-table-to-unshare',
            new CsvFile($importFile)
        );
        $tomClient->shareBucket($tomBucketToUnshare);
        $saraClient->linkBucket('linkedBucketFromTomToUnshare', 'in', $tomProject2['id'], $tomBucketToUnshare);

        $tomBucketToUnlink = $tomClient->createBucket('tom-bucket-to-unlink', 'in');
        $tomClient->createTable($tomBucketToUnlink, 'tom-table-to-unlink',
            new CsvFile($importFile)
        );
        $tomClient->shareBucket($tomBucketToUnlink);
        $saraBucketToUnlink = $saraClient->linkBucket('linkedBucketFromTomToUnlink', 'in', $tomProject2['id'], $tomBucketToUnlink);

        // Tom enables RO.
        // scenario 1 - share my bucket
        // scenario 2 - link my bucket to others who have RO (Sara has RO and she has linked Toms bucket)
        // scenario 3 - link others linked buckets whose owners have RO (Tom has linked Sara's bucket)
        // Grant SaraShareRole to TomReadOnlyRole
        $this->client->runCommand([
            'command' => 'storage:tmp:enable-read-only-role',
            'parameters' => [
                (string) $tomProject2['id'],
                '--force',
                '--lock',
            ],
        ]);
        // it has to wait a while because the whole command has to finish before next step
        sleep(40);

        $saraProject1 = $this->client->getProject($saraProject1['id']);
        $tomProject2 = $this->client->getProject($tomProject2['id']);
        $noeProject3 = $this->client->getProject($noeProject3['id']);
        $this->assertContains('input-mapping-read-only-storage', $saraProject1['features']);
        $this->assertContains('input-mapping-read-only-storage', $tomProject2['features']);
        $this->assertNotContains('input-mapping-read-only-storage', $noeProject3['features']);

        // Tom unshares the bucket, so Sara shouldn't see it
        $tomClient->unshareBucket($tomBucketToUnshare);
        $saraClient->dropBucket($saraBucketToUnlink, ['async' => true]);

        $saraWS = (new Workspaces($saraClient))->createWorkspace([]);
        $tomWS = (new Workspaces($tomClient))->createWorkspace([]);
        $noeWS = (new Workspaces($noeClient))->createWorkspace([]);

        print_r([
            'sara' => $saraWS['connection'],
            'tom' => $tomWS['connection'],
            'noe' => $noeWS['connection'],
        ]);
        // as Sara I should see 1. tom-bucket > tom-table (because of sharing); 2. sara-bucket > sara-table (because her)
        // as Tom I should see 1. sara-bucket > sara-table (because of sharing); 2. tom-bucket > tom-table (because his)
        // as Noe I should see nothing
    }
}
