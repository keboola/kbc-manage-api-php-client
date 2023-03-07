<?php

declare(strict_types=1);

namespace Keboola\ManageApiTest;

use Generator;
use Keboola\ManageApi\Client;
use Keboola\ManageApi\ClientException;
use PHPUnit\Framework\ExpectationFailedException;

class UsersMetadataTest extends ClientTestCase
{
    public const TEST_METADATA = [
        [
            'key' => 'test_metadata_key1',
            'value' => 'testval',
        ],
        [
            'key' => 'test.metadata.key1',
            'value' => 'testval',
        ],
    ];
    public const TEST_EXPECTED_METADATA = [
        [
            'key' => 'test_metadata_key1',
            'value' => 'testval',
            'provider' => 'user'
        ],
        [
            'key' => 'test.metadata.key1',
            'value' => 'testval',
            'provider' => 'user'
        ],
    ];
    public const PROVIDER_USER = 'user';
    public const PROVIDER_SYSTEM = 'system';

    public function setUp(): void
    {
        parent::setUp();
        // delete user feature if exists
        foreach ($this->client->listUserMetadata($this->normalUser['id']) as $item) {
            $this->client->deleteUserMetadata(
                $this->normalUser['id'],
                $item['id']
            );
        }
        foreach ($this->client->listUserMetadata($this->superAdmin['id']) as $item) {
            $this->client->deleteUserMetadata(
                $this->superAdmin['id'],
                $item['id']
            );
        }
    }

    public function tokenTypeProvider(): Generator
    {
        yield 'manage token' => [false];

        yield 'session manage token' => [true];
    }

    /**
     * @dataProvider tokenTypeProvider
     */
    public function testNormalUserCannotManageOthersMetadata(bool $useSessionToken): void
    {
        $client = $this->normalUserClient;
        if ($useSessionToken) {
            $client = $this->getSessionTokenClient($client);
        }

        $metadataArray = $this->createUserMetadata($this->client, $this->superAdmin['email']);

        try {
            $this->createUserMetadata($client, $this->superAdmin['email']);
            $this->fail('Should fail with, normal user cannot update other users.');
        } catch (ClientException $e) {
            $this->assertEquals(404, $e->getCode());
        }

        try {
            $this->createSystemMetadata($client, $this->normalUser['email']);
            $this->fail('Should fail with, normal user cannot create system metadata.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        try {
            $client->listUserMetadata($this->superAdmin['email']);
            $this->fail('Should fail, normal user cannot list others metadata.');
        } catch (ClientException $e) {
            $this->assertEquals(404, $e->getCode());
        }

        $metadata = reset($metadataArray);

        try {
            $client->deleteUserMetadata($this->superAdmin['email'], $metadata['id']);
            $this->fail('Should fail. Normal user cannot delete other others metadata.');
        } catch (ClientException $e) {
            $this->assertEquals(404, $e->getCode());
        }

        $this->assertCount(2, $this->client->listUserMetadata($this->superAdmin['email']));
    }

    public function testNormalUserCanManageOwnMetadata(): void
    {
        $metadataArray = $this->createUserMetadata($this->normalUserClient, $this->normalUser['email']);

        $this->assertMetadata($this->normalUserClient, $this->normalUser['email'], $metadataArray);

        $metadata = reset($metadataArray);
        // delete
        $this->normalUserClient->deleteUserMetadata($this->normalUser['email'], $metadata['id']);
        $this->assertCount(1, $this->normalUserClient->listUserMetadata($this->normalUser['email']));
    }

    public function testNormalUserCanManageOwnMetadataWithSessionManageToken(): void
    {
        $sessionTokenClient = $this->getSessionTokenClient($this->normalUserClient);

        $metadataArray = $this->createUserMetadata($sessionTokenClient, $this->normalUser['email']);

        $this->assertMetadata($sessionTokenClient, $this->normalUser['email'], $metadataArray);

        $metadata = reset($metadataArray);
        // delete
        $sessionTokenClient->deleteUserMetadata($this->normalUser['email'], $metadata['id']);
        $this->assertCount(1, $this->normalUserClient->listUserMetadata($this->normalUser['email']));
    }

    public function testSuperAdminCanManageOthersMetadata(): void
    {
        $metadata = $this->createUserMetadata($this->client, $this->normalUser['email']);

        $this->assertMetadata($this->client, $this->normalUser['email'], $metadata);

        $md = [
            [
                'key' => 'system.key',
                'value' => 'value',
            ],
        ];

        $metadata = $this->client->setUserMetadata($this->normalUser['email'], self::PROVIDER_SYSTEM, $md);

        $this->assertCount(3, $metadata);

        $systemMetadata = end($metadata);

        $this->assertSame('system', $systemMetadata['provider']);
        $this->assertSame('user', $metadata[1]['provider']);

        $this->client->deleteUserMetadata($this->superAdmin['email'], $systemMetadata['id']);

        $this->assertCount(2, $this->client->listUserMetadata($this->normalUser['email']));
    }

    public function testUpdateUserMetadata(): void
    {
        $metadata = $this->createUserMetadata($this->client, $this->superAdmin['email']);
        $this->assertCount(2, $metadata);

        $itemKey1 = $this->findMetadataKey($metadata, 'test_metadata_key1');
        $itemKey1Dotted = $this->findMetadataKey($metadata, 'test.metadata.key1');

        $md1 = [
            [
                'key' => 'test_metadata_key1',
                'value' => 'updatedtestval',
            ],
        ];

        $md2 = [
            [
                'key' => 'test_metadata_key2',
                'value' => 'testval',
            ],
        ];

        sleep(1);

        // update existing metadata
        $metadataAfterUpdate = $this->client->setUserMetadata($this->superAdmin['email'], self::PROVIDER_USER, $md1);

        $this->assertCount(2, $metadataAfterUpdate);

        $itemAfterUpdate = $this->findMetadataKey($metadataAfterUpdate, 'test_metadata_key1');
        $itemDottedAfterUpdate = $this->findMetadataKey($metadataAfterUpdate, 'test.metadata.key1');

        $this->assertSame($itemKey1['id'], $itemAfterUpdate['id']);
        $this->assertSame($itemKey1['key'], $itemAfterUpdate['key']);
        $this->assertSame($md1[0]['value'], $itemAfterUpdate['value']);
        $this->assertNotSame($itemKey1['value'], $itemAfterUpdate['value']);
        $this->assertNotSame($itemKey1['timestamp'], $itemAfterUpdate['timestamp']);

        $this->assertSame($itemKey1Dotted['id'], $itemDottedAfterUpdate['id']);
        $this->assertSame($itemKey1Dotted['key'], $itemDottedAfterUpdate['key']);
        $this->assertSame($itemKey1Dotted['value'], $itemDottedAfterUpdate['value']);
        $this->assertSame($itemKey1Dotted['timestamp'], $itemDottedAfterUpdate['timestamp']);

        $listMetadata = $this->client->listUserMetadata($this->superAdmin['email']);
        $this->assertCount(2, $listMetadata);

        $listMetadataItem = $this->findMetadataKey($listMetadata, 'test_metadata_key1');
        $listDotted = $this->findMetadataKey($listMetadata, 'test.metadata.key1');

        $this->assertSame($itemKey1['id'], $listMetadataItem['id']);
        $this->assertSame($itemKey1['key'], $listMetadataItem['key']);
        $this->assertSame($md1[0]['value'], $listMetadataItem['value']);
        $this->assertNotSame($itemKey1['value'], $listMetadataItem['value']);
        $this->assertNotSame($itemKey1, $listMetadataItem['timestamp']);

        $this->assertSame($itemKey1Dotted['id'], $listDotted['id']);
        $this->assertSame($itemKey1Dotted['key'], $listDotted['key']);
        $this->assertSame($itemKey1Dotted['value'], $listDotted['value']);
        $this->assertSame($itemKey1Dotted['timestamp'], $listDotted['timestamp']);

        // add new metadata
        $newMetadata = $this->client->setUserMetadata($this->superAdmin['email'], self::PROVIDER_USER, $md2);
        $this->assertCount(3, $newMetadata);

        $newItemKey1 = $this->findMetadataKey($newMetadata, 'test_metadata_key1');
        $newItemKey1Dotted = $this->findMetadataKey($newMetadata, 'test.metadata.key1');
        $newItemKey2 = $this->findMetadataKey($newMetadata, 'test_metadata_key2');

        $this->assertSame($itemKey1['id'], $newItemKey1['id']);
        $this->assertSame($itemKey1['key'], $newItemKey1['key']);
        $this->assertSame($md1[0]['value'], $newItemKey1['value']);
        $this->assertNotSame($itemKey1['value'], $newItemKey1['value']);
        $this->assertNotSame($itemKey1, $newItemKey1['timestamp']);

        $this->assertSame($itemKey1Dotted['id'], $newItemKey1Dotted['id']);
        $this->assertSame($itemKey1Dotted['key'], $newItemKey1Dotted['key']);
        $this->assertSame($itemKey1Dotted['value'], $newItemKey1Dotted['value']);
        $this->assertSame($itemKey1Dotted['timestamp'], $newItemKey1Dotted['timestamp']);

        $this->assertNotSame($itemKey1['id'], $newItemKey2['id']);
        $this->assertNotSame($itemKey1Dotted['id'], $newItemKey2['id']);
        $this->assertSame($md2[0]['key'], $newItemKey2['key']);
        $this->assertSame($md2[0]['value'], $newItemKey2['value']);

        $listMetadata = $this->client->listUserMetadata($this->superAdmin['email']);
        $this->assertCount(3, $listMetadata);

        $newItemKey1 = $this->findMetadataKey($listMetadata, 'test_metadata_key1');
        $newItemKey1Dotted = $this->findMetadataKey($listMetadata, 'test.metadata.key1');
        $newItemKey2 = $this->findMetadataKey($listMetadata, 'test_metadata_key2');

        $this->assertSame('test_metadata_key1', $newItemKey1['key']);
        $this->assertSame('updatedtestval', $newItemKey1['value']);

        $this->assertSame('test.metadata.key1', $newItemKey1Dotted['key']);
        $this->assertSame('testval', $newItemKey1Dotted['value']);

        $this->assertSame('test_metadata_key2', $newItemKey2['key']);
        $this->assertSame('testval', $newItemKey2['value']);
    }

    // helpers
    private function createUserMetadata(Client $client, string $emailOrId): array
    {
        return $client->setUserMetadata(
            $emailOrId,
            self::PROVIDER_USER,
            self::TEST_METADATA
        );
    }

    private function createSystemMetadata(Client $client, string $emailOrId): array
    {
        return $client->setUserMetadata(
            $emailOrId,
            self::PROVIDER_SYSTEM,
            self::TEST_METADATA
        );
    }

    private function findMetadataKey(array $metadata, string $key): array
    {
        foreach ($metadata as $item) {
            if ($item['key'] === $key) {
                return $item;
            }
        }
        throw new ExpectationFailedException(sprintf('Metadata key: "%s" not found', $key));
    }

    private function assertMetadata(Client $client, string $userEmail, array $metadata): void
    {
        $this->assertCount(2, $metadata);

        $this->assertArrayEqualsSorted(self::TEST_EXPECTED_METADATA, $metadata, 'key');

        $metadataArray = $client->listUserMetadata($userEmail);
        $this->assertCount(2, $metadataArray);

        $this->assertArrayHasKey('id', $metadataArray[0]);
        $this->assertArrayHasKey('key', $metadataArray[0]);
        $this->assertArrayHasKey('value', $metadataArray[0]);
        $this->assertArrayHasKey('provider', $metadataArray[0]);
        $this->assertArrayHasKey('timestamp', $metadataArray[0]);

        $this->assertArrayEqualsSorted(self::TEST_EXPECTED_METADATA, $metadataArray, 'key');

    }
}
