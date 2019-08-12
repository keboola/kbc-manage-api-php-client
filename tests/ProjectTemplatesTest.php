<?php

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

class ProjectTemplatesTest extends ClientTestCase
{
    const TEST_PROJECT_TEMPLATE_STRING_ID = 'production';

    public function testListProjectTemplates()
    {
        $templates = $this->client->getProjectTemplates(self::TEST_PROJECT_TEMPLATE_STRING_ID);

        $this->assertGreaterThan(0, count($templates));

    }

    public function testFetchProjectTemplate()
    {
        $template = $this->client->getProjectTemplate(self::TEST_PROJECT_TEMPLATE_STRING_ID);

        $this->assertArrayHasKey('stringId', $template);
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
