<?php
/**
 * Created by PhpStorm.
 * User: martinhalamicek
 * Date: 07/01/16
 * Time: 09:43
 */

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

class UsersTest extends ClientTestCase
{

    public function testGetUser()
    {
        $token = $this->client->verifyToken();
        $userEmail = $token['user']['email'];
        $userId = $token['user']['id'];

        $user = $this->client->getUser($userEmail);
        $this->assertEquals($userId, $user['id']);
        $initialFeaturesCount = count($user['features']);

        $feature = 'manage-feature-test-' . $this->getRandomFeatureSuffix();
        $this->client->createFeature($feature, 'admin', $feature);
        $this->client->addUserFeature($userEmail, $feature);

        $user = $this->client->getUser($userEmail);
        $this->assertEquals($initialFeaturesCount + 1, count($user['features']));
        $this->assertContains($feature, $user['features']);

        $feature2 = 'manage-feature-test-2-' . $this->getRandomFeatureSuffix();
        $this->client->createFeature($feature2, 'admin', $feature2);
        $this->client->addUserFeature($userId, $feature2);

        $user = $this->client->getUser($userEmail);
        $this->assertEquals($initialFeaturesCount + 2, count($user['features']));
        $this->assertContains($feature, $user['features']);

        $this->client->removeUserFeature($userId, $feature);
        $this->client->removeUserFeature($userId, $feature2);

        $user = $this->client->getUser($userId);
        $this->assertEquals($initialFeaturesCount, count($user['features']));
    }

    public function testAddNonexistentFeature()
    {
        $token = $this->client->verifyToken();
        $this->assertTrue(isset($token['user']['id']));
        $featureName = 'random-feature-' . $this->getRandomFeatureSuffix();

        try {
            $this->client->addUserFeature($token['user']['id'], $featureName);
            $this->fail('Feature not found');
        } catch (ClientException $e) {
            $this->assertEquals(404, $e->getCode());
        }
    }

}
