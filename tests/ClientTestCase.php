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

    protected $testMaintainerId;

    public function setUp()
    {
        $this->client = new Client([
            'token' => getenv('KBC_MANAGE_API_TOKEN'),
            'url' => getenv('KBC_MANAGE_API_URL'),
            'backoffMaxTries' => 1,
        ]);
        $this->testMaintainerId = getenv('KBC_TEST_MAINTAINER_ID');
    }


}