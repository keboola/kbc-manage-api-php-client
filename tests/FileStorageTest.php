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

    public function testFileStorageList()
    {
        $storageList = $this->client->listFileStorage();

        $this->assertNotEmpty($storageList);

        $storage = reset($storageList);
        $this->assertArrayHasKey('awsKey', $storage);
        $this->assertArrayNotHasKey('awsSecret', $storage);
        $this->assertArrayHasKey('region', $storage);
        $this->assertArrayHasKey('filesBucket', $storage);
    }

}