<?php

declare(strict_types=1);

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Client;
use Keboola\ManageApi\ClientException;

class MaintainersMetadataTest extends ClientTestCase
{
    public const TEST_METADATA = [
        [
            'key' => 'test_metadata_key1',
            'value' => 'testval',
        ],
        [
            'key' => 'test.metadata.key2',
            'value' => 'testval2',
        ],
    ];

    public const PROVIDER_USER = 'user';
    public const PROVIDER_SYSTEM = 'system';

    /**
     * @var array
     */
    private $maintainer;

    public function setUp()
    {
        /*
         * $this->client = super-admin
         * $this->normalUserClient = normal admin without any connections yet
         */
        parent::setUp();

        $maintainerPrefix = sprintf('%s - MaintainerMetadata', self::TESTS_MAINTAINER_PREFIX);

        // cleanup maintainers created by tests
        foreach ($this->client->listMaintainers() as $maintainer) {
            if (strpos($maintainer['name'], $maintainerPrefix) === 0) {
                $this->client->deleteMaintainer($maintainer['id']);
            }
        }

        // create default maintainer
        $this->maintainer = $this->client->createMaintainer([
            'name' => sprintf('%s - %s.%s', $maintainerPrefix, date('Ymd.His'), rand(1000, 9999)),
        ]);
    }

    public function providers(): array
    {
        return [
            'system provider' => [
                self::PROVIDER_SYSTEM,
            ],
            'user provider' => [
                self::PROVIDER_USER,
            ],
        ];
    }

    // normal user
    public function testNormalUserCannotManageMetadata(): void
    {
        // User
        $metadata = $this->createUserMetadata($this->client, $this->maintainer['id']);
        $this->cannotManageUserMetadata($this->normalUserClient, $metadata[0]['id']);

        // System
        $this->cannotSetSystemMetadata($this->normalUserClient);

        // superadmin creates system metadata
        $metadataArray = $this->createSystemMetadata($this->client, $this->maintainer['id']);
        // but normal user cannot delete it
        $this->cannotDeleteMetadata($this->normalUserClient, $metadataArray[0]['id']);
    }

    // super admin
    /**
     * @dataProvider providers
     */
    public function testSuperAdminCanManageMetadata(string $provider): void
    {
        // Create
        $this->createMetadata($this->client, $this->maintainer['id'], $provider);

        // Update
        $userMetadata = $this->createMetadata($this->client, $this->maintainer['id'], $provider);
        $this->assertCount(2, $userMetadata);
        $this->validateMetadataEquality(self::TEST_METADATA[1], $userMetadata[0], $provider);
        $this->validateMetadataEquality(self::TEST_METADATA[0], $userMetadata[1], $provider);

        // Get
        $metadataArray = $this->client->listMaintainerMetadata($this->maintainer['id']);
        $this->assertCount(2, $metadataArray);
        $this->validateMetadataEquality(self::TEST_METADATA[1], $metadataArray[0], $provider);
        $this->validateMetadataEquality(self::TEST_METADATA[0], $metadataArray[1], $provider);

        // Delete
        $this->deleteAndCheckMetadata($this->client, $metadataArray[0]['id']);
    }

    // maintainer
    public function testMaintainerAdminCanSeeSystemMetadata(): void
    {
        $this->client->addUserToMaintainer($this->maintainer['id'], ['email' => $this->normalUser['email']]);

        //superadmin creates system metadata
        $this->createSystemMetadata($this->client, $this->maintainer['id']);

        $metadataArray = $this->normalUserClient->listMaintainerMetadata($this->maintainer['id']);
        $this->assertCount(2, $metadataArray);
        $this->validateMetadataEquality(self::TEST_METADATA[1], $metadataArray[0], self::PROVIDER_SYSTEM);
        $this->validateMetadataEquality(self::TEST_METADATA[0], $metadataArray[1], self::PROVIDER_SYSTEM);
    }

    public function testMaintainerAdminCannotManageSystemMetadata(): void
    {
        $this->client->addUserToMaintainer($this->maintainer['id'], ['email' => $this->normalUser['email']]);

        $this->cannotSetSystemMetadata($this->normalUserClient);

        // superadmin creates system metadata
        $metadataArray = $this->createSystemMetadata($this->client, $this->maintainer['id']);
        // but maintainer cannot delete it
        $this->cannotDeleteMetadata($this->normalUserClient, $metadataArray[0]['id']);
    }

    // helpers
    private function createUserMetadata(Client $client, int $maintainerId): array
    {
        return $client->setMaintainerMetadata(
            $maintainerId,
            self::PROVIDER_USER,
            self::TEST_METADATA
        );
    }

    private function createSystemMetadata(Client $client, int $maintainerId): array
    {
        return $client->setMaintainerMetadata(
            $maintainerId,
            self::PROVIDER_SYSTEM,
            self::TEST_METADATA
        );
    }

    private function createMetadata(Client $client, int $maintainerId, $provider): array
    {
        return $client->setMaintainerMetadata(
            $maintainerId,
            $provider,
            self::TEST_METADATA
        );
    }

    private function validateMetadataEquality(array $expected, array $actual, string $provider): void
    {
        foreach ($expected as $key => $value) {
            $this->assertArrayHasKey($key, $actual);
            $this->assertSame($value, $actual[$key]);
        }
        $this->assertEquals($provider, $actual['provider']);
        $this->assertArrayHasKey('timestamp', $actual);
    }

    private function cannotManageUserMetadata(Client $client, $metadataId)
    {
        // note there is no cannotManageSYSTEMMetadata because LIST operation is the same and SET/DELETE operations are tested explicitly
        try {
            $client->listMaintainerMetadata($this->maintainer['id']);
            $this->fail('Test should not reach this line.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        try {
            $client->setMaintainerMetadata($this->maintainer['id'], self::PROVIDER_USER, self::TEST_METADATA);
            $this->fail('Test should not reach this line.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $this->cannotDeleteMetadata($client, $metadataId);
    }

    private function cannotSetSystemMetadata($client)
    {
        try {
            $client->setMaintainerMetadata($this->maintainer['id'], self::PROVIDER_SYSTEM, self::TEST_METADATA);
            $this->fail('Test should not reach this line.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }
    }

    /**
     * deletes a single piece of metadata and expectes that only one is remaining (with expectation that it has started with 2)
     * @param Client $client
     * @param int $metadataId
     */
    private function deleteAndCheckMetadata(Client $client, int $metadataId)
    {
        $client->deleteMaintainerMetadata($this->maintainer['id'], $metadataId);
        $metadataArray = $this->client->listMaintainerMetadata($this->maintainer['id']);
        $this->assertCount(1, $metadataArray);
    }

    private function cannotDeleteMetadata($client, $metadataId)
    {
        try {
            $client->deleteMaintainerMetadata($this->maintainer['id'], $metadataId);
            $this->fail('Test should not reach this line.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }
    }
}
