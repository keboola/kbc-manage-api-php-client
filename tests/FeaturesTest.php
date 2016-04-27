<?php

namespace Keboola\ManageApiTest;

class FeaturesTest extends ClientTestCase
{
    public function testCreateListAndDeleteFeature()
    {
        $expectedFeature = $this->prepareRandomFeature();

        $this->client->createFeature(
            $expectedFeature['name'], $expectedFeature['type'], $expectedFeature['description']
        );

        $features = $this->client->listFeatures();

        $featureFound = null;

        foreach ($features as $feature) {
            if (array_search($expectedFeature['name'], $feature) !== false) {
                $featureFound = $feature;
                break;
            }
        }

        $this->assertTrue($featureFound !== null);
        $this->assertSame($expectedFeature['name'], $featureFound['name']);
        $this->assertSame($expectedFeature['type'], $featureFound['type']);
        $this->assertSame($expectedFeature['description'], $featureFound['description']);

        $secondFeature = $this->prepareRandomFeature();

        $this->client->createFeature(
            $secondFeature['name'], $secondFeature['type'], $secondFeature['description']
        );

        $this->client->removeFeature($featureFound['name']);

        $this->assertSame(count($features), count($this->client->listFeatures()));

    }

    private function prepareRandomFeature()
    {
        return [
            'name' => 'test-feature-' . $this->getRandomFeatureSuffix(),
            'type' => 'admin',
            'description' => 'test feature',
        ];
    }

}
