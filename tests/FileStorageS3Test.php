<?php


namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;
use function GuzzleHttp\json_encode;

/**
 * @group FileStorage
 */
class FileStorageS3Test extends ClientTestCase
{

    private const ROTATE_ABS_OPTIONS = [
        'accountKey' => TEST_ABS_ROTATE_ACCOUNT_KEY,
    ];

    private const DEFAULT_S3_OPTIONS = [
        'awsKey' => TEST_S3_KEY,
        'awsSecret' => TEST_S3_SECRET,
        'region' => TEST_S3_REGION,
        'filesBucket' => TEST_S3_FILES_BUCKET,
        'owner' => 'keboola',
    ];


    private const ROTATE_S3_OPTIONS = [
        'awsKey' => TEST_S3_ROTATE_KEY,
        'awsSecret' => TEST_S3_ROTATE_SECRET,
    ];

    public function testFileStorageS3Create()
    {
        $storage = $this->client->createS3FileStorage(self::DEFAULT_S3_OPTIONS);

        $this->assertArrayNotHasKey('awsSecret', $storage);
        $this->assertSame($storage['awsKey'], TEST_S3_KEY);
        $this->assertSame($storage['region'], TEST_S3_REGION);
        $this->assertSame($storage['filesBucket'], TEST_S3_FILES_BUCKET);
        $this->assertSame($storage['provider'], 'aws');
        $this->assertFalse($storage['isDefault']);
    }

    public function testRotateS3Key()
    {
        $storage = $this->client->createS3FileStorage(self::DEFAULT_S3_OPTIONS);

        $rotatedStorage = $this->client->rotateS3FileStorageCredentials($storage['id'], self::ROTATE_S3_OPTIONS);

        $this->assertArrayNotHasKey('awsSecret', $storage);
        $this->assertSame($rotatedStorage['awsKey'], TEST_S3_ROTATE_KEY);
        $this->assertSame($rotatedStorage['region'], TEST_S3_REGION);
        $this->assertSame($rotatedStorage['filesBucket'], TEST_S3_FILES_BUCKET);
        $this->assertSame($rotatedStorage['provider'], 'aws');
        $this->assertFalse($rotatedStorage['isDefault']);
    }

    public function testListS3Storages()
    {
        $initCount = count($this->client->listS3FileStorage());
        $this->client->createS3FileStorage(self::DEFAULT_S3_OPTIONS);
        $storages = $this->client->listS3FileStorage();

        $this->assertSame($initCount + 1, count($storages));

        foreach ($storages as $storage) {
            if ($storage['provider'] !== 'aws') {
                $this->fail('List of S3 storages contains also Azure Blob Storages');
            }
        }
    }


    public function testSetS3StorageAsDefault()
    {
        $storage = $this->client->createS3FileStorage(self::DEFAULT_S3_OPTIONS);

        $this->assertFalse($storage['isDefault']);

        $storage = $this->client->setS3FileStorageAsDefault($storage['id']);

        $this->assertTrue($storage['isDefault']);

        $storageList = $this->client->listS3FileStorage();
        $regions = [];
        foreach ($storageList as $item) {
            if ($item['isDefault'] && in_array($item['region'], $regions)) {
                $this->fail('There are more default storage backends with default flag in one region');
            }

            if ($item['isDefault']) {
                array_push($regions, $item['region']);
            }

            if ($item['isDefault'] && $item['id'] !== $storage['id'] && $item['region'] === self::DEFAULT_S3_OPTIONS['region']) {
                $this->fail('Eu storage backend was not set as default correctly');
            }
        }
    }

    public function testSetS3StorageDefaultInMultipleRegions()
    {
        $this->markTestSkipped('This tests requires working us-east-1 S3 credentials and bucket');
        $storage = $this->client->createS3FileStorage(self::DEFAULT_S3_OPTIONS);

        $this->assertFalse($storage['isDefault']);

        $storage = $this->client->setS3FileStorageAsDefault($storage['id']);

        $this->assertTrue($storage['isDefault']);

        $usRegionStorage = $this->client->createS3FileStorage(array_merge(self::DEFAULT_S3_OPTIONS, [
            'region' => 'us-east-1',
            'awsKey' => '### FILL BEFORE RUNNING TEST ###',
            'awsSecret' => '### FILL BEFORE RUNNING TEST ###',
            'filesBucket' => '### FILL BEFORE RUNNING TEST ###',
        ]));

        $this->assertFalse($usRegionStorage['isDefault']);

        $usRegionStorage = $this->client->setS3FileStorageAsDefault($usRegionStorage['id']);

        $this->assertTrue($usRegionStorage['isDefault']);

        $storageList = $this->client->listS3FileStorage();
        $regions = [];
        foreach ($storageList as $item) {
            if ($item['isDefault'] && in_array($item['region'], $regions)) {
                $this->fail('There are more default storage backends with default flag in one region');
            }

            if ($item['isDefault']) {
                array_push($regions, $item['region']);
            }

            if ($item['isDefault'] && $item['id'] !== $storage['id'] && $item['region'] === self::DEFAULT_S3_OPTIONS['region']) {
                $this->fail('Eu storage was not set as default correctly');
            }

            if ($item['isDefault'] && $item['id'] !== $usRegionStorage['id'] && $item['region'] === 'us-east-1') {
                $this->fail('Us storage was not set as default correctly');
            }
        }
    }

    public function testCrossProviderStorageDefaultS3Abs()
    {
        $storage = $this->client->createS3FileStorage(self::DEFAULT_S3_OPTIONS);

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage(sprintf('Azure Blob Storage file storage "%d" not found', $storage['id']));
        $this->client->setAbsFileStorageAsDefault($storage['id']);
    }

    public function testCrossProviderStorageCredentialsRotateS3Abs()
    {
        $storage = $this->client->createS3FileStorage(self::DEFAULT_S3_OPTIONS);

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage(sprintf('Azure Blob Storage file storage "%d" not found', $storage['id']));
        $this->client->rotateAbsFileStorageCredentials($storage['id'], self::ROTATE_ABS_OPTIONS);
    }

    public function testCreateS3StorageWithoutRequiredParam()
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Invalid request
Errors:
"awsKey": This field is missing.
"awsSecret": This field is missing.
"filesBucket": This field is missing.
"owner": This field is missing.
"region": This field is missing.
');
        $this->client->createS3FileStorage([]);
    }

    public function testRotateS3CredentialsWithoutRequiredParams()
    {
        $storage = $this->client->createS3FileStorage(self::DEFAULT_S3_OPTIONS);

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Invalid request
Errors:
"awsKey": This field is missing.
"awsSecret": This field is missing.
');
        $this->client->rotateS3FileStorageCredentials($storage['id'], []);
    }

    public function testProjectAssignS3FileStorage()
    {
        $name = 'My org';
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => $name,
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
        ]);

        $storage = $this->client->createS3FileStorage(self::DEFAULT_S3_OPTIONS);

        $this->client->assignFileStorage($project['id'], $storage['id']);

        $project = $this->client->getProject($project['id']);
        $this->assertEquals($storage['id'], $project['fileStorage']['id']);
    }
}
