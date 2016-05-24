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

class StorageBackendTest extends ClientTestCase
{

    public function testStorageBackendCreate()
    {
        $this->markTestSkipped('just for development');
        $backend = $this->client->createStorageBackend([
            'region' => 'us-east-1',
            'backend' => 'mysql',
            'host' => 'rds-devel-a.c97npkkbezqf.eu-west-1.rds.amazonaws.com',
            'username' => '',
            'password' => '',
        ]);
    }

    public function testStorageAssignBackend()
    {
        $this->markTestSkipped('this creates too many RS databses - skip it for');
        // get redshift backend
        $backends = $this->client->listStorageBackend();
        $redshiftBackend = null;
        foreach ($backends as $backend) {
            if ($backend['backend'] == 'redshift') {
                $redshiftBackend = $backend;
                break;
            }
        }
        if (!$redshiftBackend) {
            $this->fail('Redshift backend not found');
        }

        $name = 'My org';
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => $name,
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
        ]);

        $this->assertArrayHasKey('backends', $project);
        $this->assertEquals('mysql', $project['defaultBackend']);

        $backends = $project['backends'];

        $this->assertArrayHasKey('mysql', $backends);
        $this->assertArrayNotHasKey('redshift', $backends);
        
        
        $this->client->assignProjectStorageBackend($project['id'], $redshiftBackend['id']);

        $project = $this->client->getProject($project['id']);
        $backends = $project['backends'];

        $this->assertArrayHasKey('redshift', $backends);
        $this->assertEquals('redshift', $project['defaultBackend']);

        $redshift = $backends['redshift'];
        $this->assertEquals($redshiftBackend['id'], $redshift['id']);

        // let's try to create a bucket in project now

        $token = $this->client->createProjectStorageToken($project['id'], [
            'description' => 'test',
            'expiresIn' => 60,
            'canManageBuckets' => true,
        ]);

        $sapiClient = new \Keboola\StorageApi\Client([
            'url' => getenv('KBC_MANAGE_API_URL'),
            'token' => $token['token'],
        ]);
        $bucketId = $sapiClient->createBucket('test', 'in');
        $bucket = $sapiClient->getBucket($bucketId);
        $this->assertEquals('redshift', $bucket['backend']);
    }

    public function testStorageAssignSnowflakeBackend()
    {
        $this->markTestSkipped('this creates too many SF databses - skip it for');
        // get redshift backend
        $backends = $this->client->listStorageBackend();
        $snowflakeBackend = null;
        foreach ($backends as $backend) {
            if ($backend['backend'] == 'snowflake') {
                $snowflakeBackend = $backend;
                break;
            }
        }
        if (!$snowflakeBackend) {
            $this->fail('Snowflake backend not found');
        }

        $name = 'My org';
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => $name,
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
        ]);

        $this->assertArrayHasKey('backends', $project);
        $this->assertEquals('mysql', $project['defaultBackend']);

        $backends = $project['backends'];

        $this->assertArrayHasKey('mysql', $backends);
        $this->assertArrayNotHasKey('snowflake', $backends);


        $this->client->assignProjectStorageBackend($project['id'], $snowflakeBackend['id']);

        $project = $this->client->getProject($project['id']);
        $backends = $project['backends'];

        $this->assertArrayHasKey('snowflake', $backends);
        $this->assertEquals('snowflake', $project['defaultBackend']);

        $redshift = $backends['snowflake'];
        $this->assertEquals($snowflakeBackend['id'], $redshift['id']);

        // let's try to create a bucket in project now

        $token = $this->client->createProjectStorageToken($project['id'], [
            'description' => 'test',
            'expiresIn' => 60,
            'canManageBuckets' => true,
        ]);

        $sapiClient = new \Keboola\StorageApi\Client([
            'url' => getenv('KBC_MANAGE_API_URL'),
            'token' => $token['token'],
        ]);
        $bucketId = $sapiClient->createBucket('test', 'in');
        $bucket = $sapiClient->getBucket($bucketId);
        $this->assertEquals('snowflake', $bucket['backend']);

    }

    public function testStorageBackendList()
    {
        $backends = $this->client->listStorageBackend();

        $this->assertNotEmpty($backends);

        $backend = reset($backends);
        $this->assertInternalType('int', $backend['id']);
        $this->assertArrayHasKey('host', $backend);
        $this->assertArrayHasKey('backend', $backend);
        $this->assertArrayNotHasKey('login', $backend);
    }

    public function testStorageBackendListWithLogins()
    {
        $backends = $this->client->listStorageBackend([
            'logins' => true,
        ]);

        $this->assertNotEmpty($backends);

        $backend = reset($backends);
        $this->assertInternalType('int', $backend['id']);
        $this->assertArrayHasKey('host', $backend);
        $this->assertArrayHasKey('backend', $backend);
        $this->assertArrayHasKey('login', $backend);
        $this->assertArrayHasKey('region', $backend);
    }

}