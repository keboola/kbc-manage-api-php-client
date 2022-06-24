<?php

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

/**
 * @group FileStorage
 */
class FileStorageGcsTest extends ClientTestCase
{
    public const DEFAULT_GCS_OPTIONS = [
        'type' => TEST_GCS_TYPE,
        'projectId' => TEST_GCS_PROJECT_ID,
        'privateKeyId' => TEST_GCS_PRIVATE_KEY_ID,
        'privateKey' => TEST_GCS_PRIVATE_KEY,
        'clientEmail' => TEST_GCS_CLIENT_EMAIL,
        'clientId' => TEST_GCS_CLIENT_ID,
        'authUri' => TEST_GCS_AUTH_URI,
        'tokenUri' => TEST_GCS_TOKEN_URI,
        'authProviderX509CertUrl' => TEST_GCS_AUTH_PROVIDER_X509_CERT_URL,
        'clientX509CertUrl' => TEST_GCS_CLIENT_X509_CERT_URL,
        'owner' => 'keboola',
        'region' => TEST_GCS_REGION,
    ];
    private const ROTATE_GCS_OPTIONS = [
        'privateKeyId' => TEST_GCS_PRIVATE_KEY_ID,
        'privateKey' => TEST_GCS_PRIVATE_KEY,
        'clientEmail' => TEST_GCS_CLIENT_EMAIL,
        'clientId' => TEST_GCS_CLIENT_ID,
        'authUri' => TEST_GCS_AUTH_URI,
        'tokenUri' => TEST_GCS_TOKEN_URI,
        'authProviderX509CertUrl' => TEST_GCS_AUTH_PROVIDER_X509_CERT_URL,
        'clientX509CertUrl' => TEST_GCS_CLIENT_X509_CERT_URL,
    ];

    public function testFileStorageGcsCreate()
    {
        $storage = $this->client->createGcsFileStorage(self::DEFAULT_GCS_OPTIONS);

        $this->assertArrayNotHasKey('private_key', $storage);

        $this->assertSame(TEST_GCS_TYPE, $storage['type']);
        $this->assertSame(TEST_GCS_PROJECT_ID, $storage['projectId']);
        $this->assertSame(TEST_GCS_PRIVATE_KEY_ID, $storage['privateKeyId']);
        $this->assertSame(TEST_GCS_PRIVATE_KEY, $storage['privateKey']);
        $this->assertSame(TEST_GCS_CLIENT_EMAIL, $storage['clientEmail']);
        $this->assertSame(TEST_GCS_CLIENT_ID, $storage['clientId']);
        $this->assertSame(TEST_GCS_AUTH_URI, $storage['authUri']);
        $this->assertSame(TEST_GCS_TOKEN_URI, $storage['tokenUri']);
        $this->assertSame(TEST_GCS_AUTH_PROVIDER_X509_CERT_URL, $storage['authProviderX509CertUrl']);
        $this->assertSame(TEST_GCS_CLIENT_X509_CERT_URL, $storage['clientX509CertUrl']);
        $this->assertSame('keboola', $storage['owner']);
        $this->assertSame(TEST_GCS_REGION, $storage['region']);
        $this->assertSame($storage['provider'], 'gcp');
        $this->assertFalse($storage['isDefault']);
    }

    public function testRotateGcsKey()
    {
        $storage = $this->client->createGcsFileStorage(self::DEFAULT_GCS_OPTIONS);

        $rotatedStorage = $this->client->rotateGcsFileStorageCredentials($storage['id'], self::ROTATE_GCS_OPTIONS);
        $this->assertArrayNotHasKey('accountKey', $storage);
        $this->assertSame($rotatedStorage['accountName'], TEST_ABS_ACCOUNT_NAME);
        $this->assertSame($rotatedStorage['containerName'], TEST_ABS_CONTAINER_NAME);
        $this->assertSame($rotatedStorage['provider'], 'azure');
        $this->assertFalse($rotatedStorage['isDefault']);
    }

    public function testListGcsStorages()
    {
        $initCount = count($this->client->listGcsFileStorage());
        $this->client->createGcsFileStorage(self::DEFAULT_GCS_OPTIONS);
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
        $storage = $this->client->createGcsFileStorage(self::DEFAULT_GCS_OPTIONS);

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

            if ($item['isDefault'] && $item['id'] !== $storage['id'] && $item['region'] === self::DEFAULT_GCS_OPTIONS['region']) {
                $this->fail('Eu storage backend was not set as default correctly');
            }
        }
    }

    public function testCrossProviderStorageDefaultGcsS3()
    {
        $storage = $this->client->createGcsFileStorage(self::DEFAULT_GCS_OPTIONS);

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
        $storage = $this->client->createGcsFileStorage(self::DEFAULT_GCS_OPTIONS);

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

        $storage = $this->client->createGcsFileStorage(self::DEFAULT_GCS_OPTIONS);

        $this->client->assignFileStorage($project['id'], $storage['id']);

        $project = $this->client->getProject($project['id']);
        $this->assertEquals($storage['id'], $project['fileStorage']['id']);
    }

    public function testCrossProviderStorageCredentialsRotateGcsS3()
    {
        $storage = $this->client->createGcsFileStorage(self::DEFAULT_GCS_OPTIONS);

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage(sprintf('AWS S3 file storage "%d" not found', $storage['id']));
        $this->client->rotateS3FileStorageCredentials($storage['id'], self::ROTATE_S3_OPTIONS);
    }
}
