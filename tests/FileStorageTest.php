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

    public function testFileStorageCreate()
    {
        $this->markTestSkipped('just for development');
        $storage = $this->client->createFileStorage([
           'awsKey' => '',
           'awsSecret' => '',
           'region' => 'eu-central-1',
           'filesBucket' => 'martin-storage-3-s3filesbucket-jodg2rehxtvu',
        ]);

        $this->assertArrayHasKey('awsKey', $storage);
        $this->assertArrayNotHasKey('awsSecret', $storage);
        $this->assertArrayHasKey('region', $storage);
        $this->assertArrayHasKey('filesBucket', $storage);
    }

    public function testProjectAssignFileStorage()
    {
        $this->markTestSkipped('just for development');
        $name = 'My org';
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => $name,
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
        ]);

        $this->client->assignFileStorage($project['id'], 15);

        $project = $this->client->getProject($project['id']);
        $this->assertEquals(15, $project['fileStorage']['id']);
    }

    public function testSetFileStorageAsDefault()
    {
        $this->markTestSkipped('just for development');
        $this->client->setFileStorageAsDefault(17);
        $this->client->setFileStorageAsDefault(20);
    }

    public function testFileStorageList()
    {
        $storageList = $this->client->listFileStorage();

        $this->assertNotEmpty($storageList);

        $storage = reset($storageList);
        $this->assertArrayHasKey('awsKey', $storage);
        $this->assertArrayNotHasKey('awsSecret', $storage);
        $this->assertArrayHasKey('region', $storage);
        $this->assertArrayHasKey('filesBucket', $storage);
        $this->assertArrayHasKey('isDefault', $storage);
        $this->assertInternalType('bool', $storage['isDefault']);
    }

}