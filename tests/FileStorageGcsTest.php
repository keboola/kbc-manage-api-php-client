<?php

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

/**
 * @group FileStorage
 */
class FileStorageGcsTest extends ClientTestCase
{
    public function getGcsDefaultOptions(): array
    {
        return [
            'gcsCredentials' => json_decode(TEST_GCS_KEY_FILE, true, 512, \JSON_THROW_ON_ERROR),
            'owner' => 'keboola',
            'region' => TEST_GCS_REGION,
            'filesBucket' => TEST_GCS_FILES_BUCKET
        ];
    }

    public function getGcsRotateOptions(): array
    {
        return [
            'gcsCredentials' => json_decode(TEST_GCS_KEY_FILE, true, 512, \JSON_THROW_ON_ERROR),
        ];
    }

    public function getExpectedGcsCredentialsWithoutPk(): array
    {
        $credentials = json_decode(TEST_GCS_KEY_FILE, true, 512, \JSON_THROW_ON_ERROR);
        $privateKeyArrayKey = 'private_key';
        $this->assertArrayHasKey($privateKeyArrayKey, $credentials);
        unset($credentials[$privateKeyArrayKey]);
        return $credentials;
    }

    public function testFileStorageGcsCreate()
    {
        $storage = $this->client->createGcsFileStorage($this->getGcsDefaultOptions());

        $credentials = $storage['gcsCredentials'];
        $this->assertArrayNotHasKey('private_key', $credentials);
        $this->assertSame($this->getExpectedGcsCredentialsWithoutPk(), $credentials);
        $this->assertSame('keboola', $storage['owner']);
        $this->assertSame(TEST_GCS_REGION, $storage['region']);
        $this->assertSame($storage['provider'], 'gcp');
        $this->assertFalse($storage['isDefault']);
    }

    public function testRotateGcsKey()
    {
        $storage = $this->client->createGcsFileStorage($this->getGcsDefaultOptions());

        $rotatedStorage = $this->client->rotateGcsFileStorageCredentials($storage['id'], $this->getGcsRotateOptions());
        $this->assertArrayNotHasKey('accountKey', $storage);
        $this->assertSame($rotatedStorage['accountName'], TEST_ABS_ACCOUNT_NAME);
        $this->assertSame($rotatedStorage['containerName'], TEST_ABS_CONTAINER_NAME);
        $this->assertSame($rotatedStorage['provider'], 'azure');
        $this->assertFalse($rotatedStorage['isDefault']);
    }

    public function testListGcsStorages()
    {
        $initCount = count($this->client->listGcsFileStorage());
        $this->client->createGcsFileStorage($this->getGcsDefaultOptions());
        $storages = $this->client->listGcsFileStorage();

        $this->assertSame($initCount + 1, count($storages));

        foreach ($storages as $storage) {
            if ($storage['provider'] !== 'azure') {
                $this->fail('List of Azure Blob Storages contains also S3 Storage');
            }
        }
    }

    public function testSetGcsStorageAsDefault()
    {
        $storage = $this->client->createGcsFileStorage($this->getGcsDefaultOptions());

        $this->assertFalse($storage['isDefault']);

        $storage = $this->client->setGcsFileStorageAsDefault($storage['id']);

        $this->assertTrue($storage['isDefault']);

        $storageList = $this->client->listGcsFileStorage();
        $regions = [];
        foreach ($storageList as $item) {
            if ($item['isDefault'] && in_array($item['region'], $regions)) {
                $this->fail('There are more default storage backends with default flag in one region');
            }

            if ($item['isDefault']) {
                array_push($regions, $item['region']);
            }

            if ($item['isDefault'] && $item['id'] !== $storage['id'] && $item['region'] === $this->getGcsDefaultOptions()['region']) {
                $this->fail('Eu storage backend was not set as default correctly');
            }
        }
    }

    public function testCrossProviderStorageDefaultGcsS3()
    {
        $storage = $this->client->createGcsFileStorage($this->getGcsDefaultOptions());

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage(sprintf('AWS S3 file storage "%d" not found', $storage['id']));
        $this->client->setS3FileStorageAsDefault($storage['id']);
    }

    public function testCreateGcsStorageWithoutRequiredParam()
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Invalid request
Errors:
"accountName": This field is missing.
"accountKey": This field is missing.
"containerName": This field is missing.
"owner": This field is missing.
');
        $this->client->createGcsFileStorage([]);
    }

    public function testRotateGcsCredentialsWithoutRequiredParams()
    {
        $storage = $this->client->createGcsFileStorage($this->getGcsDefaultOptions());

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Invalid request
Errors:
"accountKey": This field is missing');

        $this->client->rotateGcsFileStorageCredentials($storage['id'], []);
    }

    public function testProjectAssignGcsFileStorage()
    {
        $this->markTestSkipped('Will be enabled after Azure FS will be fully working');

        $name = 'My org';
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => $name,
        ]);

        $project = $this->createRedshiftProjectForClient($this->client, $organization['id'], [
            'name' => 'My test',
        ]);

        $storage = $this->client->createGcsFileStorage($this->getGcsDefaultOptions());

        $this->client->assignFileStorage($project['id'], $storage['id']);

        $project = $this->client->getProject($project['id']);
        $this->assertEquals($storage['id'], $project['fileStorage']['id']);
    }

    public function testCrossProviderStorageCredentialsRotateGcsS3()
    {
        $storage = $this->client->createGcsFileStorage($this->getGcsDefaultOptions());

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage(sprintf('AWS S3 file storage "%d" not found', $storage['id']));
        $this->client->rotateS3FileStorageCredentials($storage['id'], self::ROTATE_S3_OPTIONS);
    }
}
