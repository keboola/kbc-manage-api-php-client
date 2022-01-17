<?php

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Backend;
use Throwable;

class ProjectTemplateFeaturesAssigningTest extends ClientTestCase
{
    public const TEST_PROJECT_TEMPLATE_STRING_ID = 'demo';

    public function testAutomatedFeatureAssigning()
    {
        $randomFeature = $this->prepareRandomFeature();
        $this->createFeature($randomFeature);

        $this->client->addProjectTemplateFeature(self::TEST_PROJECT_TEMPLATE_STRING_ID, $randomFeature['name']);

        $project = $this->createProject();

        $this->assertContains($randomFeature['name'], $project['features']);
    }

    private function createProject()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'Test template features - organization',
            'defaultBackend' => Backend::REDSHIFT,
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'Test template features - project',
            'type' => self::TEST_PROJECT_TEMPLATE_STRING_ID,
            'dataRetentionTimeInDays' => 1,
        ]);

        return $project;
    }

    private function prepareRandomFeature()
    {
        return [
            'name' => 'test-feature-project-template-' . uniqid('', true),
            'type' => 'project',
            'description' => 'project template feature',
        ];
    }

    private function createFeature($feature)
    {
        $this->client->createFeature($feature['name'], $feature['type'], $feature['description']);
    }

    public function testCreateProjectWithROIMFeatureFromTemplate()
    {
        $roimFeatureName = 'input-mapping-read-only-storage';

        try {
            $this->client->createFeature($roimFeatureName, 'project', '');
        } catch (Throwable $e) {
            self::assertEquals($e->getMessage(), 'Feature already exists');
        }

        $this->client->addProjectTemplateFeature(self::TEST_PROJECT_TEMPLATE_STRING_ID, $roimFeatureName);

        $project = $this->createProject();

        self::assertContains($roimFeatureName, $project['features']);
        $this->client->deleteProject($project['id']);
        $this->client->purgeDeletedProject($project['id']);

        $this->client->removeProjectTemplateFeature(self::TEST_PROJECT_TEMPLATE_STRING_ID, $roimFeatureName);
    }
}
