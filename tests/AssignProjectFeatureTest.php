<?php

declare(strict_types=1);

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;
use Keboola\ManageApi\ProjectRole;

class AssignProjectFeatureTest extends ClientTestCase
{
    private array $organization;

    public function setUp(): void
    {
        parent::setUp();
        $this->cleanupFeatures($this->testFeatureName(), 'project');

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
    }

    public function testNormalUserCannotManageFeatureToProject(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $featureName = $this->testFeatureName();
        $this->client->createFeature(
            $featureName,
            'project',
            $featureName,
            true,
            true
        );

        try {
            $this->normalUserClient->addProjectFeature($projectId, $featureName);
            $this->fail('Normal user can\'t add project features that he doesn\'t belong to.');
        } catch (ClientException $exception) {
            $this->assertStringContainsString('You don\'t have access to project', $exception->getMessage());
            $this->assertSame(403, $exception->getCode());
        }

        $this->client->addProjectFeature($projectId, $featureName);

        try {
            $this->normalUserClient->removeProjectFeature($projectId, $featureName);
            $this->fail('Normal user can\'t remove project features that he doesn\'t belong to.');
        } catch (ClientException $exception) {
            $this->assertStringContainsString('You don\'t have access to project', $exception->getMessage());
            $this->assertSame(403, $exception->getCode());
        }
    }

    /**
     * @dataProvider canBeManageByAdminProvider
     */
    public function testSuperAdminCanManageProjectFeature(bool $canBeManageByAdmin): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember($this->organization['id']);

        $featureName = $this->testFeatureName();
        $this->client->createFeature(
            $featureName,
            'project',
            $featureName,
            $canBeManageByAdmin,
            true
        );

        $this->client->addProjectFeature($projectId, $featureName);

        $project = $this->client->getProject($projectId);

        $this->assertCount(1, $project['features']);
        $this->assertSame($featureName, $project['features'][0]);

        $this->client->removeProjectFeature($projectId, $featureName);

        $project = $this->client->getProject($projectId);

        $this->assertCount(0, $project['features']);
    }

    public function canBeManageByAdminProvider(): array
    {
        return [
            [true],
            [false],
        ];
    }

    public function testSuperAdminCannotManageFeatureCannotBeManagedViaAPI(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember($this->organization['id']);

        $featureName = $this->testFeatureName();
        $feature = $this->client->createFeature(
            $featureName,
            'project',
            $featureName,
            false,
            false
        );

        try {
            $this->client->addProjectFeature($projectId, $featureName);
            $this->fail('The feature "%s" can\'t be added via API');
        } catch (ClientException $exception) {
            $this->assertSame(sprintf('The feature "%s" can\'t be assigned via API', $featureName), $exception->getMessage());
            $this->assertSame(400, $exception->getCode());
        }

        $this->client->updateFeature($feature['id'], [
            'canBeManageByAdmin' => true,
            'canBeManagedViaAPI' => true,
        ]);
        $this->client->addProjectFeature($projectId, $featureName);

        $project = $this->client->getProject($projectId);

        $this->assertCount(1, $project['features']);
        $this->assertSame($featureName, $project['features'][0]);

        $this->client->updateFeature($feature['id'], [
            'canBeManageByAdmin' => true,
            'canBeManagedViaAPI' => false,
        ]);

        try {
            $this->client->removeProjectFeature($projectId, $featureName);
            $this->fail('The feature "%s" can\'t be removed via API');
        } catch (ClientException $exception) {
            $this->assertSame(sprintf('The feature "%s" can\'t be assigned via API', $featureName), $exception->getMessage());
            $this->assertSame(400, $exception->getCode());
        }
    }

    public function testAdminProjectMemberManageFeatureCanBeManageByAdmin()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $this->client->addUserToProject(
            $projectId,
            [
                'email' => $this->normalUser['email'],
                'role' => 'admin',
            ]
        );

        $featureName = $this->testFeatureName();
        $this->client->createFeature(
            $featureName,
            'project',
            $featureName,
            true,
            true
        );

        $this->normalUserClient->addProjectFeature($projectId, $featureName);

        $project = $this->normalUserClient->getProject($projectId);

        $this->assertCount(1, $project['features']);
        $this->assertSame($featureName, $project['features'][0]);

        $this->normalUserClient->removeProjectFeature($projectId, $featureName);

        $project = $this->normalUserClient->getProject($projectId);

        $this->assertCount(0, $project['features']);
    }

    public function testAdminProjectMemberCannotManageFeatureCannotBeManageByAdmin()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $this->client->addUserToProject(
            $projectId,
            [
                'email' => $this->normalUser['email'],
                'role' => 'admin',
            ]
        );

        $featureName = $this->testFeatureName();
        $this->client->createFeature(
            $featureName,
            'project',
            $featureName,
            false,
            true
        );

        try {
            $this->normalUserClient->addProjectFeature($projectId, $featureName);
        } catch (ClientException $e) {
            $this->assertStringContainsString('You can\'t edit project features', $e->getMessage());
            $this->assertSame(403, $e->getCode());
        }

        $this->client->addProjectFeature($projectId, $featureName);

        $project = $this->client->getProject($projectId);

        $this->assertCount(1, $project['features']);
        $this->assertSame($featureName, $project['features'][0]);

        try {
            $this->normalUserClient->removeProjectFeature($projectId, $featureName);
        } catch (ClientException $e) {
            $this->assertStringContainsString('You can\'t edit project features', $e->getMessage());
            $this->assertSame(403, $e->getCode());
        }

        $project = $this->client->getProject($projectId);

        $this->assertCount(1, $project['features']);
        $this->assertSame($featureName, $project['features'][0]);
    }

    public function testProjectMemberCannotManageFeatureCannotBeManagedViaAPI()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $this->client->addUserToProject(
            $projectId,
            [
                'email' => $this->normalUser['email'],
                'role' => 'admin',
            ]
        );

        $featureName = $this->testFeatureName();
        $feature = $this->client->createFeature(
            $featureName,
            'project',
            $featureName,
            true,
            false
        );

        try {
            $this->normalUserClient->addProjectFeature($projectId, $featureName);
            $this->fail('The feature "%s" can\'t be added via API');
        } catch (ClientException $exception) {
            $this->assertStringContainsString(sprintf('The feature "%s" can\'t be assigned via API', $featureName), $exception->getMessage());
            $this->assertSame(400, $exception->getCode());
        }

        $this->client->updateFeature($feature['id'], [
            'canBeManageByAdmin' => true,
            'canBeManagedViaAPI' => true,
        ]);
        $this->client->addProjectFeature($projectId, $featureName);

        $project = $this->client->getProject($projectId);

        $this->assertCount(1, $project['features']);
        $this->assertSame($featureName, $project['features'][0]);

        $this->client->updateFeature($feature['id'], [
            'canBeManageByAdmin' => true,
            'canBeManagedViaAPI' => false,
        ]);

        try {
            $this->normalUserClient->removeProjectFeature($projectId, $featureName);
            $this->fail('The feature "%s" can\'t be assigned via API');
        } catch (ClientException $exception) {
            $this->assertSame(sprintf('The feature "%s" can\'t be assigned via API', $featureName), $exception->getMessage());
            $this->assertSame(400, $exception->getCode());
        }
    }

    /**
     * @dataProvider notAllowedAddFeaturesRoles
     */
    public function testOtherProjectMembersCannotManageFeatureCanBeManageByAdmin(string $role): void
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

        $featureName = $this->testFeatureName();
        $this->client->createFeature(
            $featureName,
            'project',
            $featureName,
            true,
            true
        );

        try {
            $this->normalUserClient->addProjectFeature($projectId, $featureName);
        } catch (ClientException $exception) {
            $this->assertStringContainsString('You can\'t edit project features', $exception->getMessage());
            $this->assertSame(403, $exception->getCode());
        }

        $this->client->addProjectFeature($projectId, $featureName);

        $project = $this->client->getProject($projectId);

        $this->assertCount(1, $project['features']);
        $this->assertSame($featureName, $project['features'][0]);

        try {
            $this->normalUserClient->addProjectFeature($projectId, $featureName);
        } catch (ClientException $exception) {
            $this->assertStringContainsString('You can\'t edit project features', $exception->getMessage());
            $this->assertSame(403, $exception->getCode());
        }

        $project = $this->client->getProject($projectId);

        $this->assertCount(1, $project['features']);
        $this->assertSame($featureName, $project['features'][0]);
    }

    public function notAllowedAddFeaturesRoles(): array
    {
        return [
            'guest' => [
                ProjectRole::GUEST,
            ],
            'share' => [
                ProjectRole::SHARE,
            ],
            'read only' => [
                ProjectRole::READ_ONLY,
            ],
        ];
    }

    public function testMaintainerAdminCannotManageFeatures(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $featureName = $this->testFeatureName();
        $this->client->createFeature(
            $featureName,
            'project',
            $featureName,
            true,
            true
        );

        try {
            $this->normalUserClient->addProjectFeature($projectId, $featureName);
        } catch (ClientException $exception) {
            $this->assertStringContainsString('You can\'t edit project features', $exception->getMessage());
            $this->assertSame(403, $exception->getCode());
        }

        $this->client->addProjectFeature($projectId, $featureName);

        $project = $this->client->getProject($projectId);

        $this->assertCount(1, $project['features']);
        $this->assertSame($featureName, $project['features'][0]);

        try {
            $this->normalUserClient->addProjectFeature($projectId, $featureName);
        } catch (ClientException $exception) {
            $this->assertStringContainsString('You can\'t edit project features', $exception->getMessage());
            $this->assertSame(403, $exception->getCode());
        }

        $project = $this->client->getProject($projectId);

        $this->assertCount(1, $project['features']);
        $this->assertSame($featureName, $project['features'][0]);
    }

    public function testOrgAdminCannotManageFeatures(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $featureName = $this->testFeatureName();
        $this->client->createFeature(
            $featureName,
            'project',
            $featureName,
            true,
            true
        );

        try {
            $this->normalUserClient->addProjectFeature($projectId, $featureName);
        } catch (ClientException $exception) {
            $this->assertStringContainsString('You can\'t edit project features', $exception->getMessage());
            $this->assertSame(403, $exception->getCode());
        }

        $this->client->addProjectFeature($projectId, $featureName);

        $project = $this->client->getProject($projectId);

        $this->assertCount(1, $project['features']);
        $this->assertSame($featureName, $project['features'][0]);

        try {
            $this->normalUserClient->addProjectFeature($projectId, $featureName);
        } catch (ClientException $exception) {
            $this->assertStringContainsString('You can\'t edit project features', $exception->getMessage());
            $this->assertSame(403, $exception->getCode());
        }

        $project = $this->client->getProject($projectId);

        $this->assertCount(1, $project['features']);
        $this->assertSame($featureName, $project['features'][0]);
    }
}
