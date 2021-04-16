<?php

namespace Keboola\ManageApiTest;

use Keboola\Csv\CsvFile;
use Keboola\ManageApi\Backend;
use Keboola\ManageApi\ClientException;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Metadata;
use Keboola\StorageApi\Options\Components as ComponentsOptions;
use Keboola\StorageApi\Workspaces;

class ProjectDeleteTest extends ParallelClientTestCase
{
    private const FILE_STORAGE_PROVIDER_S3 = 'aws';
    private const FILE_STORAGE_PROVIDER_ABS = 'azure';

    private $organization;

    public function setUp()
    {
        parent::setUp();

        $this->organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);
    }

    private function waitUntilProjectWillBeDeleted($project)
    {
        $startTime = time();
        $maxWaitTimeSeconds = 120;

        // wait until project will be deleted
        do {
            $isProjectDeleted = false;
            try {
                $this->client->getProject($project['id']);
            } catch (ClientException $e) {
                $isProjectDeleted = true;
            }
            if (time() - $startTime > $maxWaitTimeSeconds) {
                throw new \Exception('Project purge timeout.');
            }
            sleep(1);
        } while ($isProjectDeleted !== true);

        // reset the clock
        $startTime = time();

        // purge all data async
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

    public function deleteAndPurgeProjectWithData(): \Generator
    {
        yield 'snowflake with S3 file storage' => [
            'backend' => Backend::SNOWFLAKE,
            'fileStorageProvider' => self::FILE_STORAGE_PROVIDER_S3,
        ];
        yield 'snowflake with ABS file storage test' => [
            'backend' => Backend::SNOWFLAKE,
            'fileStorageProvider' => self::FILE_STORAGE_PROVIDER_ABS,
        ];
        yield 'redshift with S3 file storage' => [
            'backend' => Backend::REDSHIFT,
            'fileStorageProvider' => self::FILE_STORAGE_PROVIDER_S3,
        ];
        yield 'synapse with ABS file storage' => [
            'backend' => Backend::SYNAPSE,
            'fileStorageProvider' => self::FILE_STORAGE_PROVIDER_ABS,
        ];
    }

    public function testPurgeExpiredProjectRemoveJoinRequest()
    {
        $normalJoinRequests = $this->normalUserClient->listMyProjectJoinRequests();

        foreach ($normalJoinRequests as $invitation) {
            $this->normalUserClient->deleteMyProjectJoinRequest($invitation['id']);
        }

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $this->client->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => 0,
        ]);

        $project = $this->client->createProject($this->organization['id'], [
            'name' => 'My test',
            'defaultBackend' => Backend::SNOWFLAKE,
        ]);

        $this->normalUserClient->requestAccessToProject($project['id']);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(1, $joinRequests);
        $this->assertSame($project['id'], $joinRequests[0]['project']['id']);

        $this->client->updateProject($project['id'], ['expirationDays' => -1]);

        $this->waitUntilProjectWillBeDeleted($project);

        $joinRequests = $this->normalUserClient->listMyProjectJoinRequests();
        $this->assertCount(0, $joinRequests);
    }

    public function testPurgeExpiredProjectRemoveUserInvitation()
    {
        $normalUserInvitations = $this->normalUserClient->listMyProjectInvitations();

        foreach ($normalUserInvitations as $invitation) {
            $this->normalUserClient->declineMyProjectInvitation($invitation['id']);
        }

        $project = $this->client->createProject($this->organization['id'], [
            'name' => 'My test',
            'defaultBackend' => Backend::SNOWFLAKE,
        ]);

        $this->client->inviteUserToProject($project['id'], ['email' => $this->normalUser['email']]);

        $normalUserInvitations = $this->normalUserClient->listMyProjectInvitations();

        $this->assertCount(1, $normalUserInvitations);
        $this->assertSame($project['id'], $normalUserInvitations[0]['project']['id']);

        $this->client->updateProject($project['id'], ['expirationDays' => -1]);

        $this->waitUntilProjectWillBeDeleted($project);

        $normalUserInvitations = $this->normalUserClient->listMyProjectInvitations();
        $this->assertCount(0, $normalUserInvitations);
    }

    public function testPurgeExpiredProjectRemoveMetadata()
    {
        /*
          creates and purges project with project metadata. Metadata should be erased after purging. Check it manually
          because there is no way how to test is automatically
        */
        $project = $this->client->createProject($this->organization['id'], [
            'name' => 'My test',
            'defaultBackend' => Backend::SNOWFLAKE,
        ]);

        $this->client->setProjectMetadata(
            $project['id'],
            ProjectsMetadataTest::PROVIDER_USER,
            ProjectsMetadataTest::TEST_METADATA
        );
        $this->client->updateProject($project['id'], ['expirationDays' => -1]);

        $this->waitUntilProjectWillBeDeleted($project);
    }


    /**
     * @dataProvider deleteAndPurgeProjectWithData
     */
    public function testDeleteAndPurgeProjectWithData(string $backend, string $fileStorageProvider): void
    {
        $connectionParamName = sprintf('defaultConnection%sId', ucfirst($backend));
        $maintainer =  $this->client->getMaintainer($this->testMaintainerId);

        if ($maintainer[$connectionParamName] === null) {
            $this->markTestSkipped(sprintf('Test maintainer does not have set default connection for %s backend', $backend));
        }

        $project = $this->client->createProject($this->organization['id'], [
            'name' => 'My test',
            'defaultBackend' => $backend,
        ]);

        $this->assertEquals($backend, $project['defaultBackend']);

        $this->client->assignFileStorage($project['id'], $this->loadFileStorageId($fileStorageProvider));

        if ($backend === Backend::REDSHIFT || $backend === Backend::SYNAPSE) {
            $this->client->assignProjectStorageBackend($project['id'], $maintainer[$connectionParamName]);
        }

        $project = $this->client->getProject($project['id']);
        $this->assertTrue($project['has' . ucfirst($backend)]);

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
        $configuration = (new ComponentsOptions\Configuration())
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

        $components = new Components($sapiClient);
        $components->addConfiguration($configuration);

        $configurationRow = new ComponentsOptions\ConfigurationRow($configuration);
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
        $maxWaitTimeSeconds = 5 * 60;
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

    private function loadFileStorageId(string $fileStorageProvider): int
    {
        if ($fileStorageProvider === self::FILE_STORAGE_PROVIDER_ABS) {
            $fileStorages = array_filter(
                $this->client->listAbsFileStorage(),
                function (array $fileStorage) {
                    return $fileStorage['owner'] === 'keboola';
                }
            );
        } else {
            $fileStorages = array_filter(
                $this->client->listS3FileStorage(),
                function (array $fileStorage) {
                    return $fileStorage['owner'] === 'keboola';
                }
            );
        }

        $fileStorage = end($fileStorages);
        return $fileStorage['id'];
    }
}
