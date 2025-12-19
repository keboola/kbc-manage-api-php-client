<?php

declare(strict_types=1);

namespace Keboola\ManageApiTest;

use Generator;
use Iterator;
use Keboola\ManageApi\ClientException;
use Keboola\ManageApi\ProjectRole;

final class AssignProjectFeatureTest extends BaseFeatureTest
{
    public $organization;
    public function setUp(): void
    {
        parent::setUp();
        $this->cleanupFeatures($this->testFeatureName(), 'project');
        $this->client->removeUserFeature($this->normalUser['email'], 'can-manage-features');
    }

    /**
     * @dataProvider provideVariousOfTokensClient
     */
    public function testNormalUserCannotManageFeatureToProject(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $featureName = $this->testFeatureName();
        $newFeature = $this->client->createFeature(
            $featureName,
            'project',
            $featureName,
            $featureName,
            true,
            true
        );

        $normalUserClient = $this->getNormalUserClient();
        $features = $normalUserClient->listFeatures();
        $featureFound = null;

        foreach ($features as $feature) {
            if ($featureName === $feature['name']) {
                $featureFound = $feature;
                break;
            }
        }
        $this->assertSame($featureName, $featureFound['name']);

        $feature = $normalUserClient->getFeature($newFeature['id']);
        $this->assertSame($featureName, $feature['name']);

        try {
            $normalUserClient->addProjectFeature($projectId, $featureName);
            $this->fail('Normal user can\'t add project features that he doesn\'t belong to.');
        } catch (ClientException $exception) {
            $this->assertStringContainsString('You don\'t have access to project', $exception->getMessage());
            $this->assertSame(403, $exception->getCode());
        }

        $this->client->addProjectFeature($projectId, $featureName);

        try {
            $normalUserClient->removeProjectFeature($projectId, $featureName);
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
            $featureName,
            $canBeManageByAdmin,
            true
        );

        $superAdminClient = $this->getSuperAdminClient();
        $features = $superAdminClient->listFeatures();
        $featureFound = null;

        foreach ($features as $feature) {
            if ($featureName === $feature['name']) {
                $featureFound = $feature;
                break;
            }
        }
        $this->assertSame($featureName, $featureFound['name']);

        $feature = $superAdminClient->getFeature($newFeature['id']);
        $this->assertSame($featureName, $feature['name']);

        $superAdminClient->addProjectFeature($projectId, $featureName);

        $project = $this->client->getProject($projectId);

        $this->assertProjectHasFeature($featureName, $project['features']);

        $superAdminClient->removeProjectFeature($projectId, $featureName);

        $project = $this->client->getProject($projectId);

        $this->assertProjectHasNotFeature($featureName, $project['features']);
    }

    /**
     * @dataProvider provideVariousOfTokensClient
     */
    public function testSuperAdminCannotManageFeatureCannotBeManagedViaAPI(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);
        $projectId = $this->createProjectWithNormalAdminMember($this->organization['id']);

        $featureName = $this->testFeatureName();
        $newFeature = $this->client->createFeature(
            $featureName,
            'project',
            $featureName,
            $featureName,
            false,
            false
        );

        $superAdminClient = $this->getSuperAdminClient();
        // but super admin can list it
        $features = $superAdminClient->listFeatures();
        $featureFound = null;

        foreach ($features as $feature) {
            if ($featureName === $feature['name']) {
                $featureFound = $feature;
                break;
            }
        }
        $this->assertSame($featureName, $featureFound['name']);

        $feature = $superAdminClient->getFeature($newFeature['id']);
        $this->assertSame($featureName, $feature['name']);

        try {
            $superAdminClient->addProjectFeature($projectId, $featureName);
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
            $superAdminClient->removeProjectFeature($projectId, $featureName);
            $this->fail('The feature can\'t be removed via API');
        } catch (ClientException $exception) {
            $this->assertSame(sprintf('The feature "%s" can\'t be assigned via API', $featureName), $exception->getMessage());
            $this->assertSame(422, $exception->getCode());
        }
    }

    public function provideAllowedRoleToManageFeature(): Generator
    {
        foreach ($this->provideVariousOfTokensClient() as $token) {
            yield 'admin ' . $token[0] => [
                ProjectRole::ADMIN,
                $token[0],
            ];
            yield 'share ' . $token[0] => [
                ProjectRole::SHARE,
                $token[0],
            ];
        }
    }

    /**
     * @dataProvider provideAllowedRoleToManageFeature
     */
    public function testProjectMemberCanManageFeatureCanBeManageByAdmin(string $role): void
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
            $featureName,
            true,
            true
        );

        $normalUserClient = $this->getNormalUserClient();
        $features = $normalUserClient->listFeatures();
        $featureFound = null;

        foreach ($features as $feature) {
            if ($featureName === $feature['name']) {
                $featureFound = $feature;
                break;
            }
        }
        $this->assertSame($featureName, $featureFound['name']);

        $feature = $normalUserClient->getFeature($newFeature['id']);
        $this->assertSame($featureName, $feature['name']);

        $normalUserClient->addProjectFeature($projectId, $featureName);

        $project = $this->normalUserClient->getProject($projectId);

        $this->assertProjectHasFeature($featureName, $project['features']);

        $normalUserClient->removeProjectFeature($projectId, $featureName);

        $project = $this->normalUserClient->getProject($projectId);

        $this->assertProjectHasNotFeature($featureName, $project['features']);
    }

    /**
     * @dataProvider provideVariousOfTokensClient
     */
    public function testAdminProjectMemberCannotManageFeatureCannotBeManageByAdmin(): void
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
            $featureName,
            false,
            true
        );

        $normalUserClient = $this->getNormalUserClient();
        $features = $normalUserClient->listFeatures();
        $this->assertNotContains($featureName, $features);

        try {
            $normalUserClient->getFeature($newFeature['id']);
            $this->fail('The feature can\'t be get by normal admin');
        } catch (ClientException $e) {
            $this->assertStringContainsString('Feature not found', $e->getMessage());
            $this->assertSame(404, $e->getCode());
        }

        try {
            $normalUserClient->addProjectFeature($projectId, $featureName);
            $this->fail('The feature can\'t be added by normal admin');
        } catch (ClientException $e) {
            $this->assertStringContainsString('You can\'t edit project features', $e->getMessage());
            $this->assertSame(403, $e->getCode());
        }

        $this->client->addProjectFeature($projectId, $featureName);

        $project = $this->client->getProject($projectId);

        $this->assertProjectHasFeature($featureName, $project['features']);

        try {
            $normalUserClient->removeProjectFeature($projectId, $featureName);
            $this->fail('The feature can\'t be removed by normal admin');
        } catch (ClientException $e) {
            $this->assertStringContainsString('You can\'t edit project features', $e->getMessage());
            $this->assertSame(403, $e->getCode());
        }

        $project = $this->client->getProject($projectId);

        $this->assertProjectHasFeature($featureName, $project['features']);
    }

    /**
     * @dataProvider provideVariousOfTokensClient
     */
    public function testProjectMemberCannotManageFeatureCannotBeManagedViaAPI(): void
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
            $featureName,
            true,
            false
        );

        $normalUserClient = $this->getNormalUserClient();
        $features = $normalUserClient->listFeatures();
        $this->assertNotContains($featureName, $features);

        try {
            $normalUserClient->getFeature($newFeature['id']);
            $this->fail('The feature can\'t be get by normal admin');
        } catch (ClientException $e) {
            $this->assertStringContainsString('Feature not found', $e->getMessage());
            $this->assertSame(404, $e->getCode());
        }

        try {
            $normalUserClient->addProjectFeature($projectId, $featureName);
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
            $normalUserClient->removeProjectFeature($projectId, $featureName);
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
            $featureName,
            true,
            true
        );

        $normalUserClient = $this->getNormalUserClient();
        $features = $normalUserClient->listFeatures();
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
            $normalUserClient->addProjectFeature($projectId, $featureName);
            $this->fail('Should fail, only admin role can manage project features');
        } catch (ClientException $exception) {
            $this->assertStringContainsString('You can\'t edit project features', $exception->getMessage());
            $this->assertSame(403, $exception->getCode());
        }

        $this->client->addProjectFeature($projectId, $featureName);

        $project = $this->client->getProject($projectId);

        $this->assertProjectHasFeature($featureName, $project['features']);

        try {
            $normalUserClient->addProjectFeature($projectId, $featureName);
            $this->fail('Should fail, only admin role can manage project features');
        } catch (ClientException $exception) {
            $this->assertStringContainsString('You can\'t edit project features', $exception->getMessage());
            $this->assertSame(403, $exception->getCode());
        }

        $project = $this->client->getProject($projectId);

        $this->assertProjectHasFeature($featureName, $project['features']);
    }

    public function notAllowedAddFeaturesRoles(): Iterator
    {
        yield 'guest manage token' => [
            ProjectRole::GUEST,
            self::MANAGE_TOKEN_CLIENT,
        ];
        yield 'guest session token' => [
            ProjectRole::GUEST,
            self::SESSION_TOKEN_CLIENT,
        ];
        yield 'readOnly manage token' => [
            ProjectRole::READ_ONLY,
            self::MANAGE_TOKEN_CLIENT,
        ];
        yield 'readOnly session token' => [
            ProjectRole::READ_ONLY,
            self::SESSION_TOKEN_CLIENT,
        ];
    }

    /**
     * @dataProvider provideVariousOfTokensClient
     */
    public function testUserWithCanManageFeaturesCanManageFeatures(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $projectId = $this->createProjectWithSuperAdminMember($this->organization['id']);

        $featureName = $this->testFeatureName();
        $newFeature = $this->client->createFeature(
            $featureName,
            'project',
            $featureName,
            $featureName,
            true,
            true
        );

        $normalUserClient = $this->getNormalUserClient();
        $features = $normalUserClient->listFeatures();
        $featureFound = null;

        foreach ($features as $feature) {
            if ($featureName === $feature['name']) {
                $featureFound = $feature;
                break;
            }
        }
        $this->assertSame($featureName, $featureFound['name']);

        $feature = $normalUserClient->getFeature($newFeature['id']);
        $this->assertSame($featureName, $feature['name']);

        try {
            $normalUserClient->addProjectFeature($projectId, $featureName);
            $this->fail('Should fail, only user with can-manage-features can manage project features');
        } catch (ClientException $exception) {
            $this->assertStringContainsString(sprintf("You don't have access to project %s", $projectId), $exception->getMessage());
            $this->assertSame(403, $exception->getCode());
        }

        try {
            $normalUserClient->removeProjectFeature($projectId, $featureName);
            $this->fail('Should fail, only user with can-manage-features can manage project features');
        } catch (ClientException $exception) {
            $this->assertStringContainsString(sprintf("You don't have access to project %s", $projectId), $exception->getMessage());
            $this->assertSame(403, $exception->getCode());
        }

        $this->client->addUserFeature($this->normalUser['email'], 'can-manage-features');

        $normalUserClient->addProjectFeature($projectId, $featureName);
        $project = $this->client->getProject($projectId);
        $this->assertProjectHasFeature($featureName, $project['features']);

        $normalUserClient->removeProjectFeature($projectId, $featureName);
        $project = $this->client->getProject($projectId);
        $this->assertProjectHasNotFeature($featureName, $project['features']);

        $this->client->removeUserFeature($this->normalUser['email'], 'can-manage-features');

        try {
            $normalUserClient->addProjectFeature($projectId, $featureName);
            $this->fail('Should fail, only user with can-manage-features can manage project features');
        } catch (ClientException $exception) {
            $this->assertStringContainsString(sprintf("You don't have access to project %s", $projectId), $exception->getMessage());
            $this->assertSame(403, $exception->getCode());
        }
    }

    /**
     * @dataProvider provideVariousOfTokensClient
     */
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
            $featureName,
            true,
            true
        );

        $normalUserClient = $this->getNormalUserClient();
        $features = $normalUserClient->listFeatures();
        $featureFound = null;

        foreach ($features as $feature) {
            if ($featureName === $feature['name']) {
                $featureFound = $feature;
                break;
            }
        }
        $this->assertSame($featureName, $featureFound['name']);

        $feature = $normalUserClient->getFeature($newFeature['id']);
        $this->assertSame($featureName, $feature['name']);

        try {
            $normalUserClient->addProjectFeature($projectId, $featureName);
            $this->fail('Should fail, only admin in project can manage project features');
        } catch (ClientException $exception) {
            $this->assertStringContainsString('You can\'t edit project features', $exception->getMessage());
            $this->assertSame(403, $exception->getCode());
        }

        $this->client->addProjectFeature($projectId, $featureName);

        $project = $this->client->getProject($projectId);

        $this->assertProjectHasFeature($featureName, $project['features']);

        try {
            $normalUserClient->addProjectFeature($projectId, $featureName);
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
        if (in_array($featureName, $features)) {
            $featureFound = $featureName;
        }
        $this->assertNotNull($featureFound);
    }

    private function assertProjectHasNotFeature(string $featureName, array $features): void
    {
        $featureFound = null;
        if (in_array($featureName, $features)) {
            $featureFound = $featureName;
        }
        $this->assertNull($featureFound);
    }
}
