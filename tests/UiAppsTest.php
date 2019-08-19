<?php

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Client;

class UiAppsTest extends ClientTestCase
{

    public function testPublicList()
    {
        $client = new Client([
            'token' => 'token is not required for this api all',
            'url' => getenv('KBC_MANAGE_API_URL'),
            'backoffMaxTries' => 1,
        ]);
        $apps = $client->listUiApps();

        $app = reset($apps);
        $this->assertNotEmpty($app['name']);
        $this->assertNotEmpty($app['version']);
        $this->assertNotEmpty($app['basePath']);
        $this->assertNotEmpty($app['styles']);
        $this->assertNotEmpty($app['scripts']);
    }
}
