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

        $this->createUserMetadata($this->client, $organizationId);

        $this->cannotManageUserMetadata($this->normalUserClient);
        $this->cannotManageSystemMetadata($this->normalUserClient);
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

        $this->deleteMetadata($this->client, $metadataArray);
    }


    public function testSuperAdminWithoutMFACannotManageMetadata(): void
    {
        $this->normalUserWithMfaClient->enableOrganizationMfa($this->organization['id']);

        $this->cannotManageMetadataBecauseOfMissingMFA($this->client);
    }

    // maintainer
    public function testMaintainerAdminCanManageUserMetadata(): void
    {
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);

        $this->createUserMetadata($this->normalUserClient, $this->organization['id']);

        // trying for allowAutoJoin=false. Metadata should be just updateded
        $this->client->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => false,
        ]);
        $metadata = $this->createUserMetadata($this->normalUserClient, $this->organization['id']);

        $this->assertCount(2, $metadata);
        $this->validateMetadataEquality(self::TEST_METADATA[1], $metadata[0], self::PROVIDER_USER);
        $this->validateMetadataEquality(self::TEST_METADATA[0], $metadata[1], self::PROVIDER_USER);

        $metadataArray = $this->normalUserClient->listOrganizationMetadata($this->organization['id']);
        $this->assertCount(2, $metadataArray);
        $this->validateMetadataEquality(self::TEST_METADATA[1], $metadataArray[0], self::PROVIDER_USER);
        $this->validateMetadataEquality(self::TEST_METADATA[0], $metadataArray[1], self::PROVIDER_USER);

        $this->client->removeUserFromMaintainer($this->testMaintainerId, $this->normalUser['id']);

        $this->cannotManageUserMetadata($this->normalUserClient, $metadataArray[0]['id']);

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);
        $this->deleteMetadata($this->normalUserClient, $metadataArray);
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

        $this->cannotManageSystemMetadata($this->normalUserClient);

        // superadmin creates system metadata
        $this->createSystemMetadata($this->client, $this->organization['id']);
        $metadataArray = $this->normalUserClient->listOrganizationMetadata($this->organization['id']);
        // but maintainer cannot delete it
        $this->cannotDeleteMetadata($this->normalUserClient, $metadataArray[0]['id']);
    }

    public function testMaintainerAdminWithoutMFACannotManageMetadata(): void
    {
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $this->normalUserWithMfaClient->enableOrganizationMfa($this->organization['id']);

        $this->cannotManageMetadataBecauseOfMissingMFA($this->normalUserClient);
    }

    // org admin
    public function testOrgAdminCanManageUserMetadata(): void
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

        $this->client->removeUserFromOrganization($this->organization['id'], $this->normalUser['id']);

        $this->cannotManageUserMetadata($this->normalUserClient);
        $this->cannotDeleteMetadata($this->normalUserClient, $metadataArray[0]['id']);

        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $this->deleteMetadata($this->normalUserClient, $metadataArray);
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

        $this->cannotManageSystemMetadata($this->normalUserClient);

        // superadmin creates system metadata
        $this->createSystemMetadata($this->client, $this->organization['id']);
        $metadataArray = $this->normalUserClient->listOrganizationMetadata($this->organization['id']);
        // but org admin cannot delete it
        $this->cannotDeleteMetadata($this->normalUserClient, $metadataArray[0]['id']);
    }

    public function testOrgAdminWithoutMFACannotManageMetadata(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $this->normalUserWithMfaClient->enableOrganizationMfa($this->organization['id']);

        $this->cannotManageMetadataBecauseOfMissingMFA($this->normalUserClient);
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

        $this->cannotManageUserMetadata($this->normalUserClient);
        $this->cannotManageSystemMetadata($this->normalUserClient);


        // superadmin creates user metadata
        $this->createUserMetadata($this->client, $this->organization['id']);
        $metadataArray = $this->client->listOrganizationMetadata($this->organization['id']);
        // but proj admin cannot delete it
        $this->cannotDeleteMetadata($this->normalUserClient, $metadataArray[0]['id']);
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

    private function cannotManageUserMetadata(Client $client, $metadataId = null)
    {
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

        if($metadataId){
            $this->cannotDeleteMetadata($client, $metadataId);
        }
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

    private function cannotManageSystemMetadata($client)
    {
        try {
            $client->setOrganizationMetadata($this->organization['id'], self::PROVIDER_SYSTEM, self::TEST_METADATA);
            $this->fail('Test should not reach this line.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }
    }

    private function deleteMetadata(Client $client, $metadataArray)
    {
        foreach($metadataArray as $metadata){
            $client->deleteOrganizationMetadata($this->organization['id'], $metadata['id']);
        }
        $metadataArray = $this->client->listOrganizationMetadata($this->organization['id']);
        $this->assertCount(0, $metadataArray);
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
