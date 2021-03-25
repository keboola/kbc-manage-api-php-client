<?php
require 'vendor/autoload.php';

class AddFeatures
{
    public $host = '';

    public $token = '';

    public $featureName = '';

    public $featureDesc = '';

    public function run()
    {
        $client = new \Keboola\ManageApi\Client([
            'url' => $this->host,
            'token' => $this->token,
        ]);

        try {
            echo "Creating new feature {$this->featureName}\n";
            $client->createFeature($this->featureName, 'project', $this->featureDesc);
            echo " - SUCCESS\n ";
        } catch (Exception $e) {
            echo " - ERROR " . $e->getMessage() . "\n";
        }

        echo "\n --- Adding features to template -- \n\n";

        $templates = $client->getProjectTemplates();

        foreach ($templates as $template) {
            echo "Adding feature '{$this->featureName}' to template '{$template['id']}': \n";
            try {
                $client->addProjectTemplateFeature($template['id'], $this->featureName);
                echo " - SUCCESS\n ";
            } catch (Exception $e) {
                echo " - ERROR " . $e->getMessage() . "\n";
            }
        }
    }
}

$obj = new AddFeatures();
$obj->featureDesc = 'desc';
$obj->featureName = 'mojeKrasnaFicura';


$stacks = [
    'local' => ['host' => 'http://connection-apache', 'token' => ''],
//    'connection' => ['host' => '', 'token' => ''],
//    'eu-central' => ['host' => '', 'token' => ''],
//    'eu-north' => ['host' => '', 'token' => ''],
//    'test-east' => ['host' => '', 'token' => ''],
//    'test-az' => ['host' => '', 'token' => ''],
];

foreach ($stacks as $stackName => $stackData) {
    echo "Running for {$stackName} \n\n";
    $obj->host = $stackData['host'];
    $obj->token = $stackData['token'];
    $obj->run();
}
