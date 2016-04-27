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
        $this->assertEmpty($user['features']);

        $feature = 'manage-feature-test';
        $this->client->addUserFeature($userEmail, $feature);

        $user = $this->client->getUser($userEmail);
        $this->assertEquals([$feature], $user['features']);

        $feature2 = 'manage-feature-test-2';
        $this->client->addUserFeature($userId, $feature2);

        $user = $this->client->getUser($userEmail);
        $this->assertEquals([$feature2, $feature], $user['features']);

        $this->client->removeUserFeature($userId, $feature);
        $this->client->removeUserFeature($userId, $feature2);

        $user = $this->client->getUser($userId);
        $this->assertEmpty($user['features']);
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
