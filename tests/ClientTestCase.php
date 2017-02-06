<?php
/**
 * Created by PhpStorm.
 * User: martinhalamicek
 * Date: 15/10/15
 * Time: 15:26
 */

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Client;

class ClientTestCase extends \PHPUnit_Framework_TestCase
{

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var Client
     */
    protected $normalUserClient;

    protected $testMaintainerId;

    protected $normalUser;

    protected $superAdmin;
    
    public function setUp()
    {
        $this->client = new Client([
            'token' => getenv('KBC_MANAGE_API_TOKEN'),
            'url' => getenv('KBC_MANAGE_API_URL'),
            'backoffMaxTries' => 1,
        ]);
        $this->normalUserClient = new \Keboola\ManageApi\Client([
            'token' => getenv('KBC_TEST_ADMIN_TOKEN'),
            'url' => getenv('KBC_MANAGE_API_URL'),
            'backoffMaxTries' => 1,
        ]);
        $this->testMaintainerId = getenv('KBC_TEST_MAINTAINER_ID');

        $this->normalUser = $this->normalUserClient->verifyToken()['user'];
        $this->superAdmin = $this->client->verifyToken()['user'];
        $maintainers = $this->client->listMaintainers();
        foreach ($maintainers as $maintainer) {
            if ((int) $maintainer['id'] === (int) $this->testMaintainerId) {
                $members = $this->client->listMaintainerMembers($maintainer['id']);
                foreach ($members as $member) {
                    if ($member['id'] != $this->superAdmin['id']) {
                        $this->client->removeUserFromMaintainer($maintainer['id'], $member['id']);
                    }
                }
            } else {
                $this->client->deleteMaintainer($maintainer['id']);
            }
        }
    }

    public function getRandomFeatureSuffix()
    {
        return uniqid('', true);
    }
}
