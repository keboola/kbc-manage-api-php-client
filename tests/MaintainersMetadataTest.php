<?php

declare(strict_types=1);

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Backend;
use Keboola\ManageApi\Client;
use Keboola\ManageApi\ClientException;
use Keboola\ManageApi\ProjectRole;

final class MaintainersMetadataTest extends ClientTestCase
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

    private array $maintainer;

    public function setUp(): void
    {
        /*
         * $this->client = super-admin
         * $this->normalUserClient = normal admin without any connections yet
         */
        parent::setUp();

        $testMaintainer = $this->client->getMaintainer($this->testMaintainerId);
        // create maintainer group + add super-admin as maintainer
        $this->maintainer = $this->client->createMaintainer([
            'name' => sprintf('%s - MaintainerMetadataTest', self::TESTS_MAINTAINER_PREFIX),
            'defaultConnectionMysqlId' => $testMaintainer['defaultConnectionMysqlId'],
            'defaultConnectionRedshiftId' => $testMaintainer['defaultConnectionRedshiftId'],
            'defaultConnectionSnowflakeId' => $testMaintainer['defaultConnectionSnowflakeId'],
            'defaultFileStorageId' => $testMaintainer['defaultFileStorageId'],
        ]);

        // add dummy user as maintainer admin
        $this->client->addUserToMaintainer($this->maintainer['id'], ['email' => 'devel-tests+spam@keboola.com']);
        // delete super-admin from maintainers
        $this->client->removeUserFromMaintainer($this->maintainer['id'], $this->superAdmin['id']);
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

    public function allProjectRoles(): array
    {
        return [
            'admin' => [
                ProjectRole::ADMIN,
            ],
            'share' => [
                ProjectRole::SHARE,
            ],
            'guest' => [
                ProjectRole::GUEST,
            ],
            'read only' => [
                ProjectRole::READ_ONLY,
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
    /**
     * @dataProvider providers
     */
    public function testMaintainerAdminCanListMetadata(string $provider): void
    {
        // add normal user as maintainer admin
        $this->client->addUserToMaintainer($this->maintainer['id'], ['email' => $this->normalUser['email']]);

        //super-admin creates metadata
        $this->createMetadata($this->client, $this->maintainer['id'], $provider);

        $metadataArray = $this->normalUserClient->listMaintainerMetadata($this->maintainer['id']);
        $this->assertCount(2, $metadataArray);
        $this->validateMetadataEquality(self::TEST_METADATA[1], $metadataArray[0], $provider);
        $this->validateMetadataEquality(self::TEST_METADATA[0], $metadataArray[1], $provider);
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

    public function testMaintainerAdminCanManageUserMetadata(): void
    {
        $this->client->addUserToMaintainer($this->maintainer['id'], ['email' => $this->normalUser['email']]);

        // Create
        $this->createMetadata($this->client, $this->maintainer['id'], self::PROVIDER_USER);

        // Update
        $userMetadata = $this->createMetadata($this->client, $this->maintainer['id'], self::PROVIDER_USER);
        $this->assertCount(2, $userMetadata);
        $this->validateMetadataEquality(self::TEST_METADATA[1], $userMetadata[0], self::PROVIDER_USER);
        $this->validateMetadataEquality(self::TEST_METADATA[0], $userMetadata[1], self::PROVIDER_USER);

        // Get
        $metadataArray = $this->client->listMaintainerMetadata($this->maintainer['id']);
        $this->assertCount(2, $metadataArray);
        $this->validateMetadataEquality(self::TEST_METADATA[1], $metadataArray[0], self::PROVIDER_USER);
        $this->validateMetadataEquality(self::TEST_METADATA[0], $metadataArray[1], self::PROVIDER_USER);

        // Delete
        $this->deleteAndCheckMetadata($this->client, $metadataArray[0]['id']);
    }

    // organization
    public function testOrganizationAdminCannotManageUserMetadata(): void
    {
        // create ogranization + add super-admin as admin
        $organization = $this->client->createOrganization($this->maintainer['id'], [
            'name' => 'My org',
        ]);
        // add normal user as organization admin
        $this->client->addUserToOrganization($organization['id'], ['email' => $this->normalUser['email']]);
        // delete super-admin from organization
        $this->client->removeUserFromOrganization($organization['id'], $this->superAdmin['id']);

        // super-admin creates user metadata
        $metadata = $this->createUserMetadata($this->client, $this->maintainer['id']);
        // organization admin try to manage metadata
        $this->cannotManageUserMetadata($this->normalUserClient, $metadata[0]['id']);
    }

    // project
    /**
     * @dataProvider allProjectRoles
     */
    public function testProjectAdminCannotManageUserMetadata(string $role): void
    {
        // create ogranization + add super-admin as admin
        $organization = $this->client->createOrganization($this->maintainer['id'], [
            'name' => 'My org',
        ]);

        $projectId = $this->createProjectWithSuperAdminMember($organization['id']);

        // add normal user to project with defined role
        $this->client->addUserToProject(
            $projectId,
            [
                'email' => $this->normalUser['email'],
                'role' => $role,
            ]
        );
        // delete super-admin from project
        $this->client->removeUserFromProject($projectId, $this->superAdmin['id']);

        // super-admin creates user metadata
        $metadata = $this->createUserMetadata($this->client, $this->maintainer['id']);
        // organization admin try to manage metadata
        $this->cannotManageUserMetadata($this->normalUserClient, $metadata[0]['id']);
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

    private function createMetadata(Client $client, int $maintainerId, string $provider): array
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

    private function cannotManageUserMetadata(Client $client, $metadataId): void
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

    private function cannotSetSystemMetadata($client): void
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
    private function deleteAndCheckMetadata(Client $client, int $metadataId): void
    {
        $client->deleteMaintainerMetadata($this->maintainer['id'], $metadataId);
        $metadataArray = $this->client->listMaintainerMetadata($this->maintainer['id']);
        $this->assertCount(1, $metadataArray);
    }

    private function cannotDeleteMetadata($client, $metadataId): void
    {
        try {
            $client->deleteMaintainerMetadata($this->maintainer['id'], $metadataId);
            $this->fail('Test should not reach this line.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }
    }
}
