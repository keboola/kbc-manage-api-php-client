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
                $updatedBackend = $this->client->updateStorageBackendBigquery(
                    $backend['id'],
                    [
                        'owner' => 'new-owner',
                    ],
                );

                $this->assertSame('new-owner', $updatedBackend['owner']);
                $updatedBackend = $this->client->updateStorageBackendBigquery(
                    $backend['id'],
                    [
                        'owner' => $oldOwner,
                    ],
                );
                $this->assertSame($oldOwner, $updatedBackend['owner']);
                return;
            }
        }
        $this->fail('No bigquery backend found');
    }
}
