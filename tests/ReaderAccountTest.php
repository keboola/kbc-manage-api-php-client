<?php

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

class ReaderAccountTest extends ClientTestCase
{

    public function testCreateReaderAccount()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, ['name' => 'RemoveMeOrg']);

        $orgDetail = $this->client->getOrganization($organization['id']);
        $maintainer = $this->client->getMaintainer($orgDetail['maintainer']['id']);
        $this->client->createReaderAccount($organization['id'], $maintainer['defaultConnectionSnowflakeId']);

        try {
            $this->client->createReaderAccount($organization['id'], $maintainer['defaultConnectionSnowflakeId']);
            $this->fail('Cannot create reader account twice');
        } catch (ClientException $e) {
            $this->assertEquals(sprintf('Reader account for organization with ID "%s" already exists', $organization['id']), $e->getMessage());
        }
    }
}
