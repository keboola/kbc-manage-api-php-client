<?php

declare(strict_types=1);

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Client;
use Keboola\ManageApi\ClientException;
use Keboola\ManageApi\ProjectRole;

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

    public function testNormalUserCanNotManageMetadata(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $organizationId = $this->organization['id'];

        $metadata = $this->createUserMetadata($this->client, $organizationId);
        $this->cannotManageUserMetadata($this->normalUserClient, $metadata[0]['id']);

        $this->cannotSetSystemMetadata($this->normalUserClient);
        // superadmin creates system metadata
        $metadataArray = $this->createSystemMetadata($this->client, $this->organization['id']);
        // but normal user cannot delete it
        $this->cannotDeleteMetadata($this->normalUserClient, $metadataArray[0]['id']);
    }

    /**
     * @dataProvider providers
     */
    public function testSuperAdminCanManageMetadata(string $provider): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $this->createMetadata($this->client, $this->organization['id'], $provider);

        // trying for allowAutoJoin=false. Metadata should be just updateded
        $this->normalUserClient->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => false,
        ]);
        $userMetadata = $this->createMetadata($this->client, $this->organization['id'], $provider);

        $this->assertCount(2, $userMetadata);
        $this->validateMetadataEquality(self::TEST_METADATA[1], $userMetadata[0], $provider);
        $this->validateMetadataEquality(self::TEST_METADATA[0], $userMetadata[1], $provider);

        $metadataArray = $this->client->listOrganizationMetadata($this->organization['id']);

        $this->assertCount(2, $metadataArray);
        $this->validateMetadataEquality(self::TEST_METADATA[1], $metadataArray[0], $provider);
        $this->validateMetadataEquality(self::TEST_METADATA[0], $metadataArray[1], $provider);

        $this->deleteAndCheckMetadata($this->client, $metadataArray[0]['id']);
    }


    public function testSuperAdminWithoutMFACannotManageMetadata(): void
    {
        $this->normalUserWithMfaClient->enableOrganizationMfa($this->organization['id']);

        $this->cannotManageMetadataBecauseOfMissingMFA($this->client);

        $userMetadata = $this->createMetadata($this->normalUserWithMfaClient, $this->organization['id'], self::PROVIDER_USER);

        try {
            $this->client->deleteOrganizationMetadata($this->organization['id'], $userMetadata[0]['id']);
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertSame('This organization requires users to have multi-factor authentication enabled', $e->getMessage());
        }
    }

    // maintainer
    public function testUserMetadataScenarioForMaintainerAdmin(): void
    {
        // setup maintainar
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);

        // create
        $this->createUserMetadata($this->normalUserClient, $this->organization['id']);
        // trying for allowAutoJoin=false. Metadata should be just updateded
        $this->client->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => false,
        ]);

        // update
        $metadata = $this->createUserMetadata($this->normalUserClient, $this->organization['id']);
        $this->assertCount(2, $metadata);
        $this->validateMetadataEquality(self::TEST_METADATA[1], $metadata[0], self::PROVIDER_USER);
        $this->validateMetadataEquality(self::TEST_METADATA[0], $metadata[1], self::PROVIDER_USER);

        // list
        $metadataArray = $this->normalUserClient->listOrganizationMetadata($this->organization['id']);
        $this->assertCount(2, $metadataArray);
        $this->validateMetadataEquality(self::TEST_METADATA[1], $metadataArray[0], self::PROVIDER_USER);
        $this->validateMetadataEquality(self::TEST_METADATA[0], $metadataArray[1], self::PROVIDER_USER);

        // delete
        $this->deleteAndCheckMetadata($this->normalUserClient, $metadataArray[0]['id']);

        // remove the maintainer and check that no operations are available anymore
        $this->client->removeUserFromMaintainer($this->testMaintainerId, $this->normalUser['id']);
        $this->cannotManageUserMetadata($this->normalUserClient, $metadataArray[1]['id']);
    }

    public function testMaintainerAdminCanSeeSystemMetadata(): void
    {
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        //superadmin creates system metadata
        $this->createSystemMetadata($this->client, $this->organization['id']);

        $metadataArray = $this->normalUserClient->listOrganizationMetadata($this->organization['id']);
        $this->assertCount(2, $metadataArray);
        $this->validateMetadataEquality(self::TEST_METADATA[1], $metadataArray[0], self::PROVIDER_SYSTEM);
        $this->validateMetadataEquality(self::TEST_METADATA[0], $metadataArray[1], self::PROVIDER_SYSTEM);
    }

    public function testMaintainerAdminCannotManageSystemMetadata(): void
    {
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $this->cannotSetSystemMetadata($this->normalUserClient);

        // superadmin creates system metadata
        $metadataArray = $this->createSystemMetadata($this->client, $this->organization['id']);
        // but maintainer cannot delete it
        $this->cannotDeleteMetadata($this->normalUserClient, $metadataArray[0]['id']);
    }

    public function testMaintainerAdminWithoutMFACannotManageMetadata(): void
    {
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $this->normalUserWithMfaClient->enableOrganizationMfa($this->organization['id']);

        $this->cannotManageMetadataBecauseOfMissingMFA($this->normalUserClient);

        $userMetadata = $this->createMetadata($this->normalUserWithMfaClient, $this->organization['id'], self::PROVIDER_USER);

        try {
            $this->normalUserClient->deleteOrganizationMetadata($this->organization['id'], $userMetadata[0]['id']);
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertSame('This organization requires users to have multi-factor authentication enabled', $e->getMessage());
        }
    }

    // org admin
    public function testUserMetadataScenarioForOrgAdmin(): void
    {
        // setup
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        // create
        $metadata = $this->createUserMetadata($this->normalUserClient, $this->organization['id']);
        $this->assertCount(2, $metadata);
        $this->validateMetadataEquality(self::TEST_METADATA[1], $metadata[0], self::PROVIDER_USER);
        $this->validateMetadataEquality(self::TEST_METADATA[0], $metadata[1], self::PROVIDER_USER);

        // list
        $metadataArray = $this->normalUserClient->listOrganizationMetadata($this->organization['id']);
        $this->assertCount(2, $metadataArray);
        $this->validateMetadataEquality(self::TEST_METADATA[1], $metadataArray[0], self::PROVIDER_USER);
        $this->validateMetadataEquality(self::TEST_METADATA[0], $metadataArray[1], self::PROVIDER_USER);

        // delete
        $this->deleteAndCheckMetadata($this->normalUserClient, $metadataArray[0]['id']);

        // remove the permission and check that no operations are available
        $this->client->removeUserFromOrganization($this->organization['id'], $this->normalUser['id']);
        $this->cannotManageUserMetadata($this->normalUserClient, $metadataArray[1]['id']);
    }

    public function testOrgAdminCanSeeSystemMetadata(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        //superadmin creates system metadata
        $this->createSystemMetadata($this->client, $this->organization['id']);

        $metadataArray = $this->normalUserClient->listOrganizationMetadata($this->organization['id']);
        $this->assertCount(2, $metadataArray);
        $this->validateMetadataEquality(self::TEST_METADATA[1], $metadataArray[0], self::PROVIDER_SYSTEM);
        $this->validateMetadataEquality(self::TEST_METADATA[0], $metadataArray[1], self::PROVIDER_SYSTEM);
    }

    public function testOrgAdminCannotManageSystemMetadata(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $this->cannotSetSystemMetadata($this->normalUserClient);

        // superadmin creates system metadata
        $metadataArray = $this->createSystemMetadata($this->client, $this->organization['id']);
        // but org admin cannot delete it
        $this->cannotDeleteMetadata($this->normalUserClient, $metadataArray[0]['id']);
    }

    public function testOrgAdminWithoutMFACannotManageMetadata(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $this->normalUserWithMfaClient->enableOrganizationMfa($this->organization['id']);

        $this->cannotManageMetadataBecauseOfMissingMFA($this->normalUserClient);

        $userMetadata = $this->createMetadata($this->normalUserWithMfaClient, $this->organization['id'], self::PROVIDER_USER);

        try {
            $this->normalUserClient->deleteOrganizationMetadata($this->organization['id'], $userMetadata[0]['id']);
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertSame('This organization requires users to have multi-factor authentication enabled', $e->getMessage());
        }
    }

    // project admin
    /**
     * @dataProvider allProjectRoles
     */
    public function testProjectAdminCanNotManageMetadata(string $role): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $this->client->addUserToProject(
            $projectId,
            [
                'email' => $this->normalUser['email'],
                'role' => $role,
            ]
        );

        // SYSTEM
        $this->cannotSetSystemMetadata($this->normalUserClient);
        $systemMetadata = $this->createSystemMetadata($this->client, $this->organization['id']);
        $this->cannotDeleteMetadata($this->normalUserClient, $systemMetadata[0]['id']);

        // USER
        // superadmin creates user metadata
        $userMetadata = $this->createUserMetadata($this->client, $this->organization['id']);
        // but proj admin cannot manage it
        $this->cannotManageUserMetadata($this->normalUserClient, $userMetadata[0]['id']);
    }

    // helpers
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

    private function createMetadata(Client $client, int $organizationId, $provider): array
    {
        return $client->setOrganizationMetadata(
            $organizationId,
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
            $client->listOrganizationMetadata($this->organization['id']);
            $this->fail('Test should not reach this line.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        try {
            $client->setOrganizationMetadata($this->organization['id'], self::PROVIDER_USER, self::TEST_METADATA);
            $this->fail('Test should not reach this line.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $this->cannotDeleteMetadata($client, $metadataId);
    }

    private function cannotManageMetadataBecauseOfMissingMFA($client)
    {
        try {
            $client->listOrganizationMetadata($this->organization['id']);
            $this->fail('Test should not reach this line.');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertSame('This organization requires users to have multi-factor authentication enabled', $e->getMessage());
        }

        try {
            $client->setOrganizationMetadata($this->organization['id'], self::PROVIDER_USER, self::TEST_METADATA);
            $this->fail('Test should not reach this line.');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertSame('This organization requires users to have multi-factor authentication enabled', $e->getMessage());
        }

        try {
            $client->setOrganizationMetadata($this->organization['id'], self::PROVIDER_SYSTEM, self::TEST_METADATA);
            $this->fail('Test should not reach this line.');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertSame('This organization requires users to have multi-factor authentication enabled', $e->getMessage());
        }
    }

    private function cannotSetSystemMetadata($client)
    {
        try {
            $client->setOrganizationMetadata($this->organization['id'], self::PROVIDER_SYSTEM, self::TEST_METADATA);
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
        $client->deleteOrganizationMetadata($this->organization['id'], $metadataId);
        $metadataArray = $this->client->listOrganizationMetadata($this->organization['id']);
        $this->assertCount(1, $metadataArray);
    }

    private function cannotDeleteMetadata($client, $metadataId)
    {
        try {
            $client->deleteOrganizationMetadata($this->organization['id'], $metadataId);
            $this->fail('Test should not reach this line.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }
    }
}
