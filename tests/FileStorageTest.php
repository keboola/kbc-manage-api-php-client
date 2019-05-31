<?php
/**
 * Created by PhpStorm.
 * User: martinhalamicek
 * Date: 15/10/15
 * Time: 15:29
 */

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;
use Keboola\StorageApi\Client;

class FileStorageTest extends ClientTestCase
{

    private const DISABLE_DEV_TESTS = true;

    private const DEFAULT_S3_OPTIONS = [
        'awsKey' => TEST_AWS_KEY,
        'awsSecret' => TEST_AWS_SECRET,
        'region' => TEST_AWS_REGION,
        'filesBucket' => TEST_AWS_FILES_BUCKET,
        'owner' => 'keboola',
    ];

    private const DEFAULT_ABS_OPTIONS = [
        'accountName' => TEST_ABS_ACCOUNT_NAME,
        'accountKey' => TEST_ABS_ACCOUNT_KEY,
        'containerName' => TEST_ABS_CONTAINER_NAME,
        'owner' => 'keboola',
    ];

    private const ROTATE_S3_OPTIONS = [
        'awsKey' => TEST_AWS_ROTATE_KEY,
        'awsSecret' => TEST_AWS_ROTATE_SECRET,
    ];

    private const ROTATE_ABS_OPTIONS = [
        'accountKey' => TEST_ABS_ROTATE_ACCOUNT_KEY,
    ];

    public function testFileStorageS3Create()
    {
        if (self::DISABLE_DEV_TESTS) {
            $this->markTestSkipped('just for development');
        }

        $storage = $this->client->createS3FileStorage(self::DEFAULT_S3_OPTIONS);

        $this->assertArrayNotHasKey('awsSecret', $storage);
        $this->assertSame($storage['awsKey'], TEST_AWS_KEY);
        $this->assertSame($storage['region'], TEST_AWS_REGION);
        $this->assertSame($storage['filesBucket'], TEST_AWS_FILES_BUCKET);
        $this->assertSame($storage['provider'], 'aws');
        $this->assertSame($storage['isDefault'], false);
    }

    public function testFileStorageAbsCreate()
    {
        if (self::DISABLE_DEV_TESTS) {
            $this->markTestSkipped('just for development');
        }

        $storage = $this->client->createAbsFileStorage(self::DEFAULT_ABS_OPTIONS);

        $this->assertArrayNotHasKey('accountKey', $storage);
        $this->assertSame($storage['accountName'], TEST_ABS_ACCOUNT_NAME);
        $this->assertSame($storage['containerName'], TEST_ABS_CONTAINER_NAME);
        $this->assertSame($storage['provider'], 'azure');
        $this->assertSame($storage['isDefault'], false);
    }

    public function testRotateS3Key()
    {
        if (self::DISABLE_DEV_TESTS) {
            $this->markTestSkipped('just for development');
        }

        $storage = $this->client->createS3FileStorage(self::DEFAULT_S3_OPTIONS);

        $rotatedStorage = $this->client->rotateS3FileStorageCredentials($storage['id'], self::ROTATE_S3_OPTIONS);

        $this->assertSame($rotatedStorage['awsKey'], TEST_AWS_ROTATE_KEY);
    }

    public function testRotateAbsKey()
    {
        if (self::DISABLE_DEV_TESTS) {
            $this->markTestSkipped('just for development');
        }

        $storage = $this->client->createAbsFileStorage(self::DEFAULT_ABS_OPTIONS);

        $this->client->rotateAbsFileStorageCredentials($storage['id'], self::ROTATE_ABS_OPTIONS);
    }

    public function testListS3Storages()
    {
        if (self::DISABLE_DEV_TESTS) {
            $this->markTestSkipped('just for development');
        }

        $initCount = count($this->client->listS3FileStorage());
        $this->client->createS3FileStorage(self::DEFAULT_S3_OPTIONS);
        $storages = $this->client->listS3FileStorage();

        $this->assertSame($initCount + 1, count($storages));

        foreach ($storages as $storage) {
            if ($storage['provider'] === 'azure') {
               $this->fail('List of S3 storages contains also Azure Blob Storages');
            }
        }
    }

    public function testListAbsStorages()
    {
        if (self::DISABLE_DEV_TESTS) {
            $this->markTestSkipped('just for development');
        }

        $initCount = count($this->client->listAbsFileStorage());
        $this->client->createAbsFileStorage(self::DEFAULT_ABS_OPTIONS);
        $storages = $this->client->listAbsFileStorage();

        $this->assertSame($initCount + 1, count($storages));

        foreach ($storages as $storage) {
            if ($storage['provider'] === 'aws') {
               $this->fail('List of Azure Blob Storages contains also S3 Storage');
            }
        }
    }

    public function testSetS3StorageAsDefault()
    {
        if (self::DISABLE_DEV_TESTS) {
            $this->markTestSkipped('just for development');
        }

        $storage = $this->client->createS3FileStorage(self::DEFAULT_S3_OPTIONS);

        $this->assertFalse($storage['isDefault']);

        $storage = $this->client->setS3FileStorageAsDefault($storage['id']);

        $this->assertTrue($storage['isDefault']);
    }

    public function testSetAbsStorageAsDefault()
    {
        if (self::DISABLE_DEV_TESTS) {
            $this->markTestSkipped('just for development');
        }

        $storage = $this->client->createAbsFileStorage(self::DEFAULT_ABS_OPTIONS);

        $this->assertFalse($storage['isDefault']);

        $storage = $this->client->setAbsFileStorageAsDefault($storage['id']);

        $this->assertTrue($storage['isDefault']);
    }

    public function testCrossProviderStorageDefaultAbsS3()
    {
        if (self::DISABLE_DEV_TESTS) {
            $this->markTestSkipped('just for development');
        }

        $storage = $this->client->createAbsFileStorage(self::DEFAULT_ABS_OPTIONS);

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage(sprintf('File storage with id "%d" and provider "aws" was not found.', $storage['id']));
        $this->client->setS3FileStorageAsDefault($storage['id']);
    }

    public function testCrossProviderStorageDefaultS3Abs()
    {
        if (self::DISABLE_DEV_TESTS) {
            $this->markTestSkipped('just for development');
        }

        $storage = $this->client->createS3FileStorage(self::DEFAULT_S3_OPTIONS);

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage(sprintf('File storage with id "%d" and provider "azure" was not found.', $storage['id']));
        $this->client->setAbsFileStorageAsDefault($storage['id']);
    }

    public function testCrossProviderStorageCredentialsRotateS3Abs()
    {
        if (self::DISABLE_DEV_TESTS) {
            $this->markTestSkipped('just for development');
        }

        $storage = $this->client->createS3FileStorage(self::DEFAULT_S3_OPTIONS);

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage(sprintf('File storage with id "%d" and provider "azure" was not found.', $storage['id']));
        $this->client->rotateAbsFileStorageCredentials($storage['id'], self::ROTATE_ABS_OPTIONS);
    }

    public function testCrossProviderStorageCredentialsRotateAbsS3()
    {
        if (self::DISABLE_DEV_TESTS) {
            $this->markTestSkipped('just for development');
        }

        $storage = $this->client->createAbsFileStorage(self::DEFAULT_ABS_OPTIONS);

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage(sprintf('File storage with id "%d" and provider "aws" was not found.', $storage['id']));
        $this->client->rotateS3FileStorageCredentials($storage['id'], self::ROTATE_S3_OPTIONS);
    }

    public function testCreateS3StorageWithoutRequiredParam()
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage("Missing parameters in request: 
[awsKey] : This field is missing.
[awsSecret] : This field is missing.
[filesBucket] : This field is missing.
[owner] : This field is missing.
[region] : This field is missing.");
        $this->client->createS3FileStorage([]);
    }

    public function testCreateAbsStorageWithoutRequiredParam()
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage("Missing parameters in request: 
[accountName] : This field is missing.
[accountKey] : This field is missing.
[containerName] : This field is missing.
[owner] : This field is missing.");
        $this->client->createAbsFileStorage([]);
    }

    public function testRotateS3CredentialsWithoutRequiredParams()
    {
        if (self::DISABLE_DEV_TESTS) {
            $this->markTestSkipped('just for development');
        }

        $storage = $this->client->createS3FileStorage(self::DEFAULT_S3_OPTIONS);

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage("Missing parameters in request: 
[awsKey] : This field is missing.
[awsSecret] : This field is missing.");
        $this->client->rotateS3FileStorageCredentials($storage['id'], []);
    }

    public function testRotateAbsCredentialsWithoutRequiredParams()
    {
        if (self::DISABLE_DEV_TESTS) {
            $this->markTestSkipped('just for development');
        }

        $storage = $this->client->createAbsFileStorage(self::DEFAULT_ABS_OPTIONS);

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage("Missing parameters in request: 
[accountKey] : This field is missing.");
        $this->client->rotateAbsFileStorageCredentials($storage['id'], []);
    }

    public function testProjectAssignS3FileStorage()
    {
        if (self::DISABLE_DEV_TESTS) {
            $this->markTestSkipped('just for development');
        }
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

    public function testProjectAssignAbsFileStorage()
    {
        if (self::DISABLE_DEV_TESTS) {
            $this->markTestSkipped('just for development');
        }
        $name = 'My org';
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => $name,
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
        ]);

        $storage = $this->client->createAbsFileStorage(self::DEFAULT_ABS_OPTIONS);

        $this->client->assignFileStorage($project['id'], $storage['id']);

        $project = $this->client->getProject($project['id']);
        $this->assertEquals($storage['id'], $project['fileStorage']['id']);
    }
}