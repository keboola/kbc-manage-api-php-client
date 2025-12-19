<?php

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;
use Keboola\ManageApiTest\Utils\EnvVariableHelper;

class UiAppsTest extends ClientTestCase
{
    public function testAppCreationAndDeletion(): void
    {
        $client = $this->getClient([
            'token' => EnvVariableHelper::getKbcManageApiSuperTokenWithUiManageScope(),
            'url' => EnvVariableHelper::getKbcManageApiUrl(),
            'backoffMaxTries' => 1,
        ]);

        $newAppName = 'Sample KBC Application';

        $listOfAppsBeforeCreation = array_map(function (array $app) {
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

        $listOfAppsAfterCreation = array_map(function (array $app) {
            return $app['name'];
        }, $client->listUiApps());
        sort($listOfAppsAfterCreation);

        $client->deleteUiApp($newAppName);

        $listOfAppsAfterDeletion = array_map(function (array $app) {
            return $app['name'];
        }, $client->listUiApps());
        sort($listOfAppsAfterDeletion);

        $this->assertSame(count($listOfAppsBeforeCreation), (count($listOfAppsAfterCreation) - 1));
        $this->assertNotFalse(array_search($newAppName, $listOfAppsAfterCreation));
        $this->assertEquals($listOfAppsBeforeCreation, $listOfAppsAfterDeletion);
    }

    public function testAppCreationWithIsCritical(): void
    {
        $client = $this->getClient([
            'token' => EnvVariableHelper::getKbcManageApiSuperTokenWithUiManageScope(),
            'url' => EnvVariableHelper::getKbcManageApiUrl(),
            'backoffMaxTries' => 1,
        ]);

        $appName = 'Sample critical KBC Application';
        try {
            $client->deleteUiApp($appName);
            $this->fail('Should fail');
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

    public function testPublicList(): void
    {
        $client = $this->getClient([
            'token' => 'token is not required for this api all',
            'url' => EnvVariableHelper::getKbcManageApiUrl(),
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
