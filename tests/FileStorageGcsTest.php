<?php

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;
use const JSON_THROW_ON_ERROR;

/**
 * @group FileStorage
 */
class FileStorageGcsTest extends ClientTestCase
{
    private const ROTATE_S3_OPTIONS = [
        'awsKey' => TEST_S3_ROTATE_KEY,
        'awsSecret' => TEST_S3_ROTATE_SECRET,
    ];

    private array $credentials;

    private array $rotateCredentials;

    public function setUp(): void
    {
        $this->credentials = json_decode((string) TEST_GCS_KEYFILE_JSON, true, 512, JSON_THROW_ON_ERROR);
        $this->rotateCredentials = json_decode((string) TEST_GCS_KEYFILE_ROTATE_JSON, true, 512, JSON_THROW_ON_ERROR);
        parent::setUp();
    }

    public function getGcsDefaultOptions(): array
    {
        return [
            'gcsCredentials' => $this->credentials,
            'owner' => 'keboola',
            'region' => TEST_GCS_REGION,
            'filesBucket' => TEST_GCS_FILES_BUCKET,
        ];
    }

    public function getGcsRotateOptions(): array
    {
        return [
            'gcsCredentials' => $this->rotateCredentials,
        ];
    }

    public function getExpectedGcsCredentialsWithoutPk(): array
    {
        $credentials = $this->credentials;
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
        $rotateOptions = $this->getGcsRotateOptions();
        $this->assertNotSame($rotateOptions['gcsCredentials']['private_key_id'], $storage['gcsCredentials']['private_key_id']);

        $rotatedStorage = $this->client->rotateGcsFileStorageCredentials($storage['id'], $rotateOptions);

        $this->assertSame('gcp', $rotatedStorage['provider']);
        $this->assertFalse($rotatedStorage['isDefault']);
        $rotatedGcsCredentials = $rotatedStorage['gcsCredentials'];
        $this->assertArrayNotHasKey('private_key', $rotatedGcsCredentials);
        $this->assertSame($rotatedGcsCredentials['private_key_id'], $rotatedGcsCredentials['private_key_id']);
    }

    public function testListGcsStorages()
    {
        $initCount = count($this->client->listGcsFileStorage());
        $this->client->createGcsFileStorage($this->getGcsDefaultOptions());
        $storages = $this->client->listGcsFileStorage();

        $this->assertSame($initCount + 1, count($storages));

        foreach ($storages as $storage) {
            if ($storage['provider'] !== 'gcp') {
                $this->fail('List of Google Cloud Storages contains also other storages');
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
"owner": This field is missing.
"region": This field is missing.
"gcsCredentials[type]": This field is missing.
"gcsCredentials[project_id]": This field is missing.
"gcsCredentials[private_key_id]": This field is missing.
"gcsCredentials[private_key]": This field is missing.
"gcsCredentials[client_email]": This field is missing.
"gcsCredentials[client_id]": This field is missing.
"gcsCredentials[auth_uri]": This field is missing.
"gcsCredentials[token_uri]": This field is missing.
"gcsCredentials[auth_provider_x509_cert_url]": This field is missing.
"gcsCredentials[client_x509_cert_url]": This field is missing.
"filesBucket": This field is missing.
');
        $this->client->createGcsFileStorage(['gcsCredentials'=>[]]);
    }

    public function testRotateGcsCredentialsWithoutRequiredParams()
    {
        $storage = $this->client->createGcsFileStorage($this->getGcsDefaultOptions());

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Invalid request
Errors:
"gcsCredentials[type]": This field is missing.
"gcsCredentials[project_id]": This field is missing.
"gcsCredentials[private_key_id]": This field is missing.
"gcsCredentials[private_key]": This field is missing.
"gcsCredentials[client_email]": This field is missing.
"gcsCredentials[client_id]": This field is missing.
"gcsCredentials[auth_uri]": This field is missing.
"gcsCredentials[token_uri]": This field is missing.
"gcsCredentials[auth_provider_x509_cert_url]": This field is missing.
"gcsCredentials[client_x509_cert_url]": This field is missing.
');

        $this->client->rotateGcsFileStorageCredentials($storage['id'], ['gcsCredentials'=>[]]);
    }

    public function testProjectAssignGcsFileStorage()
    {
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
