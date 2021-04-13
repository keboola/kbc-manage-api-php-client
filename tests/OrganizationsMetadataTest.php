<?php

declare(strict_types=1);

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Client;
use Keboola\ManageApi\ClientException;

class OrganizationsMetadataTest extends ClientTestCase
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

    private $organization;

    public function setUp()
    {
        /*
         * client = superadmin
         * normalUserClient = normal admin without any connections yet
         */
        parent::setUp();

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => 'spam+spam@keboola.com']);

        foreach ($this->client->listMaintainerMembers($this->testMaintainerId) as $member) {
            if ($member['id'] === $this->normalUser['id']) {
                $this->client->removeUserFromMaintainer($this->testMaintainerId, $member['id']);
            }

            if ($member['id'] === $this->superAdmin['id']) {
                $this->client->removeUserFromMaintainer($this->testMaintainerId, $member['id']);
            }
        }

        $this->organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUserWithMfa['email']]);
        $this->client->removeUserFromOrganization($this->organization['id'], $this->superAdmin['id']);
    }

    public function testNormalUserCanNotManageMetadata(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $organizationId = $this->organization['id'];

        $this->createUserMetadata($this->client, $organizationId);

        // Normal user should not ADD userMetadata
        try {
            $this->createUserMetadata($this->normalUserClient, $organizationId);
            $this->fail('Test should not reach this line.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        // Normal user should not ADD systemMetadata
        try {
            $this->createSystemMetadata($this->normalUserClient, $organizationId);
            $this->fail('Test should not reach this line.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        // Normal user should not SEE org metadata
        try {
            $this->normalUserClient->listOrganizationMetadata($organizationId);
            $this->fail('Test should not reach this line.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }
    }

    public function testSuperAdminCanManageMetadata(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $organizationId = $this->organization['id'];

        $userMetadata = $this->createUserMetadata($this->client, $organizationId);

        $this->assertCount(2, $userMetadata);
        $this->validateMetadataEquality(self::TEST_METADATA[1], $userMetadata[0], self::PROVIDER_USER);
        $this->validateMetadataEquality(self::TEST_METADATA[0], $userMetadata[1], self::PROVIDER_USER);

        $metadataArray = $this->client->listOrganizationMetadata($organizationId);

        $this->assertCount(2, $metadataArray);
        $this->validateMetadataEquality(self::TEST_METADATA[1], $metadataArray[0], self::PROVIDER_USER);
        $this->validateMetadataEquality(self::TEST_METADATA[0], $metadataArray[1], self::PROVIDER_USER);

        // superadmin can manage systemmetadata
        $systemMetadata = $this->createSystemMetadata($this->client, $organizationId);
        $this->assertCount(4, $systemMetadata);

        $this->validateMetadataEquality(self::TEST_METADATA[1], $systemMetadata[0], self::PROVIDER_SYSTEM);
        $this->validateMetadataEquality(self::TEST_METADATA[0], $systemMetadata[1], self::PROVIDER_SYSTEM);
    }

    public function testMaintainerCanManageMetadata(): void
    {
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $metadata = $this->createUserMetadata($this->normalUserClient, $this->organization['id']);
        $this->assertCount(2, $metadata);
        $this->validateMetadataEquality(self::TEST_METADATA[1], $metadata[0], self::PROVIDER_USER);
        $this->validateMetadataEquality(self::TEST_METADATA[0], $metadata[1], self::PROVIDER_USER);

        $metadataArray = $this->normalUserClient->listOrganizationMetadata($this->organization['id']);
        $this->assertCount(2, $metadataArray);
        $this->validateMetadataEquality(self::TEST_METADATA[1], $metadataArray[0], self::PROVIDER_USER);
        $this->validateMetadataEquality(self::TEST_METADATA[0], $metadataArray[1], self::PROVIDER_USER);

        // Maintainer should not manage systemMetadata
        try {
            $this->createSystemMetadata($this->normalUserClient, $this->organization['id']);
            $this->fail('Test should not reach this line.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        // test if user cannot reach metadata when he isnt maintainer anymore
        $this->client->removeUserFromMaintainer($this->testMaintainerId, $this->normalUser['id']);
        try {
            $this->normalUserClient->listOrganizationMetadata($this->organization['id']);
            $this->fail('Test should not reach this line.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }
    }

    public function testOrgAdminCanManageMetadata(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $metadata = $this->createUserMetadata($this->normalUserClient, $this->organization['id']);
        $this->assertCount(2, $metadata);
        $this->validateMetadataEquality(self::TEST_METADATA[1], $metadata[0], self::PROVIDER_USER);
        $this->validateMetadataEquality(self::TEST_METADATA[0], $metadata[1], self::PROVIDER_USER);

        $metadataArray = $this->normalUserClient->listOrganizationMetadata($this->organization['id']);
        $this->assertCount(2, $metadataArray);
        $this->validateMetadataEquality(self::TEST_METADATA[1], $metadataArray[0], self::PROVIDER_USER);
        $this->validateMetadataEquality(self::TEST_METADATA[0], $metadataArray[1], self::PROVIDER_USER);

        // Org admin should not manage systemMetadata
        try {
            $this->createSystemMetadata($this->normalUserClient, $this->organization['id']);
            $this->fail('Test should not reach this line.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        // test if user cannot reach metadata when he isnt org admin anymore
        $this->client->removeUserFromOrganization($this->organization['id'], $this->normalUser['id']);
        try {
            $this->normalUserClient->listOrganizationMetadata($this->organization['id']);
            $this->fail('Test should not reach this line.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }
    }

    public function testProjectAdminCanNotManageMetadata(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $this->createProjectWithNormalAdminMember($this->organization['id'], 'mojemama2');
        $this->client->removeUserFromOrganization($this->organization['id'], $this->normalUser['id']);

        // project admin should not manage systemMetadata
        try {
            $this->createSystemMetadata($this->normalUserClient, $this->organization['id']);
            $this->fail('Test should not reach this line.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        // test if user cannot reach metadata nor even as project admin
        try {
            $this->normalUserClient->listOrganizationMetadata($this->organization['id']);
            $this->fail('Test should not reach this line.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }
    }

    private function createUserMetadata(Client $client, int $organizationId): array
    {
        return $client->setOrganizationMetadata(
            $organizationId,
            self::PROVIDER_USER,
            self::TEST_METADATA
        );
    }

    private function createSystemMetadata(Client $client, int $organizationId): array
    {
        return $client->setOrganizationMetadata(
            $organizationId,
            self::PROVIDER_SYSTEM,
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
}
