<?php

declare(strict_types=1);

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Client;
use Keboola\ManageApi\ClientException;
use Keboola\ManageApi\ProjectRole;

final class ProjectsMetadataTest extends ClientTestCase
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

    public const SAML_CORRECT_METADATA = [
        [
            'key' => 'KBC.saml.key1',
            'value' => 'testval',
        ],
        [
            'key' => 'KBC.saml.key2',
            'value' => 'testval',
        ],
    ];

    public const SAML_BAD_METADATA = [
        [
            'key' => 'KBC.saml.key1',
            'value' => 'testval',
        ],
        [
            'key' => 'KBC.other.key2',
            'value' => 'testval',
        ],
    ];


    public const PROVIDER_USER = 'user';
    public const PROVIDER_SYSTEM = 'system';

    public const FEATURE_SAML_METADATA_ACCESS = 'saml-metadata-access';

    private $organization;

    public function setUp(): void
    {
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

    public function testNormalUserCannotManageMetadata(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $metadataArray = $this->createUserMetadata($this->client, $projectId);

        try {
            $this->createUserMetadata($this->normalUserClient, $projectId);
            $this->fail('Should fail.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        try {
            $this->createSystemMetadata($this->normalUserClient, $projectId);
            $this->fail('Should fail.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        try {
            $this->normalUserClient->listProjectMetadata($projectId);
            $this->fail('Should fail.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $metadata = reset($metadataArray);

        try {
            $this->normalUserClient->deleteProjectMetadata($projectId, $metadata['id']);
            $this->fail('Should fail.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $this->assertCount(2, $this->client->listProjectMetadata($projectId));
    }

    public function testSuperAdminCanManageMetadataInOrgWithAllowAutoJoinFalse(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember($this->organization['id']);

        $this->normalUserClient->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => false,
        ]);

        $metadata = $this->createUserMetadata($this->client, $projectId);

        $this->assertCount(2, $metadata);

        $this->assertSame('test.metadata.key1', $metadata[0]['key']);
        $this->assertSame('testval', $metadata[0]['value']);
        $this->assertSame('user', $metadata[0]['provider']);

        $this->assertSame('test_metadata_key1', $metadata[1]['key']);
        $this->assertSame('testval', $metadata[1]['value']);
        $this->assertSame('user', $metadata[1]['provider']);

        $metadataArray = $this->client->listProjectMetadata($projectId);
        $this->assertCount(2, $metadataArray);

        $this->assertArrayHasKey('id', $metadataArray[0]);
        $this->assertArrayHasKey('key', $metadataArray[0]);
        $this->assertArrayHasKey('value', $metadataArray[0]);
        $this->assertArrayHasKey('provider', $metadataArray[0]);
        $this->assertArrayHasKey('timestamp', $metadataArray[0]);

        $this->assertSame('test.metadata.key1', $metadataArray[0]['key']);
        $this->assertSame('testval', $metadataArray[0]['value']);
        $this->assertSame('user', $metadataArray[0]['provider']);

        $this->assertSame('test_metadata_key1', $metadataArray[1]['key']);
        $this->assertSame('testval', $metadataArray[1]['value']);
        $this->assertSame('user', $metadataArray[1]['provider']);

        $md = [
            [
                'key' => 'system.key',
                'value' => 'value',
            ],
        ];

        $metadata = $this->client->setProjectMetadata($projectId, self::PROVIDER_SYSTEM, $md);

        $this->assertCount(3, $metadata);

        $systemMetdata = reset($metadata);

        $this->assertSame('system', $systemMetdata['provider']);
        $this->assertSame('user', $metadata[1]['provider']);

        $this->client->deleteProjectMetadata($projectId, $systemMetdata['id']);

        $this->assertCount(2, $this->client->listProjectMetadata($projectId));
    }

    public function testSuperAdminCanManageMetadata(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember($this->organization['id']);

        $metadata = $this->createUserMetadata($this->client, $projectId);

        $this->assertCount(2, $metadata);

        $this->assertSame('test.metadata.key1', $metadata[0]['key']);
        $this->assertSame('testval', $metadata[0]['value']);
        $this->assertSame('user', $metadata[0]['provider']);

        $this->assertSame('test_metadata_key1', $metadata[1]['key']);
        $this->assertSame('testval', $metadata[1]['value']);
        $this->assertSame('user', $metadata[1]['provider']);

        $metadataArray = $this->client->listProjectMetadata($projectId);
        $this->assertCount(2, $metadataArray);

        $this->assertArrayHasKey('id', $metadataArray[0]);
        $this->assertArrayHasKey('key', $metadataArray[0]);
        $this->assertArrayHasKey('value', $metadataArray[0]);
        $this->assertArrayHasKey('provider', $metadataArray[0]);
        $this->assertArrayHasKey('timestamp', $metadataArray[0]);

        $this->assertSame('test.metadata.key1', $metadataArray[0]['key']);
        $this->assertSame('testval', $metadataArray[0]['value']);
        $this->assertSame('user', $metadataArray[0]['provider']);

        $this->assertSame('test_metadata_key1', $metadataArray[1]['key']);
        $this->assertSame('testval', $metadataArray[1]['value']);
        $this->assertSame('user', $metadataArray[1]['provider']);

        $md = [
            [
                'key' => 'system.key',
                'value' => 'value',
            ],
        ];

        $metadata = $this->client->setProjectMetadata($projectId, self::PROVIDER_SYSTEM, $md);

        $this->assertCount(3, $metadata);

        $systemMetadata = reset($metadata);

        $this->assertSame('system', $systemMetadata['provider']);
        $this->assertSame('user', $metadata[1]['provider']);

        $this->client->deleteProjectMetadata($projectId, $systemMetadata['id']);

        $this->assertCount(2, $this->client->listProjectMetadata($projectId));
    }

    // maintainer admin
    public function testMaintainerAdminCanManageUserMetadata(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $this->createUserMetadata($this->normalUserClient, $projectId);

        $this->client->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => false,
        ]);

        $metadata = $this->createUserMetadata($this->normalUserClient, $projectId);

        $this->assertCount(2, $metadata);

        $this->assertSame('test.metadata.key1', $metadata[0]['key']);
        $this->assertSame('testval', $metadata[0]['value']);
        $this->assertSame('user', $metadata[0]['provider']);

        $this->assertSame('test_metadata_key1', $metadata[1]['key']);
        $this->assertSame('testval', $metadata[1]['value']);
        $this->assertSame('user', $metadata[1]['provider']);

        $metadataArray = $this->normalUserClient->listProjectMetadata($projectId);
        $this->assertCount(2, $metadataArray);

        $this->assertArrayHasKey('id', $metadataArray[0]);
        $this->assertArrayHasKey('key', $metadataArray[0]);
        $this->assertArrayHasKey('value', $metadataArray[0]);
        $this->assertArrayHasKey('provider', $metadataArray[0]);
        $this->assertArrayHasKey('timestamp', $metadataArray[0]);

        $this->assertSame('test.metadata.key1', $metadataArray[0]['key']);
        $this->assertSame('testval', $metadataArray[0]['value']);
        $this->assertSame('user', $metadataArray[0]['provider']);

        $this->assertSame('test_metadata_key1', $metadataArray[1]['key']);
        $this->assertSame('testval', $metadataArray[1]['value']);
        $this->assertSame('user', $metadataArray[1]['provider']);

        $metadata = reset($metadataArray);

        $this->normalUserClient->deleteProjectMetadata($projectId, $metadata['id']);

        $this->assertCount(1, $this->normalUserClient->listProjectMetadata($projectId));
    }

    public function testMaintainerAdminWithSamlFeatureCanManageSamlSystemMetadata(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $this->client->addUserFeature($this->normalUser['email'], self::FEATURE_SAML_METADATA_ACCESS);
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        // create
        $this->normalUserClient->setProjectMetadata(
            $projectId,
            self::PROVIDER_SYSTEM,
            self::SAML_CORRECT_METADATA
        );

        $this->client->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => false,
        ]);

        // update
        $metadata = $this->normalUserClient->setProjectMetadata(
            $projectId,
            self::PROVIDER_SYSTEM,
            self::SAML_CORRECT_METADATA
        );

        $this->assertCount(2, $metadata);

        $this->assertSame('KBC.saml.key1', $metadata[0]['key']);
        $this->assertSame('testval', $metadata[0]['value']);
        $this->assertSame('system', $metadata[0]['provider']);

        $this->assertSame('KBC.saml.key2', $metadata[1]['key']);
        $this->assertSame('testval', $metadata[1]['value']);
        $this->assertSame('system', $metadata[1]['provider']);

        // list
        $metadataArray = $this->normalUserClient->listProjectMetadata($projectId);
        $this->assertCount(2, $metadataArray);

        $this->assertArrayHasKey('id', $metadataArray[0]);
        $this->assertArrayHasKey('key', $metadataArray[0]);
        $this->assertArrayHasKey('value', $metadataArray[0]);
        $this->assertArrayHasKey('provider', $metadataArray[0]);
        $this->assertArrayHasKey('timestamp', $metadataArray[0]);

        $this->assertSame('KBC.saml.key1', $metadataArray[0]['key']);
        $this->assertSame('testval', $metadataArray[0]['value']);
        $this->assertSame('system', $metadataArray[0]['provider']);

        $this->assertSame('KBC.saml.key2', $metadataArray[1]['key']);
        $this->assertSame('testval', $metadataArray[1]['value']);
        $this->assertSame('system', $metadataArray[1]['provider']);

        $metadata = reset($metadataArray);

        // delete
        $this->normalUserClient->deleteProjectMetadata($projectId, $metadata['id']);

        $this->assertCount(1, $this->normalUserClient->listProjectMetadata($projectId));
    }

    public function testMaintainerAdminCannotManageSystemMetadata(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $this->client->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => false,
        ]);

        $this->createSystemMetadata($this->client, $projectId);

        $metadataArray = $this->normalUserClient->listProjectMetadata($projectId);
        $this->assertCount(2, $metadataArray);

        $this->assertArrayHasKey('id', $metadataArray[0]);
        $this->assertArrayHasKey('key', $metadataArray[0]);
        $this->assertArrayHasKey('value', $metadataArray[0]);
        $this->assertArrayHasKey('provider', $metadataArray[0]);
        $this->assertArrayHasKey('timestamp', $metadataArray[0]);

        $this->assertSame('test.metadata.key1', $metadataArray[0]['key']);
        $this->assertSame('testval', $metadataArray[0]['value']);
        $this->assertSame('system', $metadataArray[0]['provider']);

        $this->assertSame('test_metadata_key1', $metadataArray[1]['key']);
        $this->assertSame('testval', $metadataArray[1]['value']);
        $this->assertSame('system', $metadataArray[1]['provider']);

        try {
            $this->createSystemMetadata($this->normalUserClient, $projectId);
            $this->fail('Should fail.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $metadata = reset($metadataArray);

        try {
            $this->normalUserClient->deleteProjectMetadata($projectId, $metadata['id']);
            $this->fail('Should fail.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $this->assertCount(2, $this->normalUserClient->listProjectMetadata($projectId));
    }

    // org admin
    public function testOrgAdminCanManageUserMedatata(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $this->createUserMetadata($this->normalUserClient, $projectId);

        $this->client->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => false,
        ]);

        $metadata = $this->createUserMetadata($this->normalUserClient, $projectId);

        $this->assertCount(2, $metadata);

        $this->assertSame('test.metadata.key1', $metadata[0]['key']);
        $this->assertSame('testval', $metadata[0]['value']);
        $this->assertSame('user', $metadata[0]['provider']);

        $this->assertSame('test_metadata_key1', $metadata[1]['key']);
        $this->assertSame('testval', $metadata[1]['value']);
        $this->assertSame('user', $metadata[1]['provider']);

        $metadataArray = $this->normalUserClient->listProjectMetadata($projectId);
        $this->assertCount(2, $metadataArray);

        $this->assertArrayHasKey('id', $metadataArray[0]);
        $this->assertArrayHasKey('key', $metadataArray[0]);
        $this->assertArrayHasKey('value', $metadataArray[0]);
        $this->assertArrayHasKey('provider', $metadataArray[0]);
        $this->assertArrayHasKey('timestamp', $metadataArray[0]);

        $this->assertSame('test.metadata.key1', $metadataArray[0]['key']);
        $this->assertSame('testval', $metadataArray[0]['value']);
        $this->assertSame('user', $metadataArray[0]['provider']);

        $this->assertSame('test_metadata_key1', $metadataArray[1]['key']);
        $this->assertSame('testval', $metadataArray[1]['value']);
        $this->assertSame('user', $metadataArray[1]['provider']);

        $metadata = reset($metadataArray);

        $this->normalUserClient->deleteProjectMetadata($projectId, $metadata['id']);

        $this->assertCount(1, $this->normalUserClient->listProjectMetadata($projectId));
    }

    public function testOrgAdminWithSamlFeatureCanManageSamlSystemMedatata(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $this->client->addUserFeature($this->normalUser['email'], self::FEATURE_SAML_METADATA_ACCESS);
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        // create
        $this->normalUserClient->setProjectMetadata(
            $projectId,
            self::PROVIDER_SYSTEM,
            self::SAML_CORRECT_METADATA
        );

        $this->client->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => false,
        ]);

        // update
        $metadata = $this->normalUserClient->setProjectMetadata(
            $projectId,
            self::PROVIDER_SYSTEM,
            self::SAML_CORRECT_METADATA
        );

        $this->assertCount(2, $metadata);

        $this->assertSame('KBC.saml.key1', $metadata[0]['key']);
        $this->assertSame('testval', $metadata[0]['value']);
        $this->assertSame('system', $metadata[0]['provider']);

        $this->assertSame('KBC.saml.key2', $metadata[1]['key']);
        $this->assertSame('testval', $metadata[1]['value']);
        $this->assertSame('system', $metadata[1]['provider']);

        // list
        $metadataArray = $this->normalUserClient->listProjectMetadata($projectId);
        $this->assertCount(2, $metadataArray);

        $this->assertArrayHasKey('id', $metadataArray[0]);
        $this->assertArrayHasKey('key', $metadataArray[0]);
        $this->assertArrayHasKey('value', $metadataArray[0]);
        $this->assertArrayHasKey('provider', $metadataArray[0]);
        $this->assertArrayHasKey('timestamp', $metadataArray[0]);

        $this->assertSame('KBC.saml.key1', $metadataArray[0]['key']);
        $this->assertSame('testval', $metadataArray[0]['value']);
        $this->assertSame('system', $metadataArray[0]['provider']);

        $this->assertSame('KBC.saml.key2', $metadataArray[1]['key']);
        $this->assertSame('testval', $metadataArray[1]['value']);
        $this->assertSame('system', $metadataArray[1]['provider']);

        $metadata = reset($metadataArray);

        // delete
        $this->normalUserClient->deleteProjectMetadata($projectId, $metadata['id']);

        $this->assertCount(1, $this->normalUserClient->listProjectMetadata($projectId));
    }

    public function testOrgAdminCannotManageSystemMetadata(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $this->createSystemMetadata($this->client, $projectId);

        $this->client->updateOrganization($this->organization['id'], [
            'allowAutoJoin' => false,
        ]);

        $metadataArray = $this->normalUserClient->listProjectMetadata($projectId);
        $this->assertCount(2, $metadataArray);

        $this->assertArrayHasKey('id', $metadataArray[0]);
        $this->assertArrayHasKey('key', $metadataArray[0]);
        $this->assertArrayHasKey('value', $metadataArray[0]);
        $this->assertArrayHasKey('provider', $metadataArray[0]);
        $this->assertArrayHasKey('timestamp', $metadataArray[0]);

        $this->assertSame('test.metadata.key1', $metadataArray[0]['key']);
        $this->assertSame('testval', $metadataArray[0]['value']);
        $this->assertSame('system', $metadataArray[0]['provider']);

        $this->assertSame('test_metadata_key1', $metadataArray[1]['key']);
        $this->assertSame('testval', $metadataArray[1]['value']);
        $this->assertSame('system', $metadataArray[1]['provider']);

        try {
            $this->createSystemMetadata($this->normalUserClient, $projectId);
            $this->fail('Should fail.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $metadata = reset($metadataArray);

        try {
            $this->normalUserClient->deleteProjectMetadata($projectId, $metadata['id']);
            $this->fail('Should fail.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $this->assertCount(2, $this->normalUserClient->listProjectMetadata($projectId));
    }

    // project member
    public function allowedAddMetadataRoles(): \Iterator
    {
        yield 'admin' => [
            ProjectRole::ADMIN,
        ];
        yield 'share' => [
            ProjectRole::SHARE,
        ];
    }

    /**
     * @dataProvider allowedAddMetadataRoles
     */
    public function testProjectMemberCanManageUserMetadata(string $role): void
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

        $metadata = $this->createUserMetadata($this->normalUserClient, $projectId);

        $this->assertCount(2, $metadata);

        $this->assertSame('test.metadata.key1', $metadata[0]['key']);
        $this->assertSame('testval', $metadata[0]['value']);
        $this->assertSame('user', $metadata[0]['provider']);

        $this->assertSame('test_metadata_key1', $metadata[1]['key']);
        $this->assertSame('testval', $metadata[1]['value']);
        $this->assertSame('user', $metadata[1]['provider']);

        $metadataArray = $this->normalUserClient->listProjectMetadata($projectId);
        $this->assertCount(2, $metadataArray);

        $this->assertArrayHasKey('id', $metadataArray[0]);
        $this->assertArrayHasKey('key', $metadataArray[0]);
        $this->assertArrayHasKey('value', $metadataArray[0]);
        $this->assertArrayHasKey('provider', $metadataArray[0]);
        $this->assertArrayHasKey('timestamp', $metadataArray[0]);

        $this->assertSame('test.metadata.key1', $metadataArray[0]['key']);
        $this->assertSame('testval', $metadataArray[0]['value']);
        $this->assertSame('user', $metadataArray[0]['provider']);

        $this->assertSame('test_metadata_key1', $metadataArray[1]['key']);
        $this->assertSame('testval', $metadataArray[1]['value']);
        $this->assertSame('user', $metadataArray[1]['provider']);

        $metadata = reset($metadataArray);

        $this->normalUserClient->deleteProjectMetadata($projectId, $metadata['id']);
        $this->assertCount(1, $this->normalUserClient->listProjectMetadata($projectId));
    }

    /**
     * @dataProvider allowedAddMetadataRoles
     */
    public function testProjectMemeberCannotManageSystemMetadata(string $role): void
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

        $this->createSystemMetadata($this->client, $projectId);

        $metadataArray = $this->normalUserClient->listProjectMetadata($projectId);
        $this->assertCount(2, $metadataArray);

        $this->assertArrayHasKey('id', $metadataArray[0]);
        $this->assertArrayHasKey('key', $metadataArray[0]);
        $this->assertArrayHasKey('value', $metadataArray[0]);
        $this->assertArrayHasKey('provider', $metadataArray[0]);
        $this->assertArrayHasKey('timestamp', $metadataArray[0]);

        $this->assertSame('test.metadata.key1', $metadataArray[0]['key']);
        $this->assertSame('testval', $metadataArray[0]['value']);
        $this->assertSame('system', $metadataArray[0]['provider']);

        $this->assertSame('test_metadata_key1', $metadataArray[1]['key']);
        $this->assertSame('testval', $metadataArray[1]['value']);
        $this->assertSame('system', $metadataArray[1]['provider']);

        try {
            $this->createSystemMetadata($this->normalUserClient, $projectId);
            $this->fail('Should fail.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $metadata = reset($metadataArray);

        try {
            $this->normalUserClient->deleteProjectMetadata($projectId, $metadata['id']);
            $this->fail('Should fail.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $this->assertCount(2, $this->normalUserClient->listProjectMetadata($projectId));
    }

    /**
     * @dataProvider allowedAddMetadataRoles
     */
    public function testProjectMemberWithSamlFeatureCanManageSamlSystemMetadata(string $role): void
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

        // create
        $metadata = $this->normalUserClient->setProjectMetadata(
            $projectId,
            self::PROVIDER_SYSTEM,
            self::SAML_CORRECT_METADATA
        );

        $this->assertCount(2, $metadata);

        $this->assertSame('KBC.saml.key1', $metadata[0]['key']);
        $this->assertSame('testval', $metadata[0]['value']);
        $this->assertSame('system', $metadata[0]['provider']);

        $this->assertSame('KBC.saml.key2', $metadata[1]['key']);
        $this->assertSame('testval', $metadata[1]['value']);
        $this->assertSame('system', $metadata[1]['provider']);

        // list
        $metadataArray = $this->normalUserClient->listProjectMetadata($projectId);
        $this->assertCount(2, $metadataArray);

        $this->assertArrayHasKey('id', $metadataArray[0]);
        $this->assertArrayHasKey('key', $metadataArray[0]);
        $this->assertArrayHasKey('value', $metadataArray[0]);
        $this->assertArrayHasKey('provider', $metadataArray[0]);
        $this->assertArrayHasKey('timestamp', $metadataArray[0]);

        $this->assertSame('KBC.saml.key1', $metadataArray[0]['key']);
        $this->assertSame('testval', $metadataArray[0]['value']);
        $this->assertSame('system', $metadataArray[0]['provider']);

        $this->assertSame('KBC.saml.key2', $metadataArray[1]['key']);
        $this->assertSame('testval', $metadataArray[1]['value']);
        $this->assertSame('system', $metadataArray[1]['provider']);

        $metadata = reset($metadataArray);

        // delete
        $this->normalUserClient->deleteProjectMetadata($projectId, $metadata['id']);
        $this->assertCount(1, $this->normalUserClient->listProjectMetadata($projectId));
    }

    /**
     * @dataProvider allowedAddMetadataRoles
     */
    public function testProjectMemeberWithSamlFeatureCannotManageSamlSystemMetadata(string $role): void
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

        // cannot create
        try {
            $this->normalUserClient->setProjectMetadata(
                $projectId,
                self::PROVIDER_SYSTEM,
                self::SAML_BAD_METADATA
            );
            $this->fail('Should fail.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        // create as super admin
        $this->client->setProjectMetadata(
            $projectId,
            self::PROVIDER_SYSTEM,
            self::SAML_BAD_METADATA
        );

        // can list
        $metadataArray = $this->normalUserClient->listProjectMetadata($projectId);
        $this->assertCount(2, $metadataArray);

        $metadata = reset($metadataArray);

        // cannot delete
        try {
            $this->normalUserClient->deleteProjectMetadata($projectId, $metadata['id']);
            $this->fail('Should fail.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $this->assertCount(2, $this->normalUserClient->listProjectMetadata($projectId));
    }

    public function notAllowedAddMetadataRoles(): \Iterator
    {
        yield 'guest' => [
            ProjectRole::GUEST,
        ];
        yield 'read only' => [
            ProjectRole::READ_ONLY,
        ];
    }

    /**
     * @dataProvider notAllowedAddMetadataRoles
     */
    public function testProjectMemeberCannotManageUserAndSystemMetadata(string $role): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $metadataArray = $this->createUserMetadata($this->client, $projectId);

        $this->client->addUserToProject(
            $projectId,
            [
                'email' => $this->normalUser['email'],
                'role' => $role,
            ]
        );

        try {
            $this->createUserMetadata($this->normalUserClient, $projectId);
            $this->fail('Should fail.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        try {
            $this->createSystemMetadata($this->normalUserClient, $projectId);
            $this->fail('Should fail.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $metadata = reset($metadataArray);

        try {
            $this->normalUserClient->deleteProjectMetadata($projectId, $metadata['id']);
            $this->fail('Should fail.');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }

        $this->assertCount(2, $this->client->listProjectMetadata($projectId));
    }

    public function allowedListMetadataRoles(): \Iterator
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

    /**
     * @dataProvider allowedListMetadataRoles
     */
    public function testProjectMemberCanListUserMetadata(string $role): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $metadataArray = $this->createUserMetadata($this->client, $projectId);

        $this->client->addUserToProject(
            $projectId,
            [
                'email' => $this->normalUser['email'],
                'role' => $role,
            ]
        );

        $this->assertCount(2, $this->normalUserClient->listProjectMetadata($projectId));
    }

    /**
     * @dataProvider allowedListMetadataRoles
     */
    public function testProjectMemberWithoutMfaCannotListUserMetadata(string $role): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $metadataArray = $this->createUserMetadata($this->client, $projectId);

        $this->client->addUserToProject(
            $projectId,
            [
                'email' => $this->normalUser['email'],
                'role' => $role,
            ]
        );

        $this->normalUserWithMfaClient->enableOrganizationMfa($this->organization['id']);

        try {
            $this->normalUserClient->listProjectMetadata($projectId);
            $this->fail('Should fail.');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertSame('This project requires users to have multi-factor authentication enabled', $e->getMessage());
        }
    }

    public function testSuperAdminWithoutMfaCannotManageMetadata(): void
    {
        $projectId = $this->createProjectWithAdminHavingMfaEnabled($this->organization['id']);

        $this->normalUserWithMfaClient->updateOrganization(
            $this->organization['id'],
            [
                'mfaRequired' => 1,
            ]
        );

        $metadata = $this->createUserMetadata($this->normalUserWithMfaClient, $projectId);

        try {
            $this->createUserMetadata($this->client, $projectId);
            $this->fail('Should fail.');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertStringContainsString('This project requires users to have multi-factor authentication enabled', $e->getMessage());
        }

        try {
            $this->createSystemMetadata($this->client, $projectId);
            $this->fail('Should fail.');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertStringContainsString('This project requires users to have multi-factor authentication enabled', $e->getMessage());
        }

        try {
            $this->client->listProjectMetadata($projectId);
            $this->fail('Should fail.');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertStringContainsString('This project requires users to have multi-factor authentication enabled', $e->getMessage());
        }

        $metadata = reset($metadata);

        try {
            $this->client->deleteProjectMetadata($projectId, $metadata['id']);
            $this->fail('Should fail.');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertStringContainsString('This project requires users to have multi-factor authentication enabled', $e->getMessage());
        }

        $this->assertCount(2, $this->normalUserWithMfaClient->listProjectMetadata($projectId));
    }

    public function testMaintainerAdminWithoutMfaCannotManageMetadata(): void
    {
        $projectId = $this->createProjectWithAdminHavingMfaEnabled($this->organization['id']);

        $metadata = $this->createUserMetadata($this->normalUserWithMfaClient, $projectId);

        $this->normalUserWithMfaClient->updateOrganization(
            $this->organization['id'],
            [
                'mfaRequired' => 1,
            ]
        );

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        try {
            $this->createUserMetadata($this->normalUserClient, $projectId);
            $this->fail('Should fail.');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertStringContainsString('This project requires users to have multi-factor authentication enabled', $e->getMessage());
        }

        try {
            $this->normalUserClient->listProjectMetadata($projectId);
            $this->fail('Should fail.');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertStringContainsString('This project requires users to have multi-factor authentication enabled', $e->getMessage());
        }

        $metadata = reset($metadata);

        try {
            $this->normalUserClient->deleteProjectMetadata($projectId, $metadata['id']);
            $this->fail('Should fail.');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertStringContainsString('This project requires users to have multi-factor authentication enabled', $e->getMessage());
        }

        $this->assertCount(2, $this->normalUserWithMfaClient->listProjectMetadata($projectId));
    }

    public function testOrgAdminWithoutMfaCannotManageMetadata(): void
    {
        $projectId = $this->createProjectWithAdminHavingMfaEnabled($this->organization['id']);

        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $metadata = $this->createUserMetadata($this->normalUserWithMfaClient, $projectId);

        $this->normalUserWithMfaClient->enableOrganizationMfa($this->organization['id']);

        try {
            $this->createUserMetadata($this->normalUserClient, $projectId);
            $this->fail('Should fail.');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertStringContainsString('This project requires users to have multi-factor authentication enabled', $e->getMessage());
        }

        try {
            $this->normalUserClient->listProjectMetadata($projectId);
            $this->fail('Should fail.');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertStringContainsString('This project requires users to have multi-factor authentication enabled', $e->getMessage());
        }

        $metadata = reset($metadata);

        try {
            $this->normalUserClient->deleteProjectMetadata($projectId, $metadata['id']);
            $this->fail('Should fail.');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertStringContainsString('This project requires users to have multi-factor authentication enabled', $e->getMessage());
        }

        $this->assertCount(2, $this->normalUserWithMfaClient->listProjectMetadata($projectId));
    }

    /**
     * @dataProvider allowedAddMetadataRoles
     */
    public function testProjectMemberWithoutMfaCannotManageMetadata(string $role): void
    {
        $projectId = $this->createProjectWithAdminHavingMfaEnabled($this->organization['id']);

        $metadata = $this->createUserMetadata($this->normalUserWithMfaClient, $projectId);

        $this->normalUserWithMfaClient->addUserToProject(
            $projectId,
            [
                'email' => $this->normalUser['email'],
                'role' => $role,
            ]
        );

        $this->normalUserWithMfaClient->enableOrganizationMfa($this->organization['id']);

        try {
            $this->createUserMetadata($this->normalUserClient, $projectId);
            $this->fail('Should fail.');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertStringContainsString('This project requires users to have multi-factor authentication enabled', $e->getMessage());
        }

        try {
            $this->normalUserClient->listProjectMetadata($projectId);
            $this->fail('Should fail.');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertStringContainsString('This project requires users to have multi-factor authentication enabled', $e->getMessage());
        }

        $metadata = reset($metadata);

        try {
            $this->normalUserClient->deleteProjectMetadata($projectId, $metadata['id']);
            $this->fail('Should fail.');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertStringContainsString('This project requires users to have multi-factor authentication enabled', $e->getMessage());
        }

        $this->assertCount(2, $this->normalUserWithMfaClient->listProjectMetadata($projectId));
    }

    public function testUpdateProjectMetadata(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember($this->organization['id']);

        $metadata = $this->createUserMetadata($this->client, $projectId);

        $this->assertCount(2, $metadata);

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
        $metadataAfterUpdate = $this->client->setProjectMetadata($projectId, self::PROVIDER_USER, $md1);

        $this->assertCount(2, $metadataAfterUpdate);

        $this->assertSame($metadata[1]['id'], $metadataAfterUpdate[1]['id']);
        $this->assertSame('test_metadata_key1', $metadataAfterUpdate[1]['key']);
        $this->assertSame('updatedtestval', $metadataAfterUpdate[1]['value']);
        $this->assertNotSame($metadata[1]['timestamp'], $metadataAfterUpdate[1]['timestamp']);

        $listMetadata = $this->client->listProjectMetadata($projectId);
        $this->assertCount(2, $listMetadata);

        $this->assertSame($metadata[1]['id'], $listMetadata[1]['id']);
        $this->assertSame('test_metadata_key1', $listMetadata[1]['key']);
        $this->assertSame('updatedtestval', $listMetadata[1]['value']);
        $this->assertNotSame($metadata[1], $listMetadata[1]['timestamp']);

        // add new metadata
        $newMetadata = $this->client->setProjectMetadata($projectId, self::PROVIDER_USER, $md2);
        $this->assertCount(3, $newMetadata);

        $this->assertNotSame($metadata[0]['id'], $newMetadata[2]['id']);
        $this->assertNotSame($metadata[1]['id'], $newMetadata[2]['id']);
        $this->assertSame('test_metadata_key2', $newMetadata[2]['key']);
        $this->assertSame('testval', $newMetadata[2]['value']);

        $listMetadata = $this->client->listProjectMetadata($projectId);
        $this->assertCount(3, $listMetadata);

        $this->assertSame('test_metadata_key1', $listMetadata[1]['key']);
        $this->assertSame('updatedtestval', $listMetadata[1]['value']);

        $this->assertSame('test_metadata_key2', $listMetadata[2]['key']);
        $this->assertSame('testval', $listMetadata[2]['value']);
    }

    // helpers
    private function createUserMetadata(Client $client, int $projectId): array
    {
        return $client->setProjectMetadata(
            $projectId,
            self::PROVIDER_USER,
            self::TEST_METADATA
        );
    }

    private function createSystemMetadata(Client $client, int $projectId): array
    {
        return $client->setProjectMetadata(
            $projectId,
            self::PROVIDER_SYSTEM,
            self::TEST_METADATA
        );
    }
}
