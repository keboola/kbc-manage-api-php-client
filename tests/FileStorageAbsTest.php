<?php

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;
use function GuzzleHttp\json_encode;

/**
 * @group FileStorage
 */
class FileStorageAbsTest extends ClientTestCase
{
    public const DEFAULT_ABS_OPTIONS = [
        'accountName' => TEST_ABS_ACCOUNT_NAME,
        'accountKey' => TEST_ABS_ACCOUNT_KEY,
        'containerName' => TEST_ABS_CONTAINER_NAME,
        'owner' => 'keboola',
        'region' => TEST_ABS_REGION,
    ];

    private const ROTATE_ABS_OPTIONS = [
        'accountKey' => TEST_ABS_ROTATE_ACCOUNT_KEY,
    ];

    private const ROTATE_S3_OPTIONS = [
        'awsKey' => TEST_S3_ROTATE_KEY,
        'awsSecret' => TEST_S3_ROTATE_SECRET,
    ];

    public function testFileStorageAbsCreate()
    {
        $storage = $this->client->createAbsFileStorage(self::DEFAULT_ABS_OPTIONS);

        $this->assertArrayNotHasKey('accountKey', $storage);
        $this->assertSame($storage['accountName'], TEST_ABS_ACCOUNT_NAME);
        $this->assertSame($storage['containerName'], TEST_ABS_CONTAINER_NAME);
        $this->assertSame($storage['provider'], 'azure');
        $this->assertFalse($storage['isDefault']);
        $this->assertArrayNotHasKey('gcsSnowflakeIntegrationName', $storage);
    }

    public function testRotateAbsKey()
    {
        $storage = $this->client->createAbsFileStorage(self::DEFAULT_ABS_OPTIONS);

        $rotatedStorage = $this->client->rotateAbsFileStorageCredentials($storage['id'], self::ROTATE_ABS_OPTIONS);
        $this->assertArrayNotHasKey('accountKey', $storage);
        $this->assertSame($rotatedStorage['accountName'], TEST_ABS_ACCOUNT_NAME);
        $this->assertSame($rotatedStorage['containerName'], TEST_ABS_CONTAINER_NAME);
        $this->assertSame($rotatedStorage['provider'], 'azure');
        $this->assertFalse($rotatedStorage['isDefault']);
    }

    public function testListAbsStorages()
    {
        $initCount = count($this->client->listAbsFileStorage());
        $this->client->createAbsFileStorage(self::DEFAULT_ABS_OPTIONS);
        $storages = $this->client->listAbsFileStorage();

        $this->assertSame($initCount + 1, count($storages));

        foreach ($storages as $storage) {
            if ($storage['provider'] !== 'azure') {
                $this->fail('List of Azure Blob Storages contains also S3 Storage');
            }
        }
    }

    public function testSetAbsStorageAsDefault()
    {
        $storage = $this->client->createAbsFileStorage(self::DEFAULT_ABS_OPTIONS);

        $this->assertFalse($storage['isDefault']);

        $storage = $this->client->setAbsFileStorageAsDefault($storage['id']);

        $this->assertTrue($storage['isDefault']);

        $storageList = $this->client->listAbsFileStorage();
        $regions = [];
        foreach ($storageList as $item) {
            if ($item['isDefault'] && in_array($item['region'], $regions)) {
                $this->fail('There are more default storage backends with default flag in one region');
            }

            if ($item['isDefault']) {
                array_push($regions, $item['region']);
            }

            if ($item['isDefault'] && $item['id'] !== $storage['id'] && $item['region'] === self::DEFAULT_ABS_OPTIONS['region']) {
                $this->fail('Eu storage backend was not set as default correctly');
            }
        }
    }

    public function testCrossProviderStorageDefaultAbsS3()
    {
        $storage = $this->client->createAbsFileStorage(self::DEFAULT_ABS_OPTIONS);

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage(sprintf('AWS S3 file storage "%d" not found', $storage['id']));
        $this->client->setS3FileStorageAsDefault($storage['id']);
    }

    public function testCreateAbsStorageWithoutRequiredParam()
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Invalid request:
 - accountName: "This field is missing."
 - accountKey: "This field is missing."
 - containerName: "This field is missing."
 - owner: "This field is missing."
 - region: "This field is missing."
Errors:
"accountName": This field is missing.
"accountKey": This field is missing.
"containerName": This field is missing.
"owner": This field is missing.
"region": This field is missing.
');
        $this->client->createAbsFileStorage([]);
    }

    public function testRotateAbsCredentialsWithoutRequiredParams()
    {
        $storage = $this->client->createAbsFileStorage(self::DEFAULT_ABS_OPTIONS);

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Invalid request:
 - accountKey: "This field is missing."
Errors:
"accountKey": This field is missing.
');

        $this->client->rotateAbsFileStorageCredentials($storage['id'], []);
    }

    public function testProjectAssignAbsFileStorage()
    {
        $this->markTestSkipped('Will be enabled after Azure FS will be fully working');

        $name = 'My org';
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => $name,
        ]);

        $project = $this->createRedshiftProjectForClient($this->client, $organization['id'], [
            'name' => 'My test',
        ]);

        $storage = $this->client->createAbsFileStorage(self::DEFAULT_ABS_OPTIONS);

        $this->client->assignFileStorage($project['id'], $storage['id']);

        $project = $this->client->getProject($project['id']);
        $this->assertEquals($storage['id'], $project['fileStorage']['id']);
    }

    public function testCrossProviderStorageCredentialsRotateAbsS3()
    {
        $storage = $this->client->createAbsFileStorage(self::DEFAULT_ABS_OPTIONS);

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage(sprintf('AWS S3 file storage "%d" not found', $storage['id']));
        $this->client->rotateS3FileStorageCredentials($storage['id'], self::ROTATE_S3_OPTIONS);
    }
}
