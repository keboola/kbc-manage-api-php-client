<?php

declare(strict_types=1);

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

final class AssignAdminFeatureTest extends BaseFeatureTest
{
    public function setUp(): void
    {
        parent::setUp();
        $this->cleanupFeatures($this->testFeatureName(), 'admin');
    }

    /**
     * @dataProvider canBeManageByAdminProvider
     */
    public function testSuperAdminCanManageAdminFeatureForAnybody(bool $canBeManageByAdmin): void
    {
        $featureName = $this->testFeatureName();
        $newFeature = $this->client->createFeature(
            $featureName,
            'admin',
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

        $superAdminClient->addUserFeature($this->normalUser['email'], $featureName);

        $user = $this->client->getUser($this->normalUser['id']);
        $this->assertContains($featureName, $user['features']);

        $superAdminClient->removeUserFeature($this->normalUser['email'], $featureName);
        $user = $this->client->getUser($this->normalUser['id']);
        $this->assertNotContains($featureName, $user['features']);
    }

    /**
     * @dataProvider provideVariousOfTokensClient
     */
    public function testSuperAdminCannotManageFeatureCannotBeManagedViaAPI(): void
    {
        $featureName = $this->testFeatureName();
        $newFeature = $this->client->createFeature(
            $featureName,
            'admin',
            $featureName,
            $featureName,
            false,
            false
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

        try {
            $superAdminClient->addUserFeature($this->normalUser['email'], $featureName);
            $this->fail('The feature "%s" can\'t be added via API');
        } catch (ClientException $exception) {
            $this->assertSame(sprintf('The feature "%s" can\'t be assigned via API', $featureName), $exception->getMessage());
            $this->assertSame(422, $exception->getCode());
        }

        $this->client->updateFeature($feature['id'], [
            'canBeManageByAdmin' => false,
            'canBeManagedViaAPI' => true,
        ]);

        $this->client->addUserFeature($this->normalUser['email'], $featureName);

        $user = $this->client->getUser($this->normalUser['id']);
        $this->assertContains($featureName, $user['features']);

        $this->client->updateFeature($feature['id'], [
            'canBeManageByAdmin' => false,
            'canBeManagedViaAPI' => false,
        ]);

        try {
            $superAdminClient->removeUserFeature($this->normalUser['email'], $featureName);
            $this->fail('The feature "%s" can\'t be removed via API');
        } catch (ClientException $exception) {
            $this->assertSame(sprintf('The feature "%s" can\'t be assigned via API', $featureName), $exception->getMessage());
            $this->assertSame(422, $exception->getCode());
        }
    }

    /**
     * @dataProvider provideVariousOfTokensClient
     */
    public function testUserCanManageOwnFeatures(): void
    {
        $featureName = $this->testFeatureName();
        $newFeature = $this->client->createFeature(
            $featureName,
            'admin',
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

        $normalUserClient->addUserFeature($this->normalUser['email'], $featureName);

        // assert user has newly created feature
        $user = $this->client->getUser($this->normalUser['id']);
        $this->assertContains($featureName, $user['features']);

        $normalUserClient->removeUserFeature($this->normalUser['email'], $featureName);
        $user = $this->client->getUser($this->normalUser['id']);
        $this->assertNotContains($featureName, $user['features']);
    }

    /**
     * @dataProvider provideVariousOfTokensClient
     */
    public function testUserCanNotManageOtherUserFeatures(): void
    {
        $featureName = $this->testFeatureName();
        $newFeature = $this->client->createFeature(
            $featureName,
            'admin',
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
            $normalUserClient->addUserFeature($this->normalUserWithMfa['email'], $featureName);
            $this->fail('Should not be able to add feature to other user');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
            $this->assertSame('You can\'t access other users', $e->getMessage());
        }

        $this->client->addUserFeature($this->normalUserWithMfa['email'], $featureName);

        $user = $this->client->getUser($this->normalUserWithMfa['id']);
        $this->assertContains($featureName, $user['features']);

        try {
            $normalUserClient->removeUserFeature($this->normalUserWithMfa['email'], $featureName);
            $this->fail('Should not be able to remove feature from other user');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
            $this->assertSame('You can\'t access other users', $e->getMessage());
        }
    }

    /**
     * @dataProvider provideVariousOfTokensClient
     */
    public function testMaintainerAdminCannotManageFeatures(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $featureName = $this->testFeatureName();
        $newFeature = $this->client->createFeature(
            $featureName,
            'admin',
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
            $normalUserClient->addUserFeature($this->normalUserWithMfa['email'], $featureName);
            $this->fail('Should not be able to add feature to other user');
        } catch (ClientException $exception) {
            $this->assertStringContainsString('You can\'t access other users', $exception->getMessage());
            $this->assertSame(403, $exception->getCode());
        }

        $this->client->addUserFeature($this->normalUserWithMfa['email'], $featureName);

        $user = $this->client->getUser($this->normalUserWithMfa['id']);
        $this->assertContains($featureName, $user['features']);

        try {
            $normalUserClient->removeUserFeature($this->normalUserWithMfa['email'], $featureName);
            $this->fail('Should not be able to add feature to other user');
        } catch (ClientException $exception) {
            $this->assertStringContainsString('You can\'t access other users', $exception->getMessage());
            $this->assertSame(403, $exception->getCode());
        }

        $user = $this->client->getUser($this->normalUserWithMfa['id']);
        $this->assertContains($featureName, $user['features']);
    }

    /**
     * @dataProvider provideVariousOfTokensClient
     */
    public function testOrgAdminCannotManageFeatures(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $featureName = $this->testFeatureName();
        $newFeature = $this->client->createFeature(
            $featureName,
            'admin',
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
            $normalUserClient->addUserFeature($this->normalUserWithMfa['email'], $featureName);
            $this->fail('Should not be able to add feature to other user');
        } catch (ClientException $exception) {
            $this->assertStringContainsString('You can\'t access other users', $exception->getMessage());
            $this->assertSame(403, $exception->getCode());
        }

        $this->client->addUserFeature($this->normalUserWithMfa['email'], $featureName);

        $user = $this->client->getUser($this->normalUserWithMfa['id']);
        $this->assertContains($featureName, $user['features']);

        try {
            $normalUserClient->removeUserFeature($this->normalUserWithMfa['email'], $featureName);
            $this->fail('Should not be able to add feature to other user');
        } catch (ClientException $exception) {
            $this->assertStringContainsString('You can\'t access other users', $exception->getMessage());
            $this->assertSame(403, $exception->getCode());
        }

        $user = $this->client->getUser($this->normalUserWithMfa['id']);
        $this->assertContains($featureName, $user['features']);
    }
}
