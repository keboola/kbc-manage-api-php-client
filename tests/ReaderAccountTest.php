<?php

namespace Keboola\ManageApiTest;

use Keboola\StorageApi\Workspaces;
use Keboola\Test\Backend\Workspaces\Backend\WorkspaceBackendFactory;

class ReaderAccountTest extends ClientTestCase
{
    public function testCreateReaderAccount()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, ['name' => 'RemoveMeOrg']);

        $orgDetail = $this->client->getOrganization($organization['id']);
        $maintainer = $this->client->getMaintainer($orgDetail['maintainer']['id']);
        $this->client->createReaderAccount($organization['id'], $maintainer['defaultConnectionSnowflakeId']);
        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
            'dataRetentionTimeInDays' => 1,
        ]);
        try {
            $this->client->createReaderAccount($organization['id'], $maintainer['defaultConnectionSnowflakeId']);
            $this->fail('Cannot create reader account twice');
        } catch (ClientException $e) {
            $this->assertEquals(sprintf('Reader account for organization with ID "%s" already exists', $organization['id']), $e->getMessage());
        }

        $projectId = $project['id'];
//        $projectId = 120;
        // token without permissions
        $token = $this->client->createProjectStorageToken($projectId, [
            'description' => 'test',
            'expiresIn' => 36000,
        ]);

        $client = $this->getStorageClient([
            'url' => getenv('KBC_MANAGE_API_URL'),
            'token' => $token['token'],
        ]);
        $wsClient = new Workspaces($client);
        $workspace = $wsClient->createWorkspace(['async' => false, 'useCase' => 'reader']);


        $db = WorkspaceBackendFactory::createWorkspaceBackend($workspace);
        $db->executeQuery('select 1');
    }
}
