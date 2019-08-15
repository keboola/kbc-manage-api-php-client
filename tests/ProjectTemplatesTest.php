<?php

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

class ProjectTemplatesTest extends ClientTestCase
{
    const TEST_PROJECT_TEMPLATE_STRING_ID = 'production';

    const TEST_HIDDEN_PROJECT_TEMPLATE_STRING_ID = 'poc15DaysGuideMode';

    private $organization;
    /**
     * Create empty organization without admins, remove admins from test maintainer and delete all their join requests
     */
    public function setUp()
    {
        parent::setUp();
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => 'spam+spam@keboola.com']);
        foreach ($this->client->listMaintainerMembers($this->testMaintainerId) as $member) {
            if ($member['id'] === $this->normalUser['id']) {
                $this->client->removeUserFromMaintainer($this->testMaintainerId, $member['id']);
            }
            if ($member['id'] === $this->superAdmin['id']) {
                $this->client->removeUserFromMaintainer($this->testMaintainerId, $member['id']);
            }
        }
        $this->organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);
        $this->client->addUserToOrganization($this->organization['id'], ['email' => 'spam+spam@keboola.com']);
        $this->client->removeUserFromOrganization($this->organization['id'], $this->superAdmin['id']);
        foreach ($this->normalUserClient->listMyOrganizationInvitations() as $invitation) {
            $this->normalUserClient->declineMyOrganizationInvitation($invitation['id']);
        }
        foreach ($this->client->listMyOrganizationInvitations() as $invitation) {
            $this->client->declineMyOrganizationInvitation($invitation['id']);
        }
    }

    public function testSuperAdminCanViewAndListProjectTemplates()
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

    public function testOrganizationAdminCanViewAndListProjectTemplates()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $templates = $this->normalUserClient->getProjectTemplates();

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

    public function testSuperAdminCanViewHiddenProjectTemplate()
    {
        $template = $this->client->getProjectTemplate(self::TEST_HIDDEN_PROJECT_TEMPLATE_STRING_ID);

        $this->assertArrayHasKey('id', $template);
        $this->assertArrayHasKey('name', $template);
        $this->assertArrayHasKey('description', $template);
        $this->assertArrayHasKey('expirationDays', $template);
        $this->assertArrayHasKey('hasTryModeOn', $template);
    }

    public function testOrganizationAdminCannotViewHiddenProjectTemplate()
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionCode(403);

        $this->normalUserClient->getProjectTemplate(self::TEST_HIDDEN_PROJECT_TEMPLATE_STRING_ID);
    }

    public function testRandomAdminCannotViewAndListProjectTemplates()
    {
        $this->removeNormalUserFromOrganization();

        try {
            $this->normalUserClient->getProjectTemplates();
            $this->fail('Forbidden');
        } catch (ClientException $e) {
            $this->assertEquals(403, $e->getCode());
        }
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

    private function removeNormalUserFromOrganization()
    {
        $filteredUsers = array_filter($this->client->listOrganizationUsers($this->organization['id']), function ($input) {
            if ($input['id'] === $this->normalUser['id']) {
                return true;
            }
            return false;
        });
        if (count($filteredUsers) > 0) {
            $this->client->removeUserFromOrganization($this->organization['id'], $this->normalUser['id']);
        }
    }
}
