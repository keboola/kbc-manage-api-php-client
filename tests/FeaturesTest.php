<?php

namespace Keboola\ManageApiTest;

use Generator;
use Keboola\ManageApi\ClientException;

class FeaturesTest extends BaseFeatureTest
{
    public function setUp(): void
    {
        parent::setUp();
        $this->client->removeUserFeature($this->normalUser['email'], 'can-manage-features');
    }

    /**
     * @dataProvider featureProvider
     */
    public function testNormalUserCannotSeeTheSameFeatureAsSuperAdmin(array $createFeature, array $expectedFeature)
    {
        $createdFeature = $this->client->createFeature(
            $createFeature['name'],
            $createFeature['type'],
            $createFeature['title'],
            $createFeature['description'],
            $createFeature['canBeManageByAdmin'],
            $createFeature['canBeManagedViaAPI'],
        );

        $normalUserClient = $this->getNormalUserClient();
        $features = $normalUserClient->listFeatures();

        $featureFound = null;

        foreach ($features as $feature) {
            if ($expectedFeature['name'] === $feature['name']) {
                $featureFound = $feature;
                break;
            }
        }

        if ($createdFeature['canBeManageByAdmin'] === false || $createdFeature['canBeManagedViaAPI'] === false) {
            // feature should not be visible for normal user
            $this->assertNull($featureFound);
            try {
                $normalUserClient->getFeature($createdFeature['id']);
                $this->fail('Normal user should not see the feature');
            } catch (ClientException $e) {
                $this->assertEquals(404, $e->getCode());
            }
        } else {
            $this->assertTrue($featureFound !== null);
            $this->assertSame($expectedFeature['name'], $featureFound['name']);
            $this->assertSame($expectedFeature['type'], $featureFound['type']);
            $this->assertSame($expectedFeature['title'], $featureFound['title']);
            $this->assertSame($expectedFeature['description'], $featureFound['description']);
            $this->assertSame($expectedFeature['canBeManageByAdmin'], $featureFound['canBeManageByAdmin']);
            $this->assertSame($expectedFeature['canBeManagedViaAPI'], $featureFound['canBeManagedViaAPI']);

            $feature = $normalUserClient->getFeature($featureFound['id']);
            $this->assertSame($expectedFeature['name'], $feature['name']);
            $this->assertSame($expectedFeature['type'], $feature['type']);
            $this->assertSame($expectedFeature['title'], $feature['title']);
            $this->assertSame($expectedFeature['description'], $feature['description']);
            $this->assertSame($expectedFeature['canBeManageByAdmin'], $feature['canBeManageByAdmin']);
            $this->assertSame($expectedFeature['canBeManagedViaAPI'], $feature['canBeManagedViaAPI']);
        }

        $this->client->removeFeature($createdFeature['id']);
    }

    /**
     * @dataProvider featureProvider
     */
    public function testNormalUserWithFeatureCanSeeTheSameFeatureAsSuperAdmin(array $createFeature, array $expectedFeature)
    {
        $this->client->addUserFeature($this->normalUser['email'], 'can-manage-features');
        $this->client->createFeature(
            $createFeature['name'],
            $createFeature['type'],
            $createFeature['title'],
            $createFeature['description'],
            $createFeature['canBeManageByAdmin'],
            $createFeature['canBeManagedViaAPI'],
        );

        $normalUserClient = $this->getNormalUserClient();
        $features = $normalUserClient->listFeatures();

        $featureFound = null;

        foreach ($features as $feature) {
            if ($expectedFeature['name'] === $feature['name']) {
                $featureFound = $feature;
                break;
            }
        }

        $this->assertTrue($featureFound !== null);
        $this->assertSame($expectedFeature['name'], $featureFound['name']);
        $this->assertSame($expectedFeature['type'], $featureFound['type']);
        $this->assertSame($expectedFeature['title'], $featureFound['title']);
        $this->assertSame($expectedFeature['description'], $featureFound['description']);
        $this->assertSame($expectedFeature['canBeManageByAdmin'], $featureFound['canBeManageByAdmin']);
        $this->assertSame($expectedFeature['canBeManagedViaAPI'], $featureFound['canBeManagedViaAPI']);

        $feature = $normalUserClient->getFeature($featureFound['id']);
        $this->assertSame($expectedFeature['name'], $feature['name']);
        $this->assertSame($expectedFeature['type'], $feature['type']);
        $this->assertSame($expectedFeature['title'], $feature['title']);
        $this->assertSame($expectedFeature['description'], $feature['description']);
        $this->assertSame($expectedFeature['canBeManageByAdmin'], $feature['canBeManageByAdmin']);
        $this->assertSame($expectedFeature['canBeManagedViaAPI'], $feature['canBeManagedViaAPI']);

        $this->client->removeFeature($featureFound['id']);
    }

    /**
     * @dataProvider featureProvider
     */
    public function testCreateListAndDeleteFeature(array $createFeature, array $expectedFeature)
    {
        $this->client->createFeature(
            $createFeature['name'],
            $createFeature['type'],
            $createFeature['title'],
            $createFeature['description'],
            $createFeature['canBeManageByAdmin'],
            $createFeature['canBeManagedViaAPI'],
        );

        $features = $this->client->listFeatures();

        $featureFound = null;

        foreach ($features as $feature) {
            if ($expectedFeature['name'] === $feature['name']) {
                $featureFound = $feature;
                break;
            }
        }

        $this->assertTrue($featureFound !== null);
        $this->assertSame($expectedFeature['name'], $featureFound['name']);
        $this->assertSame($expectedFeature['type'], $featureFound['type']);
        $this->assertSame($expectedFeature['title'], $featureFound['title']);
        $this->assertSame($expectedFeature['description'], $featureFound['description']);
        $this->assertSame($expectedFeature['canBeManageByAdmin'], $featureFound['canBeManageByAdmin']);
        $this->assertSame($expectedFeature['canBeManagedViaAPI'], $featureFound['canBeManagedViaAPI']);

        $this->client->updateFeature($featureFound['id'], [
            'title' => 'Updated title',
            'description' => 'Updated desc',
            'canBeManageByAdmin' => !$createFeature['canBeManageByAdmin'],
            'canBeManagedViaAPI' => !$createFeature['canBeManagedViaAPI'],
        ]);
        $feature = $this->client->getFeature($featureFound['id']);
        $this->assertSame('Updated title', $feature['title']);
        $this->assertSame('Updated desc', $feature['description']);
        $this->assertSame(!$expectedFeature['canBeManageByAdmin'], !$createFeature['canBeManageByAdmin']);
        $this->assertSame(!$expectedFeature['canBeManagedViaAPI'], !$createFeature['canBeManagedViaAPI']);

        // test if values stay stame if not provided
        $this->client->updateFeature($featureFound['id'], []);
        $feature = $this->client->getFeature($featureFound['id']);
        $this->assertSame('Updated title', $feature['title']);
        $this->assertSame('Updated desc', $feature['description']);
        $this->assertSame(!$expectedFeature['canBeManageByAdmin'], !$createFeature['canBeManageByAdmin']);
        $this->assertSame(!$expectedFeature['canBeManagedViaAPI'], !$createFeature['canBeManagedViaAPI']);

        $secondFeature = $this->prepareRandomFeature('admin');

        $this->client->createFeature(
            $secondFeature['name'],
            $secondFeature['type'],
            $secondFeature['title'],
            $secondFeature['description']
        );

        $this->client->removeFeature($featureFound['id']);

        $this->assertSame(count($features), count($this->client->listFeatures()));
    }

    public function featureProvider(): Generator
    {
        $suffix = $this->getRandomFeatureSuffix();
        $name = 'test-feature-' . $suffix;
        $title = 'Test Feature ' . $suffix;

        yield 'global, canBeManageByAdmin:true, canBeManagedViaAPI:true' => [
            [
                'name' => $name,
                'type' => 'global',
                'title' => $title,
                'canBeManageByAdmin' => true,
                'canBeManagedViaAPI' => true,
                'description' => 'test global feature',
            ],
            [
                'name' => $name,
                'type' => 'global',
                'title' => $title,
                'canBeManageByAdmin' => true,
                'canBeManagedViaAPI' => true,
                'description' => 'test global feature',
            ],
        ];

        yield 'global, canBeManageByAdmin:false, canBeManagedViaAPI:false' => [
            [
                'name' => $name,
                'type' => 'global',
                'title' => $title,
                'canBeManageByAdmin' => false,
                'canBeManagedViaAPI' => false,
                'description' => 'test global feature',
            ],
            [
                'name' => $name,
                'type' => 'global',
                'title' => $title,
                'canBeManageByAdmin' => false,
                'canBeManagedViaAPI' => false,
                'description' => 'test global feature',
            ],
        ];

        yield 'global, canBeManageByAdmin:false, canBeManagedViaAPI:true' => [
            [
                'name' => $name,
                'type' => 'global',
                'title' => $title,
                'canBeManageByAdmin' => false,
                'canBeManagedViaAPI' => true,
                'description' => 'test global feature',
            ],
            [
                'name' => $name,
                'type' => 'global',
                'title' => $title,
                'canBeManageByAdmin' => false,
                'canBeManagedViaAPI' => true,
                'description' => 'test global feature',
            ],
        ];

        yield 'global, canBeManageByAdmin:true, canBeManagedViaAPI:false' => [
            [
                'name' => $name,
                'type' => 'global',
                'title' => $title,
                'canBeManageByAdmin' => true,
                'canBeManagedViaAPI' => false,
                'description' => 'test global feature',
            ],
            [
                'name' => $name,
                'type' => 'global',
                'title' => $title,
                'canBeManageByAdmin' => true,
                'canBeManagedViaAPI' => false,
                'description' => 'test global feature',
            ],
        ];
    }

    public function testFilterFeatures()
    {
        $expectedFeature = $this->prepareRandomFeature('project');

        $this->client->createFeature(
            $expectedFeature['name'],
            $expectedFeature['type'],
            $expectedFeature['title'],
            $expectedFeature['description']
        );

        // try to find feature in wrong list
        $globalFeatures = $this->client->listFeatures(['type' => 'global']);
        $featureFoundInWrongList = false;
        foreach ($globalFeatures as $feature) {
            if ($expectedFeature['name'] === $feature['name']) {
                $featureFoundInWrongList = true;
                break;
            }
        }

        $this->assertFalse($featureFoundInWrongList);

        // find in correct list
        $projectFeatures = $this->client->listFeatures(['type' => 'project']);
        $foundFeature = null;

        foreach ($projectFeatures as $feature) {
            if ($expectedFeature['name'] === $feature['name']) {
                $foundFeature = $feature;
                break;
            }
        }

        $this->assertTrue($foundFeature !== null);
        $this->assertSame($expectedFeature['name'], $foundFeature['name']);
        $this->assertSame($expectedFeature['type'], $foundFeature['type']);
        $this->assertSame($expectedFeature['title'], $foundFeature['title']);
        $this->assertSame($expectedFeature['description'], $foundFeature['description']);
    }

    public function testFeatureDetail()
    {
        $newFeature = $this->prepareRandomFeature('admin');

        $insertedFeature = $this->client->createFeature(
            $newFeature['name'],
            $newFeature['type'],
            $newFeature['title'],
            $newFeature['description']
        );

        $fetchedFeature = $this->client->getFeature($insertedFeature['id']);

        $this->assertSame($newFeature['name'], $fetchedFeature['name']);
        $this->assertSame($newFeature['type'], $fetchedFeature['type']);
        $this->assertSame($newFeature['title'], $fetchedFeature['title']);
        $this->assertSame($newFeature['description'], $fetchedFeature['description']);
    }


    public function testFeatureDetailProjects()
    {
        $newFeature = $this->prepareRandomFeature('project');

        $insertedFeature = $this->client->createFeature(
            $newFeature['name'],
            $newFeature['type'],
            $newFeature['title'],
            $newFeature['description']
        );

        $fetchedFeature = $this->client->getFeature($insertedFeature['id']);

        $this->assertSame($newFeature['name'], $fetchedFeature['name']);

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);
        $project = $this->createRedshiftProjectForClient($this->client, $organization['id'], [
            'name' => 'My test',
        ]);

        $this->client->addProjectFeature($project['id'], $newFeature['name']);

        $featureProjects = $this->client->getFeatureProjects($insertedFeature['id']);

        $this->assertNotEmpty($featureProjects);
        $this->assertIsArray($featureProjects);

        $projectFound = null;

        foreach ($featureProjects as $featureProject) {
            if ($project['name'] === $featureProject['name']) {
                $projectFound = $featureProject;
                break;
            }
        }

        $this->assertTrue($projectFound !== null);
        $this->assertSame($project['id'], $projectFound['id']);
        $this->assertSame($project['name'], $projectFound['name']);
    }

    public function testFeatureDetailAdmins()
    {
        $newFeature = $this->prepareRandomFeature('admin');

        $insertedFeature = $this->client->createFeature(
            $newFeature['name'],
            $newFeature['type'],
            $newFeature['title'],
            $newFeature['description']
        );

        $fetchedFeature = $this->client->getFeature($insertedFeature['id']);

        $this->assertSame($newFeature['name'], $fetchedFeature['name']);

        $token = $this->client->verifyToken();
        $this->assertTrue(isset($token['user']['id']));
        $userId = $token['user']['id'];
        $userEmail = $token['user']['email'];

        $this->client->addUserFeature($userId, $newFeature['name']);

        $featureAdmins = $this->client->getFeatureAdmins($insertedFeature['id']);

        $this->assertNotEmpty($featureAdmins);
        $this->assertIsArray($featureAdmins);

        $adminFound = null;

        foreach ($featureAdmins as $featureAdmin) {
            if ($userEmail === $featureAdmin['email']) {
                $adminFound = $featureAdmin;
                break;
            }
        }

        $this->assertTrue($adminFound !== null);
        $this->assertSame($userId, $adminFound['id']);
        $this->assertSame($userEmail, $adminFound['email']);
    }


    public function testCreateSameFeatureTwice()
    {
        $initialFeaturesCount = count($this->client->listFeatures());

        $newFeature = $this->prepareRandomFeature('admin');

        $this->client->createFeature(
            $newFeature['name'],
            $newFeature['type'],
            $newFeature['title'],
            $newFeature['description']
        );

        $this->assertSame($initialFeaturesCount + 1, count($this->client->listFeatures()));

        try {
            $this->client->createFeature(
                $newFeature['name'],
                $newFeature['type'],
                $newFeature['title'],
                $newFeature['description']
            );
            $this->fail('Feature already exists');
        } catch (ClientException $e) {
            $this->assertEquals(422, $e->getCode());
        }

        $this->assertSame($initialFeaturesCount + 1, count($this->client->listFeatures()));
    }

    public function testCreateFeatureWithWrongType()
    {
        $newFeature = $this->prepareRandomFeature('random-feature-type');

        try {
            $this->client->createFeature(
                $newFeature['name'],
                $newFeature['type'],
                $newFeature['title'],
                $newFeature['description']
            );
            $this->fail('Invalid feature type');
        } catch (ClientException $e) {
            $this->assertEquals(422, $e->getCode());
        }
    }

    public function testRemoveNonexistentFeature()
    {
        $features = $this->client->listFeatures();
        $lastFeature = end($features);
        try {
            $this->client->removeFeature($lastFeature['id'] + 1);
            $this->fail('Feature not found');
        } catch (ClientException $e) {
            $this->assertEquals(404, $e->getCode());
        }

        try {
            $this->client->removeFeature('');
            $this->fail('Feature not found');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
        }
    }

    /**
     * @return array{name: string, type: string, title: string, description: string}
     */
    private function prepareRandomFeature(string $type): array
    {
        $suffix = $this->getRandomFeatureSuffix();
        return [
            'name' => 'test-feature-' . $suffix,
            'type' => $type,
            'title' => 'Test Feature ' . $suffix,
            'description' => 'test ' . $type . 'feature',
        ];
    }
}
