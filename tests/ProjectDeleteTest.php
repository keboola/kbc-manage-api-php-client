<?php

namespace Keboola\ManageApiTest;

use Keboola\Csv\CsvFile;
use Keboola\StorageApi\Metadata;
use Keboola\StorageApi\Workspaces;

class ProjectDeleteTest extends ClientTestCase
{
    public function deleteAndPurgeProjectWithData(): array
    {
        return [
            [
                'snowflake',
            ],
            [
                'synapse',
            ],
            [
                'redshift',
            ],
        ];

    }

    /**
     * @dataProvider deleteAndPurgeProjectWithData
     */
    public function testDeleteAndPurgeProjectWithData($backend): void
    {
        $name = 'My org';
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => $name,
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
            'defaultBackend' => $backend,
        ]);

        $this->assertEquals($backend, $project['defaultBackend']);

        // Create tables, bucket, configuration and workspaces
        $token = $this->client->createProjectStorageToken($project['id'], [
            'description' => 'test',
            'expiresIn' => 600,
            'canManageBuckets' => true,
        ]);

        $sapiClient = new \Keboola\StorageApi\Client([
            'url' => getenv('KBC_MANAGE_API_URL'),
            'token' => $token['token'],
        ]);

        // create bucket and table with data
        $bucketId = $sapiClient->createBucket('test', 'in');
        $bucket = $sapiClient->getBucket($bucketId);
        $this->assertEquals($backend, $bucket['backend']);

        $tableId = $sapiClient->createTable($bucketId, 'users', new CsvFile(__DIR__ . '/_data/users.csv'));

        // create and load workspace
        $workspaces = new Workspaces($sapiClient);
        $workspace = $workspaces->createWorkspace(['backend' => $backend]);

        $this->assertEquals($backend, $workspace['connection']['backend']);

        $workspaces->loadWorkspaceData($workspace['id'], [
            'input' => [
                [
                    'source' => $tableId,
                    'destination' => 'users',
                ],
            ],
        ]);

        // add some configuration to project
        $configuration = (new \Keboola\StorageApi\Options\Components\Configuration())
            ->setComponentId('wr-db')
            ->setConfigurationId('main-1')
            ->setName('Main')
            ->setDescription('some desc')
            ->setConfiguration(array(
                'queries' => array(
                    array(
                        'id' => 1,
                        'query' => 'SELECT * from some_table',
                    ),
                ),
            ));

        $components = new \Keboola\StorageApi\Components($sapiClient);
        $components->addConfiguration($configuration);

        $configurationRow = new \Keboola\StorageApi\Options\Components\ConfigurationRow($configuration);
        $components->addConfigurationRow($configurationRow);

        // add some metadata
        $metadata = new Metadata($sapiClient);
        $metadata->postBucketMetadata($bucketId, 'test', [
            [
                'key' => 'pokus',
                'value' => 'another',
            ],
        ]);

        $metadata->postTableMetadata($tableId, 'test', [
            [
                'key' => 'test',
                'value' => 'test',
            ],
        ]);

        // soft delete
        $this->client->deleteProject($project['id']);

        $deletedProject = $this->client->getDeletedProject($project['id']);
        $this->assertArrayHasKey('deletedTime', $deletedProject);
        $this->assertTrue($deletedProject['isDeleted']);
        $this->assertFalse($deletedProject['isPurged']);

        // purge all data async
        $startTime = time();
        $maxWaitTimeSeconds = 120;
        $purgeResponse = $this->client->purgeDeletedProject($project['id']);
        $this->assertArrayHasKey('commandExecutionId', $purgeResponse);
        $this->assertNotNull($purgeResponse['commandExecutionId']);

        do {
            $deletedProject = $this->client->getDeletedProject($project['id']);
            if (time() - $startTime > $maxWaitTimeSeconds) {
                throw new \Exception('Project purge timeout.');
            }
            sleep(1);
        } while ($deletedProject['isPurged'] !== true);
        $this->assertNotNull($deletedProject['purgedTime']);
    }
}
