<?php

declare(strict_types=1);

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;
use Keboola\ManageApi\ProjectRole;

class AssignProjectFeatureTest extends BaseFeatureTest
{
    public function setUp(): void
    {
        parent::setUp();
        $this->cleanupFeatures($this->testFeatureName(), 'project');
    }

    public function testNormalUserCannotManageFeatureToProject(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $featureName = $this->testFeatureName();
        $newFeature = $this->client->createFeature(
            $featureName,
            'project',
            $featureName,
            true,
            true
        );

        $features = $this->normalUserClient->listFeatures();
        $featureFound = null;

        foreach ($features as $feature) {
            if ($featureName === $feature['name']) {
                $featureFound = $feature;
                break;
            }
        }
        $this->assertSame($featureName, $featureFound['name']);

        $feature = $this->normalUserClient->getFeature($newFeature['id']);
        $this->assertSame($featureName, $feature['name']);

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
        $newFeature = $this->client->createFeature(
            $featureName,
            'project',
            $featureName,
            $canBeManageByAdmin,
            true
        );

        $features = $this->client->listFeatures();
        $featureFound = null;

        foreach ($features as $feature) {
            if ($featureName === $feature['name']) {
                $featureFound = $feature;
                break;
            }
        }
        $this->assertSame($featureName, $featureFound['name']);

        $feature = $this->client->getFeature($newFeature['id']);
        $this->assertSame($featureName, $feature['name']);

        $this->client->addProjectFeature($projectId, $featureName);

        $project = $this->client->getProject($projectId);

        $this->assertProjectHasFeature($featureName, $project['features']);

        $this->client->removeProjectFeature($projectId, $featureName);

        $project = $this->client->getProject($projectId);

        $this->assertProjectHasNotFeature($featureName, $project['features']);
    }

    public function canBeManageByAdminProvider(): array
    {
        return [
            'admin can manage' => [true],
            'admin cannot manage' => [false],
        ];
    }

    public function testSuperAdminCannotManageFeatureCannotBeManagedViaAPI(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember($this->organization['id']);

        $featureName = $this->testFeatureName();
        $newFeature = $this->client->createFeature(
            $featureName,
            'project',
            $featureName,
            false,
            false
        );

        // but super admin can list it
        $features = $this->client->listFeatures();
        $featureFound = null;

        foreach ($features as $feature) {
            if ($featureName === $feature['name']) {
                $featureFound = $feature;
                break;
            }
        }
        $this->assertSame($featureName, $featureFound['name']);

        $feature = $this->client->getFeature($newFeature['id']);
        $this->assertSame($featureName, $feature['name']);

        try {
            $this->client->addProjectFeature($projectId, $featureName);
            $this->fail('The feature can\'t be added via API');
        } catch (ClientException $exception) {
            $this->assertSame(sprintf('The feature "%s" can\'t be assigned via API', $featureName), $exception->getMessage());
            $this->assertSame(422, $exception->getCode());
        }

        $this->client->updateFeature($feature['id'], [
            'canBeManageByAdmin' => true,
            'canBeManagedViaAPI' => true,
        ]);
        $this->client->addProjectFeature($projectId, $featureName);

        $project = $this->client->getProject($projectId);

        $this->assertProjectHasFeature($featureName, $project['features']);

        $this->client->updateFeature($feature['id'], [
            'canBeManageByAdmin' => true,
            'canBeManagedViaAPI' => false,
        ]);

        try {
            $this->client->removeProjectFeature($projectId, $featureName);
            $this->fail('The feature can\'t be removed via API');
        } catch (ClientException $exception) {
            $this->assertSame(sprintf('The feature "%s" can\'t be assigned via API', $featureName), $exception->getMessage());
            $this->assertSame(422, $exception->getCode());
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
        $newFeature = $this->client->createFeature(
            $featureName,
            'project',
            $featureName,
            true,
            true
        );

        $features = $this->normalUserClient->listFeatures();
        $featureFound = null;

        foreach ($features as $feature) {
            if ($featureName === $feature['name']) {
                $featureFound = $feature;
                break;
            }
        }
        $this->assertSame($featureName, $featureFound['name']);

        $feature = $this->normalUserClient->getFeature($newFeature['id']);
        $this->assertSame($featureName, $feature['name']);

        $this->normalUserClient->addProjectFeature($projectId, $featureName);

        $project = $this->normalUserClient->getProject($projectId);

        $this->assertProjectHasFeature($featureName, $project['features']);

        $this->normalUserClient->removeProjectFeature($projectId, $featureName);

        $project = $this->normalUserClient->getProject($projectId);

        $this->assertProjectHasNotFeature($featureName, $project['features']);
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
        $newFeature = $this->client->createFeature(
            $featureName,
            'project',
            $featureName,
            false,
            true
        );

        $features = $this->normalUserClient->listFeatures();
        $this->assertNotContains($featureName, $features);

        try {
            $this->normalUserClient->getFeature($newFeature['id']);
            $this->fail('The feature can\'t be get by normal admin');
        } catch (ClientException $e) {
            $this->assertStringContainsString('Feature not found', $e->getMessage());
            $this->assertSame(404, $e->getCode());
        }

        try {
            $this->normalUserClient->addProjectFeature($projectId, $featureName);
            $this->fail('The feature can\'t be added by normal admin');
        } catch (ClientException $e) {
            $this->assertStringContainsString('You can\'t edit project features', $e->getMessage());
            $this->assertSame(403, $e->getCode());
        }

        $this->client->addProjectFeature($projectId, $featureName);

        $project = $this->client->getProject($projectId);

        $this->assertProjectHasFeature($featureName, $project['features']);

        try {
            $this->normalUserClient->removeProjectFeature($projectId, $featureName);
            $this->fail('The feature can\'t be removed by normal admin');
        } catch (ClientException $e) {
            $this->assertStringContainsString('You can\'t edit project features', $e->getMessage());
            $this->assertSame(403, $e->getCode());
        }

        $project = $this->client->getProject($projectId);

        $this->assertProjectHasFeature($featureName, $project['features']);
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
        $newFeature = $feature = $this->client->createFeature(
            $featureName,
            'project',
            $featureName,
            true,
            false
        );

        $features = $this->normalUserClient->listFeatures();
        $this->assertNotContains($featureName, $features);

        try {
            $this->normalUserClient->getFeature($newFeature['id']);
            $this->fail('The feature can\'t be get by normal admin');
        } catch (ClientException $e) {
            $this->assertStringContainsString('Feature not found', $e->getMessage());
            $this->assertSame(404, $e->getCode());
        }

        try {
            $this->normalUserClient->addProjectFeature($projectId, $featureName);
            $this->fail('The feature can\'t be added via API');
        } catch (ClientException $exception) {
            $this->assertStringContainsString(sprintf('The feature "%s" can\'t be assigned via API', $featureName), $exception->getMessage());
            $this->assertSame(422, $exception->getCode());
        }

        $this->client->updateFeature($feature['id'], [
            'canBeManageByAdmin' => true,
            'canBeManagedViaAPI' => true,
        ]);
        $this->client->addProjectFeature($projectId, $featureName);

        $project = $this->client->getProject($projectId);

        $this->assertProjectHasFeature($featureName, $project['features']);

        $this->client->updateFeature($feature['id'], [
            'canBeManageByAdmin' => true,
            'canBeManagedViaAPI' => false,
        ]);

        try {
            $this->normalUserClient->removeProjectFeature($projectId, $featureName);
            $this->fail('The feature can\'t be assigned via API');
        } catch (ClientException $exception) {
            $this->assertSame(sprintf('The feature "%s" can\'t be assigned via API', $featureName), $exception->getMessage());
            $this->assertSame(422, $exception->getCode());
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
        $newFeature = $this->client->createFeature(
            $featureName,
            'project',
            $featureName,
            true,
            true
        );

        $features = $this->normalUserClient->listFeatures();
        $featureFound = null;

        foreach ($features as $feature) {
            if ($featureName === $feature['name']) {
                $featureFound = $feature;
                break;
            }
        }
        $this->assertSame($featureName, $featureFound['name']);

        $feature = $this->normalUserClient->getFeature($newFeature['id']);
        $this->assertSame($featureName, $feature['name']);

        try {
            $this->normalUserClient->addProjectFeature($projectId, $featureName);
            $this->fail('Should fail, only admin role can manage project features');
        } catch (ClientException $exception) {
            $this->assertStringContainsString('You can\'t edit project features', $exception->getMessage());
            $this->assertSame(403, $exception->getCode());
        }

        $this->client->addProjectFeature($projectId, $featureName);

        $project = $this->client->getProject($projectId);

        $this->assertProjectHasFeature($featureName, $project['features']);

        try {
            $this->normalUserClient->addProjectFeature($projectId, $featureName);
            $this->fail('Should fail, only admin role can manage project features');
        } catch (ClientException $exception) {
            $this->assertStringContainsString('You can\'t edit project features', $exception->getMessage());
            $this->assertSame(403, $exception->getCode());
        }

        $project = $this->client->getProject($projectId);

        $this->assertProjectHasFeature($featureName, $project['features']);
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
        ];
    }

    public function testMaintainerAdminCannotManageFeatures(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $featureName = $this->testFeatureName();
        $newFeature = $this->client->createFeature(
            $featureName,
            'project',
            $featureName,
            true,
            true
        );

        $features = $this->normalUserClient->listFeatures();
        $featureFound = null;

        foreach ($features as $feature) {
            if ($featureName === $feature['name']) {
                $featureFound = $feature;
                break;
            }
        }
        $this->assertSame($featureName, $featureFound['name']);

        $feature = $this->normalUserClient->getFeature($newFeature['id']);
        $this->assertSame($featureName, $feature['name']);

        try {
            $this->normalUserClient->addProjectFeature($projectId, $featureName);
            $this->fail('Should fail, only admin in project can manage project features');
        } catch (ClientException $exception) {
            $this->assertStringContainsString('You can\'t edit project features', $exception->getMessage());
            $this->assertSame(403, $exception->getCode());
        }

        $this->client->addProjectFeature($projectId, $featureName);

        $project = $this->client->getProject($projectId);

        $this->assertProjectHasFeature($featureName, $project['features']);

        try {
            $this->normalUserClient->addProjectFeature($projectId, $featureName);
            $this->fail('Should fail, only admin in project can manage project features');
        } catch (ClientException $exception) {
            $this->assertStringContainsString('You can\'t edit project features', $exception->getMessage());
            $this->assertSame(403, $exception->getCode());
        }

        $project = $this->client->getProject($projectId);

        $this->assertProjectHasFeature($featureName, $project['features']);
    }

    public function testOrgAdminCannotManageFeatures(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $featureName = $this->testFeatureName();
        $newFeature = $this->client->createFeature(
            $featureName,
            'project',
            $featureName,
            true,
            true
        );

        $features = $this->normalUserClient->listFeatures();
        $featureFound = null;

        foreach ($features as $feature) {
            if ($featureName === $feature['name']) {
                $featureFound = $feature;
                break;
            }
        }
        $this->assertSame($featureName, $featureFound['name']);

        $feature = $this->normalUserClient->getFeature($newFeature['id']);
        $this->assertSame($featureName, $feature['name']);

        try {
            $this->normalUserClient->addProjectFeature($projectId, $featureName);
            $this->fail('Should fail, only admin in project can manage project features');
        } catch (ClientException $exception) {
            $this->assertStringContainsString('You can\'t edit project features', $exception->getMessage());
            $this->assertSame(403, $exception->getCode());
        }

        $this->client->addProjectFeature($projectId, $featureName);

        $project = $this->client->getProject($projectId);

        $this->assertProjectHasFeature($featureName, $project['features']);

        try {
            $this->normalUserClient->addProjectFeature($projectId, $featureName);
            $this->fail('Should fail, only admin in project can manage project features');
        } catch (ClientException $exception) {
            $this->assertStringContainsString('You can\'t edit project features', $exception->getMessage());
            $this->assertSame(403, $exception->getCode());
        }

        $project = $this->client->getProject($projectId);

        $this->assertProjectHasFeature($featureName, $project['features']);
    }

    private function assertProjectHasFeature(string $featureName, array $features): void
    {
        $featureFound = null;
        if (array_search($featureName, $features) !== false) {
            $featureFound = $featureName;
        }
        $this->assertNotNull($featureFound);
    }

    private function assertProjectHasNotFeature(string $featureName, array $features): void
    {
        $featureFound = null;
        if (array_search($featureName, $features) !== false) {
            $featureFound = $featureName;
        }
        $this->assertNull($featureFound);
    }
}
