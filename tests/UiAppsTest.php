<?php

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Client;

class UiAppsTest extends ClientTestCase
{
    public function testAppCreationAndDeletion()
    {
        $client = new Client([
            'token' => getenv('KBC_MANAGE_API_SUPER_TOKEN_WITH_UI_MANAGE_SCOPE'),
            'url' => getenv('KBC_MANAGE_API_URL'),
            'backoffMaxTries' => 1,
        ]);

        $newAppName = 'Sample KBC Application';

        $listOfAppsBeforeCreation = array_map(function ($app) {
            return $app['name'];
        }, $client->listUiApps());
        sort($listOfAppsBeforeCreation);

        $client->registerUiApp([
            'manifestUrl' => 'https://keboola.github.io/kbc-ui-app-manifest-file/sample.json',
            'activate' => true,
        ]);

        $listOfAppsAfterCreation = array_map(function ($app) {
            return $app['name'];
        }, $client->listUiApps());
        sort($listOfAppsAfterCreation);

        $client->deleteUiApp($newAppName);

        $listOfAppsAfterDeletion = array_map(function ($app) {
            return $app['name'];
        }, $client->listUiApps());
        sort($listOfAppsAfterDeletion);

        $this->assertTrue(count($listOfAppsBeforeCreation) === (count($listOfAppsAfterCreation) - 1));
        $this->assertTrue(array_search($newAppName, $listOfAppsAfterCreation) !== false);
        $this->assertEquals($listOfAppsBeforeCreation, $listOfAppsAfterDeletion);
    }

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
