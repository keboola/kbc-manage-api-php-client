<?php

declare(strict_types=1);

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

class AssignAdminFeatureTest extends BaseFeatureTest
{
    public function setUp(): void
    {
        parent::setUp();
        $this->cleanupFeatures($this->testFeatureName(), 'admin');
    }

    /**
     * @dataProvider canBeManageByAdminProvider
     */
    public function testSuperAdminCanManageAdminFeatureForAnybody(bool $canBeManageByAdmin)
    {
        $featureName = $this->testFeatureName();
        $newFeature = $this->client->createFeature(
            $featureName,
            'admin',
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

        $this->client->addUserFeature($this->normalUser['email'], $featureName);

        $user = $this->client->getUser($this->normalUser['id']);
        $this->assertContains($featureName, $user['features']);

        $this->client->removeUserFeature($this->normalUser['email'], $featureName);
        $user = $this->client->getUser($this->normalUser['id']);
        $this->assertNotContains($featureName, $user['features']);
    }

    public function testSuperAdminCannotManageFeatureCannotBeManagedViaAPI()
    {
        $featureName = $this->testFeatureName();
        $newFeature = $this->client->createFeature(
            $featureName,
            'admin',
            $featureName,
            false,
            false
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

        try {
            $this->client->addUserFeature($this->normalUser['email'], $featureName);
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
            $this->client->removeUserFeature($this->normalUser['email'], $featureName);
            $this->fail('The feature "%s" can\'t be removed via API');
        } catch (ClientException $exception) {
            $this->assertSame(sprintf('The feature "%s" can\'t be assigned via API', $featureName), $exception->getMessage());
            $this->assertSame(422, $exception->getCode());
        }
    }

    public function canBeManageByAdminProvider(): array
    {
        return [
            'admin can manage' => [true],
            'admin cannot manage' => [false],
        ];
    }

    public function testUserCanManageOwnFeatures()
    {
        $featureName = $this->testFeatureName();
        $newFeature = $this->client->createFeature(
            $featureName,
            'admin',
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

        $this->normalUserClient->addUserFeature($this->normalUser['email'], $featureName);

        // assert user has newly created feature
        $user = $this->client->getUser($this->normalUser['id']);
        $this->assertContains($featureName, $user['features']);

        $this->normalUserClient->removeUserFeature($this->normalUser['email'], $featureName);
        $user = $this->client->getUser($this->normalUser['id']);
        $this->assertNotContains($featureName, $user['features']);
    }

    public function testUserCanNotManageOtherUserFeatures()
    {
        $featureName = $this->testFeatureName();
        $newFeature = $this->client->createFeature(
            $featureName,
            'admin',
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
            $this->normalUserClient->addUserFeature($this->normalUserWithMfa['email'], $featureName);
            $this->fail('Should not be able to add feature to other user');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
            $this->assertEquals('You can\'t access other users', $e->getMessage());
        }

        $this->client->addUserFeature($this->normalUserWithMfa['email'], $featureName);

        $user = $this->client->getUser($this->normalUserWithMfa['id']);
        $this->assertContains($featureName, $user['features']);

        try {
            $this->normalUserClient->removeUserFeature($this->normalUserWithMfa['email'], $featureName);
            $this->fail('Should not be able to remove feature from other user');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
            $this->assertEquals('You can\'t access other users', $e->getMessage());
        }
    }

    public function testMaintainerAdminCannotManageFeatures(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $featureName = $this->testFeatureName();
        $newFeature = $this->client->createFeature(
            $featureName,
            'admin',
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
            $this->normalUserClient->addUserFeature($this->normalUserWithMfa['email'], $featureName);
            $this->fail('Should not be able to add feature to other user');
        } catch (ClientException $exception) {
            $this->assertStringContainsString('You can\'t access other users', $exception->getMessage());
            $this->assertSame(403, $exception->getCode());
        }

        $this->client->addUserFeature($this->normalUserWithMfa['email'], $featureName);

        $user = $this->client->getUser($this->normalUserWithMfa['id']);
        $this->assertContains($featureName, $user['features']);

        try {
            $this->normalUserClient->removeUserFeature($this->normalUserWithMfa['email'], $featureName);
            $this->fail('Should not be able to add feature to other user');
        } catch (ClientException $exception) {
            $this->assertStringContainsString('You can\'t access other users', $exception->getMessage());
            $this->assertSame(403, $exception->getCode());
        }

        $user = $this->client->getUser($this->normalUserWithMfa['id']);
        $this->assertContains($featureName, $user['features']);
    }

    public function testOrgAdminCannotManageFeatures(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->superAdmin['email']]);
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $featureName = $this->testFeatureName();
        $newFeature = $this->client->createFeature(
            $featureName,
            'admin',
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
            $this->normalUserClient->addUserFeature($this->normalUserWithMfa['email'], $featureName);
            $this->fail('Should not be able to add feature to other user');
        } catch (ClientException $exception) {
            $this->assertStringContainsString('You can\'t access other users', $exception->getMessage());
            $this->assertSame(403, $exception->getCode());
        }

        $this->client->addUserFeature($this->normalUserWithMfa['email'], $featureName);

        $user = $this->client->getUser($this->normalUserWithMfa['id']);
        $this->assertContains($featureName, $user['features']);

        try {
            $this->normalUserClient->removeUserFeature($this->normalUserWithMfa['email'], $featureName);
            $this->fail('Should not be able to add feature to other user');
        } catch (ClientException $exception) {
            $this->assertStringContainsString('You can\'t access other users', $exception->getMessage());
            $this->assertSame(403, $exception->getCode());
        }

        $user = $this->client->getUser($this->normalUserWithMfa['id']);
        $this->assertContains($featureName, $user['features']);
    }
}
