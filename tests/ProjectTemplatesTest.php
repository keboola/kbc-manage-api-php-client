<?php

declare(strict_types=1);

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

final class ProjectTemplatesTest extends ClientTestCase
{
    public const TEST_PROJECT_TEMPLATE_STRING_ID = 'production';
    public const TEST_HIDDEN_PROJECT_TEMPLATE_STRING_ID = 'poc15DaysGuideMode';
    public const TEST_NONEXISTS_PROJECT_TEMPLATE_STRING_ID = 'testInvalidProjectTemplate';

    private $organization;

    /**
     * Delete organizations and remove admins from test maintainer, create empty organization without admin
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => 'devel-tests+spam@keboola.com']);

        foreach ($this->client->listMaintainerMembers($this->testMaintainerId) as $member) {
            if ($member['id'] === $this->normalUser['id']) {
                $this->client->removeUserFromMaintainer($this->testMaintainerId, $member['id']);
            }
            if ($member['id'] === $this->superAdmin['id']) {
                $this->client->removeUserFromMaintainer($this->testMaintainerId, $member['id']);
            }
        }

        $organizations = $this->client->listMaintainerOrganizations($this->testMaintainerId);
        foreach ($organizations as $organization) {
            $this->client->deleteOrganization($organization['id']);
        }

        $this->organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $this->client->addUserToOrganization($this->organization['id'], ['email' => 'devel-tests+spam@keboola.com']);
        $this->client->removeUserFromOrganization($this->organization['id'], $this->superAdmin['id']);
    }

    public function testSuperAdminCanViewAndListProjectTemplates(): void
    {
        $templates = $this->client->getProjectTemplates();
        $this->assertGreaterThan(2, count($templates));

        $filteredTemplates = array_filter($templates, fn(array $item): bool => $item['id'] === self::TEST_PROJECT_TEMPLATE_STRING_ID);

        $this->assertCount(1, $filteredTemplates);

        $templateDetail = $this->client->getProjectTemplate(self::TEST_PROJECT_TEMPLATE_STRING_ID);
        $this->assertEquals($templateDetail, current($filteredTemplates));

        // system templates
        $filteredTemplates = array_filter($templates, fn(array $item): bool => $item['id'] === self::TEST_HIDDEN_PROJECT_TEMPLATE_STRING_ID);

        $this->assertCount(1, $filteredTemplates);

        $templateDetail = $this->client->getProjectTemplate(self::TEST_HIDDEN_PROJECT_TEMPLATE_STRING_ID);
        $this->assertEquals($templateDetail, current($filteredTemplates));
    }

    public function testMaintainerAdminCanViewAndListProjectTemplates(): void
    {
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $templates = $this->normalUserClient->getProjectTemplates();

        $filteredTemplates = array_filter($templates, fn(array $item): bool => $item['id'] === self::TEST_PROJECT_TEMPLATE_STRING_ID);

        $this->assertCount(1, $filteredTemplates);

        $templateDetail = $this->normalUserClient->getProjectTemplate(self::TEST_PROJECT_TEMPLATE_STRING_ID);
        $this->assertEquals($templateDetail, current($filteredTemplates));
    }

    public function testOrganizationAdminCanViewAndListProjectTemplates(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $templates = $this->normalUserClient->getProjectTemplates();

        $filteredTemplates = array_filter($templates, fn(array $item): bool => $item['id'] === self::TEST_PROJECT_TEMPLATE_STRING_ID);

        $this->assertCount(1, $filteredTemplates);

        $templateDetail = $this->normalUserClient->getProjectTemplate(self::TEST_PROJECT_TEMPLATE_STRING_ID);
        $this->assertEquals($templateDetail, current($filteredTemplates));
    }

    public function testTemplateDetail(): void
    {
        $template = $this->client->getProjectTemplate(self::TEST_HIDDEN_PROJECT_TEMPLATE_STRING_ID);

        $this->assertArrayHasKey('id', $template);
        $this->assertArrayHasKey('name', $template);
        $this->assertArrayHasKey('description', $template);
        $this->assertArrayHasKey('expirationDays', $template);
        $this->assertArrayHasKey('billedMonthlyPrice', $template);
        $this->assertArrayHasKey('hasTryModeOn', $template);
        $this->assertArrayHasKey('defaultBackend', $template);

        $this->assertEquals(15, $template['expirationDays']);
        $this->assertIsInt($template['expirationDays']);
        $this->assertEquals(true, $template['hasTryModeOn']);
        $this->assertIsBool($template['hasTryModeOn']);
        $this->assertEquals('snowflake', $template['defaultBackend']);
    }

    public function testOrganizationAdminCannotViewHiddenProjectTemplate(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $templates = $this->normalUserClient->getProjectTemplates();

        $filteredTemplates = array_filter($templates, fn(array $item): bool => $item['id'] === self::TEST_HIDDEN_PROJECT_TEMPLATE_STRING_ID);

        $this->assertCount(0, $filteredTemplates);

        $this->expectException(ClientException::class);
        $this->expectExceptionCode(404);

        $this->normalUserClient->getProjectTemplate(self::TEST_HIDDEN_PROJECT_TEMPLATE_STRING_ID);
    }

    public function testMaintainerAdminCannotViewHiddenProjectTemplate(): void
    {
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => $this->normalUser['email']]);

        $templates = $this->normalUserClient->getProjectTemplates();

        $filteredTemplates = array_filter($templates, fn(array $item): bool => $item['id'] === self::TEST_HIDDEN_PROJECT_TEMPLATE_STRING_ID);

        $this->assertCount(0, $filteredTemplates);

        $this->expectException(ClientException::class);
        $this->expectExceptionCode(404);

        $this->normalUserClient->getProjectTemplate(self::TEST_HIDDEN_PROJECT_TEMPLATE_STRING_ID);
    }

    public function testRandomAdminCannotViewAndListProjectTemplates(): void
    {
        try {
            $this->normalUserClient->getProjectTemplates();
            $this->fail('Forbidden');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }
    }

    public function testGetNonExistProjectTemplate(): void
    {
        try {
            $this->client->getProjectTemplate('random-template-name-' . time());
            $this->fail('Project template not found');
        } catch (ClientException $e) {
            $this->assertEquals(404, $e->getCode());
        }
    }
}
