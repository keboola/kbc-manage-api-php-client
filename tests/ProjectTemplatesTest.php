<?php

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

class ProjectTemplatesTest extends ClientTestCase
{
    const TEST_PROJECT_TEMPLATE_STRING_ID = 'production';

    public function testListProjectTemplates()
    {
        $templates = $this->client->getProjectTemplates();

        $this->assertGreaterThan(0, count($templates));

        $filteredTemplates = array_filter($templates, function ($item) {
            if ($item['id'] !== self::TEST_PROJECT_TEMPLATE_STRING_ID) {
                return false;
            }
            return true;
        });
        $this->assertGreaterThan(0, count($filteredTemplates));

        $templateDetail = $this->client->getProjectTemplate(self::TEST_PROJECT_TEMPLATE_STRING_ID);

        $this->assertEquals($templateDetail, current($filteredTemplates));
    }

    public function testFetchProjectTemplate()
    {
        $template = $this->client->getProjectTemplate(self::TEST_PROJECT_TEMPLATE_STRING_ID);

        $this->assertArrayHasKey('id', $template);
        $this->assertArrayHasKey('name', $template);
        $this->assertArrayHasKey('description', $template);
        $this->assertArrayHasKey('expirationDays', $template);
        $this->assertArrayHasKey('hasTryModeOn', $template);
    }

    public function testGetNonExistProjectTemplate()
    {
        try {
            $this->client->getProjectTemplate('random-template-name-' . time());
            $this->fail('Project template not found');
        } catch (ClientException $e) {
            $this->assertEquals(404, $e->getCode());
        }
    }
}
