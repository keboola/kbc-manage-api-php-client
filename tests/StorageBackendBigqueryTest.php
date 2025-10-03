<?php

use Keboola\ManageApiTest\ClientTestCase;

class StorageBackendBigqueryTest extends ClientTestCase
{
    public function testUpdateOwnerOnBigquery(): void
    {
        $backends = $this->client->listStorageBackend();
        foreach ($backends as $backend) {
            if ($backend['backend'] === 'bigquery') {
                $oldOwner = $backend['owner'];
                $oldTechOwner = $backend['technicalOwner'];
                $updatedBackend = $this->client->updateStorageBackendBigquery(
                    $backend['id'],
                    [
                        'owner' => 'new-owner',
                        'technicalOwner' => 'kbdb',
                    ],
                );

                $this->assertSame('new-owner', $updatedBackend['owner']);
                $updatedBackend = $this->client->updateStorageBackendBigquery(
                    $backend['id'],
                    [
                        'owner' => $oldOwner,
                        'technicalOwner' => $oldTechOwner,
                    ],
                );
                $this->assertSame($oldOwner, $updatedBackend['owner']);
                $this->assertSame($oldTechOwner, $updatedBackend['technicalOwner']);
                return;
            }
        }
        $this->fail('No bigquery backend found');
    }
}
