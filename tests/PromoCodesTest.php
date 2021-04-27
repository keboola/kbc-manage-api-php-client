<?php

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

class PromoCodesTest extends ParallelClientTestCase
{

    private $organization;

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

        $organizations = $this->client->listMaintainerOrganizations($this->testMaintainerId);
        foreach ($organizations as $organization) {
            foreach ($this->client->listOrganizationProjects($organization['id']) as $project) {
                $this->client->deleteProject($project['id']);
            }
            $this->client->deleteOrganization($organization['id']);
        }

        $this->organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org for promo codes',
        ]);

        $this->client->addUserToOrganization($this->organization['id'], ['email' => 'spam@keboola.com']);
        $this->client->removeUserFromOrganization($this->organization['id'], $this->superAdmin['id']);
    }

    public function testMaintainerAdminCanListAndCreatePromoCodes()
    {
        $this->client->addUserToMaintainer($this->testMaintainerId, ['id' => $this->normalUser['id']]);

        $promoCodesBeforeCreate = $this->normalUserClient->listPromoCodes($this->testMaintainerId);

        $promoCode = $this->normalUserClient->createPromoCode($this->testMaintainerId, [
            'code' => 'TEST-' . time(),
            'expirationDays' => rand(5, 20),
            'organizationId' => $this->organization['id'],
            'projectTemplateStringId' => 'poc6months',
        ]);

        $promoCodesAfterCreate = $this->normalUserClient->listPromoCodes($this->testMaintainerId);
        $this->assertEquals(count($promoCodesBeforeCreate) + 1, count($promoCodesAfterCreate));

        $this->assertEquals($promoCode, end($promoCodesAfterCreate));
    }

    public function testCannotListPromoCodesFromRemovedOrganization()
    {
        $promoCodeCode = 'TEST-' . time();
        $this->client->createPromoCode($this->testMaintainerId, [
            'code' => $promoCodeCode,
            'expirationDays' => rand(5, 20),
            'organizationId' => $this->organization['id'],
            'projectTemplateStringId' => 'poc6months',
        ]);

        $listBeforeRemoveOrganization = $this->client->listPromoCodes($this->testMaintainerId);
        $this->assertCount(1, array_filter($listBeforeRemoveOrganization, function ($item) use ($promoCodeCode) {
            return $item['code'] === $promoCodeCode;
        }));

        $this->client->deleteOrganization($this->organization['id']);

        $listAfterRemoveOrganization = $this->client->listPromoCodes($this->testMaintainerId);
        $this->assertCount(0, array_filter($listAfterRemoveOrganization, function ($item) use ($promoCodeCode) {
            return $item['code'] === $promoCodeCode;
        }));
    }

    public function testDifferentOrganizationMaintainerCannotCreatePromoCodes()
    {
        $testMaintainer = $this->client->getMaintainer($this->testMaintainerId);

        $maintainerName = $this->getTestMaintainerPrefix() . ' - test maintainer';
        $newMaintainer = $this->createOrReplaceMaintainer([
            'name' => $maintainerName,
            'defaultConnectionMysqlId' => $testMaintainer['defaultConnectionMysqlId'],
            'defaultConnectionRedshiftId' => $testMaintainer['defaultConnectionRedshiftId'],
            'defaultConnectionSnowflakeId' => $testMaintainer['defaultConnectionSnowflakeId'],
        ]);

        $this->client->addUserToMaintainer($newMaintainer['id'], ['id' => $this->normalUser['id']]);

        $this->expectException(ClientException::class);
        $this->expectExceptionCode(403);
        $this->expectExceptionMessage(sprintf('You don\'t have access to the organization %s', $this->organization['id']));
        $this->normalUserClient->createPromoCode($this->testMaintainerId, [
            'code' => 'TEST-' . time(),
            'expirationDays' => rand(5, 20),
            'organizationId' => $this->organization['id'],
            'projectTemplateStringId' => 'poc6months',
        ]);
    }

    public function testListUsedPromoCodesCreateProjectRemoveProject()
    {
        $promoCodeCode = 'TEST-' . time();
        $this->client->createPromoCode($this->testMaintainerId, [
            'code' => $promoCodeCode,
            'expirationDays' => rand(5, 20),
            'organizationId' => $this->organization['id'],
            'projectTemplateStringId' => 'poc6months',
        ]);
        $project = $this->normalUserClient->createProjectFromPromoCode($promoCodeCode);

        $usedPromoCodesAfterCreateProject = array_filter($this->normalUserClient->listUsedPromoCodes(), function ($val) use ($promoCodeCode) {
            if ($val['code'] === $promoCodeCode) {
                return true;
            }
            return false;
        });
        $this->assertEquals(1, count($usedPromoCodesAfterCreateProject));

        $this->normalUserClient->deleteProject($project['id']);

        $usedPromoCodesAfterRemoveProject = array_filter($this->normalUserClient->listUsedPromoCodes(), function ($val) use ($promoCodeCode) {
            if ($val['code'] === $promoCodeCode) {
                return true;
            }
            return false;
        });
        $this->assertEquals(0, count($usedPromoCodesAfterRemoveProject));
    }

    public function testSuperAdminCanListAndCreatePromoCodes()
    {
        $promoCodesBeforeCreate = $this->client->listPromoCodes($this->testMaintainerId);
        $promoCode = $this->client->createPromoCode($this->testMaintainerId, [
            'code' => 'TEST-' . time(),
            'expirationDays' => rand(5, 20),
            'organizationId' => $this->organization['id'],
            'projectTemplateStringId' => 'poc6months',
        ]);
        $promoCodesAfterCreate = $this->client->listPromoCodes($this->testMaintainerId);

        $this->assertEquals(count($promoCodesBeforeCreate) + 1, count($promoCodesAfterCreate));

        $this->assertEquals($promoCode, end($promoCodesAfterCreate));
    }

    public function testCannotCreateDuplicatePromoCode()
    {
        $promoCodeCode = 'TEST-' . time();
        $promoCode = [
            'code' => $promoCodeCode,
            'expirationDays' => rand(5, 20),
            'organizationId' => $this->organization['id'],
            'projectTemplateStringId' => 'poc6months',
        ];
        $addedPromoCode = $this->client->createPromoCode($this->testMaintainerId, $promoCode);

        $this->assertEquals($promoCode['code'], $addedPromoCode['code']);

        $this->expectException(ClientException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage(sprintf('Promo code %s already exists', $promoCodeCode));

        $this->client->createPromoCode($this->testMaintainerId, $promoCode);
    }

    public function testOrganizationAdminCannotListPromoCode()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $this->expectException(ClientException::class);
        $this->expectExceptionCode(403);
        $this->expectExceptionMessage(sprintf('You don\'t have access to maintainer %s', $this->testMaintainerId));

        $this->normalUserClient->listPromoCodes($this->testMaintainerId);
    }

    public function testOrganizationAdminCannotCreatePromoCode()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $this->expectException(ClientException::class);
        $this->expectExceptionCode(403);
        $this->expectExceptionMessage(sprintf('You don\'t have access to maintainer %s', $this->testMaintainerId));
        $this->normalUserClient->createPromoCode($this->testMaintainerId, [
            'code' => 'TEST-' . time(),
            'expirationDays' => rand(5, 20),
            'organizationId' => $this->organization['id'],
            'projectTemplateStringId' => 'poc6months',
        ]);
    }

    public function testRandomAdminCannotCreatePromoCode()
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionCode(403);
        $this->expectExceptionMessage('You can\'t access project templates');
        $this->normalUserClient->createPromoCode($this->testMaintainerId, [
            'code' => 'TEST-' . time(),
            'expirationDays' => rand(5, 20),
            'organizationId' => $this->organization['id'],
            'projectTemplateStringId' => 'poc6months',
        ]);
    }

    public function testInvalidOrganization()
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionCode(404);
        $this->expectExceptionMessage('Organization 0 not found');
        $this->client->createPromoCode($this->testMaintainerId, [
            'code' => 'TEST-' . time(),
            'expirationDays' => rand(5, 20),
            'organizationId' => 0,
            'projectTemplateStringId' => 'poc6months',
        ]);
    }

    public function testNonexistsProjectTemplate()
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionCode(404);
        $this->expectExceptionMessage('Project template not found');
        $this->client->createPromoCode($this->testMaintainerId, [
            'code' => 'TEST-' . time(),
            'expirationDays' => rand(5, 20),
            'organizationId' => $this->organization['id'],
            'projectTemplateStringId' => ProjectTemplatesTest::TEST_NONEXISTS_PROJECT_TEMPLATE_STRING_ID,
        ]);
    }

    public function testRandomAdminCreateProjectFromPromoCodes()
    {
        $testingPromoCode = 'TEST-' . time();
        $this->client->createPromoCode($this->testMaintainerId, [
            'code' => $testingPromoCode,
            'expirationDays' => rand(5, 20),
            'organizationId' => $this->organization['id'],
            'projectTemplateStringId' => 'poc',
        ]);

        $newProject = $this->normalUserClient->createProjectFromPromoCode($testingPromoCode);
        $detailProject = $this->normalUserClient->getProject($newProject['id']);
        unset($detailProject['organization'], $detailProject['backends'], $detailProject['fileStorage']);

        $this->assertEquals($detailProject, $newProject);
    }

    public function testCannotCreateDuplicateProjectFromPromoCode()
    {
        $testingPromoCode = 'TEST-' . time();

        $this->client->createPromoCode($this->testMaintainerId, [
            'code' => $testingPromoCode,
            'expirationDays' => rand(5, 20),
            'organizationId' => $this->organization['id'],
            'projectTemplateStringId' => 'poc',
        ]);

        $this->normalUserClient->createProjectFromPromoCode($testingPromoCode);
        $this->expectException(ClientException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage(sprintf('Promo code %s is already used.', $testingPromoCode));
        $this->normalUserClient->createProjectFromPromoCode($testingPromoCode);
    }

    public function testCreateProjectFromNonexistsPromoCode()
    {
        $testingPromoCode = 'TEST-' . time();

        $this->expectException(ClientException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage(sprintf('Specified code was not found or is no longer valid.'));
        $this->normalUserClient->createProjectFromPromoCode($testingPromoCode);
    }

    public function testCannotCreateProjectFromPromoCodeDeletedOrganization()
    {
        $testingPromoCode = 'TEST-' . time();

        $this->client->createPromoCode($this->testMaintainerId, [
            'code' => $testingPromoCode,
            'expirationDays' => rand(5, 20),
            'organizationId' => $this->organization['id'],
            'projectTemplateStringId' => 'poc',
        ]);

        $this->client->deleteOrganization($this->organization['id']);
        $this->expectException(ClientException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage(sprintf('Specified code was not found or is no longer valid.'));
        $this->normalUserClient->createProjectFromPromoCode($testingPromoCode);
    }
}
