<?php

declare(strict_types=1);

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Client;
use Keboola\ManageApi\ClientException;
use Keboola\ManageApi\Exception;
use Keboola\ManageApi\ProjectRole;

final class OrganizationsMetadataTest extends ClientTestCase
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

    public const SAML_CORRECT_METADATA = [
        [
            'key' => 'KBC.saml.key1',
            'value' => 'testval',
        ],
        [
            'key' => 'KBC.saml.key2',
            'value' => 'testval2',
        ],
    ];

    public const SAML_BAD_METADATA = [
        [
            'key' => 'KBC.saml.key1',
            'value' => 'testval',
        ],
        [
            'key' => 'KBC.other.key2',
            'value' => 'testval2',
        ],
    ];


    public const PROVIDER_USER = 'user';
    public const PROVIDER_SYSTEM = 'system';

    public const FEATURE_SAML_METADATA_ACCESS = 'saml-metadata-access';

    private $organization;

    public function setUp(): void
    {
        /*
         * client = superadmin
         * normalUserClient = normal admin without any connections yet
         */
        parent::setUp();

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => 'devel-tests+spam@keboola.com']);

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

        // delete user feature if exists
        $this->client->removeUserFeature($this->normalUser['id'], self::FEATURE_SAML_METADATA_ACCESS);
    }

    public function allProjectRoles(): \Iterator
    {
        yield 'admin' => [
            ProjectRole::ADMIN,
        ];
        yield 'share' => [
            ProjectRole::SHARE,
        ];
        yield 'guest' => [
            ProjectRole::GUEST,
        ];
        yield 'read only' => [
            ProjectRole::READ_ONLY,
        ];
    }

    public function providers(): \Iterator
    {
        yield 'system provider' => [
            self::PROVIDER_SYSTEM,
        ];
        yield 'user provider' => [
            self::PROVIDER_USER,
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
            $this->fail('Should fail');
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
            $this->fail('Should fail');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertSame('This organization requires users to have multi-factor authentication enabled', $e->getMessage());
        }
    }

    public function testMaintainerAdminWithSamlFeatureCanManageSamlSystemMetadata(): void
    {
        $this->client->addUserFeature($this->normalUser['email'], self::FEATURE_SAML_METADATA_ACCESS);
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        // create
        $metadataArray = $this->createSystemMetadata($this->normalUserClient, $this->organization['id'], self::SAML_CORRECT_METADATA);
        $this->assertCount(2, $metadataArray);
        $this->validateMetadataEquality(self::SAML_CORRECT_METADATA[0], $metadataArray[0], self::PROVIDER_SYSTEM);
        $this->validateMetadataEquality(self::SAML_CORRECT_METADATA[1], $metadataArray[1], self::PROVIDER_SYSTEM);

        // list
        $metadataArray = $this->normalUserClient->listOrganizationMetadata($this->organization['id']);
        $this->assertCount(2, $metadataArray);
        $this->validateMetadataEquality(self::SAML_CORRECT_METADATA[0], $metadataArray[0], self::PROVIDER_SYSTEM);
        $this->validateMetadataEquality(self::SAML_CORRECT_METADATA[1], $metadataArray[1], self::PROVIDER_SYSTEM);

        // delete
        $this->deleteAndCheckMetadata($this->normalUserClient, $metadataArray[0]['id']);
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
            $this->fail('Should fail');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertSame('This organization requires users to have multi-factor authentication enabled', $e->getMessage());
        }
    }

    public function testOrgAdminWithSamlFeatureCanManageSamlSystemMetadata(): void
    {
        $this->client->addUserFeature($this->normalUser['email'], self::FEATURE_SAML_METADATA_ACCESS);
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        // create
        $metadataArray = $this->createSystemMetadata($this->normalUserClient, $this->organization['id'], self::SAML_CORRECT_METADATA);
        $this->assertCount(2, $metadataArray);
        $this->validateMetadataEquality(self::SAML_CORRECT_METADATA[0], $metadataArray[0], self::PROVIDER_SYSTEM);
        $this->validateMetadataEquality(self::SAML_CORRECT_METADATA[1], $metadataArray[1], self::PROVIDER_SYSTEM);

        // list
        $metadataArray = $this->normalUserClient->listOrganizationMetadata($this->organization['id']);
        $this->assertCount(2, $metadataArray);
        $this->validateMetadataEquality(self::SAML_CORRECT_METADATA[0], $metadataArray[0], self::PROVIDER_SYSTEM);
        $this->validateMetadataEquality(self::SAML_CORRECT_METADATA[1], $metadataArray[1], self::PROVIDER_SYSTEM);

        // delete
        $this->deleteAndCheckMetadata($this->normalUserClient, $metadataArray[0]['id']);
    }

    public function testOrgAdminWithSamlFeatureCannotManageOtherSystemMetadata(): void
    {
        $this->client->addUserFeature($this->normalUser['email'], self::FEATURE_SAML_METADATA_ACCESS);
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        // org admin cannot set system metadata
        $this->cannotSetSystemMetadata($this->normalUserClient, self::SAML_BAD_METADATA);

        // superadmin creates system metadata
        $metadataArray = $this->createSystemMetadata($this->client, $this->organization['id'], self::SAML_BAD_METADATA);

        // but org admin cannot delete it
        $this->cannotDeleteMetadata($this->normalUserClient, $metadataArray[0]['id']);
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

    /**
     * @dataProvider allProjectRoles
     */
    public function testProjectAdminWithSamlFeatureCanNotManageSamlSystemMetadata(string $role): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $this->client->addUserFeature($this->normalUser['email'], self::FEATURE_SAML_METADATA_ACCESS);
        $this->client->addUserToProject(
            $projectId,
            [
                'email' => $this->normalUser['email'],
                'role' => $role,
            ]
        );

        // user cannot set
        $this->cannotSetSystemMetadata($this->normalUserClient, self::SAML_CORRECT_METADATA);

        // superadmin create
        $systemMetadata = $this->createSystemMetadata($this->client, $this->organization['id'], self::SAML_CORRECT_METADATA);
        // user cannot delete
        $this->cannotDeleteMetadata($this->normalUserClient, $systemMetadata[0]['id']);
    }

    // helpers
    private function createUserMetadata(Client $client, int $organizationId, array $metadata = self::TEST_METADATA): array
    {
        return $client->setOrganizationMetadata(
            $organizationId,
            self::PROVIDER_USER,
            $metadata
        );
    }

    private function createSystemMetadata(Client $client, int $organizationId, array $metadata = self::TEST_METADATA): array
    {
        return $client->setOrganizationMetadata(
            $organizationId,
            self::PROVIDER_SYSTEM,
            $metadata
        );
    }

    private function createMetadata(Client $client, int $organizationId, string $provider, array $metadata = self::TEST_METADATA): array
    {
        return $client->setOrganizationMetadata(
            $organizationId,
            $provider,
            $metadata
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

    private function validateMetadataAsSortedArray(array $expected, array $actual, string $provider): void
    {
        usort($expected, function (array $a, array $b): int {
            return strcmp($a['key'], $b['key']);
        });
        usort($actual, function (array $a, array $b): int {
            return strcmp($a['key'], $b['key']);
        });

        foreach ($expected as $index => $metadata) {
            $this->validateMetadataEquality($metadata, $actual[$index], $provider);
        }
    }

    private function cannotManageUserMetadata(Client $client, $metadataId, array $metadata = self::TEST_METADATA): void
    {
        // note there is no cannotManageSYSTEMMetadata because LIST operation is the same and SET/DELETE operations are tested explicitly
        try {
            $client->listOrganizationMetadata($this->organization['id']);
            $this->fail('Test should not reach this line.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        try {
            $client->setOrganizationMetadata($this->organization['id'], self::PROVIDER_USER, $metadata);
            $this->fail('Test should not reach this line.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $this->cannotDeleteMetadata($client, $metadataId);
    }

    private function cannotManageMetadataBecauseOfMissingMFA($client, array $metadata = self::TEST_METADATA): void
    {
        try {
            $client->listOrganizationMetadata($this->organization['id']);
            $this->fail('Test should not reach this line.');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertSame('This organization requires users to have multi-factor authentication enabled', $e->getMessage());
        }

        try {
            $client->setOrganizationMetadata($this->organization['id'], self::PROVIDER_USER, $metadata);
            $this->fail('Test should not reach this line.');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertSame('This organization requires users to have multi-factor authentication enabled', $e->getMessage());
        }

        try {
            $client->setOrganizationMetadata($this->organization['id'], self::PROVIDER_SYSTEM, $metadata);
            $this->fail('Test should not reach this line.');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertSame('This organization requires users to have multi-factor authentication enabled', $e->getMessage());
        }
    }

    private function cannotSetSystemMetadata($client, array $metadata = self::TEST_METADATA): void
    {
        try {
            $client->setOrganizationMetadata($this->organization['id'], self::PROVIDER_SYSTEM, $metadata);
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
        $client->deleteOrganizationMetadata($this->organization['id'], $metadataId);
        $metadataArray = $this->client->listOrganizationMetadata($this->organization['id']);
        $this->assertCount(1, $metadataArray);
    }

    private function cannotDeleteMetadata($client, $metadataId): void
    {
        try {
            $client->deleteOrganizationMetadata($this->organization['id'], $metadataId);
            $this->fail('Test should not reach this line.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }
    }

    public function testNormalUserCannotSetMetadataWithMaintainerProvider(): void
    {
        try {
            $this->normalUserClient->setOrganizationMetadata($this->organization['id'], 'maintainer', self::TEST_METADATA);
            $this->fail('user who is not maintainer nor org member should not have access to the org metadata');
        } catch (Exception $e) {
            $this->assertSame(sprintf('You don\'t have access to the organization %s', $this->organization['id']), $e->getMessage());
        }
    }

    public function testOrgMemberCannotSetMetadataWithMaintainerProvider(): void
    {
        try {
            $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

            $this->normalUserClient->setOrganizationMetadata($this->organization['id'], 'maintainer', self::TEST_METADATA);
            $this->fail('user who is not maintainer nor org member should not have access to the org metadata');
        } catch (ClientException $e) {
            $this->assertSame(sprintf('You can\'t edit metadata for organization %s with maintainer provider', $this->organization['id']), $e->getMessage());
            $this->assertEquals(403, $e->getCode());
        }
    }

    public function testMaintainerMemberCanSetAndDeleteMetadataWithMaintainerProvider(): void
    {
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $this->normalUserClient->setOrganizationMetadata($this->organization['id'], 'maintainer', self::TEST_METADATA);
        $metadata = $this->normalUserClient->listOrganizationMetadata($this->organization['id']);
        $this->validateMetadataAsSortedArray(self::TEST_METADATA, $metadata, 'maintainer');

        $this->normalUserClient->deleteOrganizationMetadata($this->organization['id'], $metadata[0]['id']);

        $metadata = $this->normalUserClient->listOrganizationMetadata($this->organization['id']);
        $this->validateMetadataEquality(self::TEST_METADATA[0], $metadata[0], 'maintainer');
        $this->assertCount(1, $metadata);
    }

    public function testOrgMemberCannotDeleteMaintainerMetadata(): void
    {
        //setup
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);
        $this->normalUserClient->setOrganizationMetadata($this->organization['id'], 'maintainer', self::TEST_METADATA);
        $metadata = $this->normalUserClient->listOrganizationMetadata($this->organization['id']);
        $this->client->removeUserFromMaintainer($this->testMaintainerId, $this->normalUser['id']);

        // make user member of the org
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        try {
            $this->normalUserClient->deleteOrganizationMetadata($this->organization['id'], $metadata[0]['id']);
            $this->fail('org member but not maintainer member cannot delete maintainer metadata');
        } catch (ClientException $e) {
            $this->assertSame(sprintf('You can\'t edit metadata for organization %s with maintainer provider', $this->organization['id']), $e->getMessage());
            $this->assertEquals(403, $e->getCode());
        }

        // but still can see
        $metadata = $this->normalUserClient->listOrganizationMetadata($this->organization['id']);
        $this->validateMetadataAsSortedArray(self::TEST_METADATA, $metadata, 'maintainer');
        $this->assertCount(2, $metadata);
    }

    public function testNormalUserCannotDeleteMaintainerMetadata(): void
    {
        // setup
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);
        $this->normalUserClient->setOrganizationMetadata($this->organization['id'], 'maintainer', self::TEST_METADATA);
        $metadata = $this->normalUserClient->listOrganizationMetadata($this->organization['id']);
        $this->client->removeUserFromMaintainer($this->testMaintainerId, $this->normalUser['id']);

        try {
            $this->normalUserClient->deleteOrganizationMetadata($this->organization['id'], $metadata[0]['id']);
            $this->fail('org member but not maintainer member cannot delete maintainer metadata');
        } catch (ClientException $e) {
            $this->assertSame(sprintf('You don\'t have access to the organization %s', $this->organization['id']), $e->getMessage());
            $this->assertEquals(403, $e->getCode());
        }
    }
}
