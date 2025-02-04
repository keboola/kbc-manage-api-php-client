<?php

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Client;
use Keboola\ManageApi\ClientException;

class UiAppsTest extends ClientTestCase
{
    public function testAppCreationAndDeletion()
    {
        $client = $this->getClient([
            'token' => getenv('KBC_MANAGE_API_SUPER_TOKEN_WITH_UI_MANAGE_SCOPE'),
            'url' => getenv('KBC_MANAGE_API_URL'),
            'backoffMaxTries' => 1,
        ]);

        $newAppName = 'Sample KBC Application';

        $listOfAppsBeforeCreation = array_map(function ($app) {
            return $app['name'];
        }, $client->listUiApps());
        sort($listOfAppsBeforeCreation);

        if (in_array($newAppName, $listOfAppsBeforeCreation)) {
            $client->deleteUiApp($newAppName);
        }

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

        $this->assertSame(count($listOfAppsBeforeCreation), (count($listOfAppsAfterCreation) - 1));
        $this->assertNotFalse(array_search($newAppName, $listOfAppsAfterCreation));
        $this->assertEquals($listOfAppsBeforeCreation, $listOfAppsAfterDeletion);
    }

    public function testAppCreationWithIsCritical()
    {
        $client = $this->getClient([
            'token' => getenv('KBC_MANAGE_API_SUPER_TOKEN_WITH_UI_MANAGE_SCOPE'),
            'url' => getenv('KBC_MANAGE_API_URL'),
            'backoffMaxTries' => 1,
        ]);

        $appName = 'Sample critical KBC Application';
        try {
            $client->deleteUiApp($appName);
        } catch (ClientException) {
        }

        $created = $client->registerUiApp([
            'manifestUrl' => 'https://keboola.github.io/kbc-ui-app-manifest-file/sample.critical.json',
            'activate' => true,
        ]);

        $this->assertEquals(true, $created['version']['isCritical']);

        $apps = $client->listUiApps();
        $key = array_search($appName, array_column($apps, 'name'));

        $this->assertEquals(true, $apps[$key]['isCritical']);
    }

    public function testPublicList()
    {
        $client = $this->getClient([
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
