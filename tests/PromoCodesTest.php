<?php

declare(strict_types=1);

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

final class PromoCodesTest extends ClientTestCase
{

    private $organization;

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
            foreach ($this->client->listOrganizationProjects($organization['id']) as $project) {
                $this->client->deleteProject($project['id']);
            }
            $this->client->deleteOrganization($organization['id']);
        }

        $this->organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org for promo codes',
        ]);

        $this->client->addUserToOrganization($this->organization['id'], ['email' => 'devel-tests@keboola.com']);
        $this->client->removeUserFromOrganization($this->organization['id'], $this->superAdmin['id']);
    }

    public function testMaintainerAdminCanListAndCreatePromoCodes(): void
    {
        $this->client->addUserToMaintainer($this->testMaintainerId, ['id' => $this->normalUser['id']]);

        $promoCodesBeforeCreate = $this->normalUserClient->listPromoCodes($this->testMaintainerId);

        $promoCode = $this->normalUserClient->createPromoCode($this->testMaintainerId, [
            'code' => 'TEST-' . time(),
            'expirationDays' => random_int(5, 20),
            'organizationId' => $this->organization['id'],
            'projectTemplateStringId' => 'poc6months',
        ]);

        $promoCodesAfterCreate = $this->normalUserClient->listPromoCodes($this->testMaintainerId);
        $this->assertCount(count($promoCodesBeforeCreate) + 1, $promoCodesAfterCreate);

        $this->assertEquals($promoCode, end($promoCodesAfterCreate));
    }

    public function testCannotListPromoCodesFromRemovedOrganization(): void
    {
        $promoCodeCode = 'TEST-' . time();
        $this->client->createPromoCode($this->testMaintainerId, [
            'code' => $promoCodeCode,
            'expirationDays' => random_int(5, 20),
            'organizationId' => $this->organization['id'],
            'projectTemplateStringId' => 'poc6months',
        ]);

        $listBeforeRemoveOrganization = $this->client->listPromoCodes($this->testMaintainerId);
        $this->assertCount(1, array_filter($listBeforeRemoveOrganization, fn(array $item): bool => $item['code'] === $promoCodeCode));

        $this->client->deleteOrganization($this->organization['id']);

        $listAfterRemoveOrganization = $this->client->listPromoCodes($this->testMaintainerId);
        $this->assertCount(0, array_filter($listAfterRemoveOrganization, fn(array $item): bool => $item['code'] === $promoCodeCode));
    }

    public function testDifferentOrganizationMaintainerCannotCreatePromoCodes(): void
    {
        $testMaintainer = $this->client->getMaintainer($this->testMaintainerId);

        $maintainerName = self::TESTS_MAINTAINER_PREFIX . ' - test maintainer';
        $newMaintainer = $this->client->createMaintainer([
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
            'expirationDays' => random_int(5, 20),
            'organizationId' => $this->organization['id'],
            'projectTemplateStringId' => 'poc6months',
        ]);
    }

    public function testListUsedPromoCodesCreateProjectRemoveProject(): void
    {
        $promoCodeCode = 'TEST-' . time();
        $this->client->createPromoCode($this->testMaintainerId, [
            'code' => $promoCodeCode,
            'expirationDays' => random_int(5, 20),
            'organizationId' => $this->organization['id'],
            'projectTemplateStringId' => 'poc6months',
        ]);
        $project = $this->normalUserClient->createProjectFromPromoCode($promoCodeCode);

        $usedPromoCodesAfterCreateProject = array_filter($this->normalUserClient->listUsedPromoCodes(), fn(array $val): bool => $val['code'] === $promoCodeCode);
        $this->assertCount(1, $usedPromoCodesAfterCreateProject);

        $this->normalUserClient->deleteProject($project['id']);

        $usedPromoCodesAfterRemoveProject = array_filter($this->normalUserClient->listUsedPromoCodes(), fn(array $val): bool => $val['code'] === $promoCodeCode);
        $this->assertCount(0, $usedPromoCodesAfterRemoveProject);
    }

    public function testSuperAdminCanListAndCreatePromoCodes(): void
    {
        $promoCodesBeforeCreate = $this->client->listPromoCodes($this->testMaintainerId);
        $promoCode = $this->client->createPromoCode($this->testMaintainerId, [
            'code' => 'TEST-' . time(),
            'expirationDays' => random_int(5, 20),
            'organizationId' => $this->organization['id'],
            'projectTemplateStringId' => 'poc6months',
        ]);
        $promoCodesAfterCreate = $this->client->listPromoCodes($this->testMaintainerId);

        $this->assertCount(count($promoCodesBeforeCreate) + 1, $promoCodesAfterCreate);

        $this->assertEquals($promoCode, end($promoCodesAfterCreate));
    }

    public function testCannotCreateDuplicatePromoCode(): void
    {
        $promoCodeCode = 'TEST-' . time();
        $promoCode = [
            'code' => $promoCodeCode,
            'expirationDays' => random_int(5, 20),
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

    public function testOrganizationAdminCannotListPromoCode(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $this->expectException(ClientException::class);
        $this->expectExceptionCode(403);
        $this->expectExceptionMessage(sprintf('You don\'t have access to maintainer %s', $this->testMaintainerId));

        $this->normalUserClient->listPromoCodes($this->testMaintainerId);
    }

    public function testOrganizationAdminCannotCreatePromoCode(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $this->expectException(ClientException::class);
        $this->expectExceptionCode(403);
        $this->expectExceptionMessage(sprintf('You don\'t have access to maintainer %s', $this->testMaintainerId));
        $this->normalUserClient->createPromoCode($this->testMaintainerId, [
            'code' => 'TEST-' . time(),
            'expirationDays' => random_int(5, 20),
            'organizationId' => $this->organization['id'],
            'projectTemplateStringId' => 'poc6months',
        ]);
    }

    public function testRandomAdminCannotCreatePromoCode(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionCode(403);
        $this->expectExceptionMessage('You can\'t access project templates');
        $this->normalUserClient->createPromoCode($this->testMaintainerId, [
            'code' => 'TEST-' . time(),
            'expirationDays' => random_int(5, 20),
            'organizationId' => $this->organization['id'],
            'projectTemplateStringId' => 'poc6months',
        ]);
    }

    public function testInvalidOrganization(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionCode(404);
        $this->expectExceptionMessage('Organization 0 not found');
        $this->client->createPromoCode($this->testMaintainerId, [
            'code' => 'TEST-' . time(),
            'expirationDays' => random_int(5, 20),
            'organizationId' => 0,
            'projectTemplateStringId' => 'poc6months',
        ]);
    }

    public function testNonexistsProjectTemplate(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionCode(404);
        $this->expectExceptionMessage('Project template not found');
        $this->client->createPromoCode($this->testMaintainerId, [
            'code' => 'TEST-' . time(),
            'expirationDays' => random_int(5, 20),
            'organizationId' => $this->organization['id'],
            'projectTemplateStringId' => ProjectTemplatesTest::TEST_NONEXISTS_PROJECT_TEMPLATE_STRING_ID,
        ]);
    }

    public function testRandomAdminCreateProjectFromPromoCodes(): void
    {
        $testingPromoCode = 'TEST-' . time();
        $this->client->createPromoCode($this->testMaintainerId, [
            'code' => $testingPromoCode,
            'expirationDays' => random_int(5, 20),
            'organizationId' => $this->organization['id'],
            'projectTemplateStringId' => 'poc',
        ]);

        $newProject = $this->normalUserClient->createProjectFromPromoCode($testingPromoCode);
        $detailProject = $this->normalUserClient->getProject($newProject['id']);
        unset(
            $detailProject['organization'],
            $detailProject['backends'],
            $detailProject['fileStorage'],
        );

        $this->assertEquals($detailProject, $newProject);
    }

    public function testCannotCreateDuplicateProjectFromPromoCode(): void
    {
        $testingPromoCode = 'TEST-' . time();

        $this->client->createPromoCode($this->testMaintainerId, [
            'code' => $testingPromoCode,
            'expirationDays' => random_int(5, 20),
            'organizationId' => $this->organization['id'],
            'projectTemplateStringId' => 'poc',
        ]);

        $this->normalUserClient->createProjectFromPromoCode($testingPromoCode);
        $this->expectException(ClientException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage(sprintf('Promo code %s is already used.', $testingPromoCode));
        $this->normalUserClient->createProjectFromPromoCode($testingPromoCode);
    }

    public function testCreateProjectFromNonexistsPromoCode(): void
    {
        $testingPromoCode = 'TEST-' . time();

        $this->expectException(ClientException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('Specified code was not found or is no longer valid.');
        $this->normalUserClient->createProjectFromPromoCode($testingPromoCode);
    }

    public function testCannotCreateProjectFromPromoCodeDeletedOrganization(): void
    {
        $testingPromoCode = 'TEST-' . time();

        $this->client->createPromoCode($this->testMaintainerId, [
            'code' => $testingPromoCode,
            'expirationDays' => random_int(5, 20),
            'organizationId' => $this->organization['id'],
            'projectTemplateStringId' => 'poc',
        ]);

        $this->client->deleteOrganization($this->organization['id']);
        $this->expectException(ClientException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('Specified code was not found or is no longer valid.');
        $this->normalUserClient->createProjectFromPromoCode($testingPromoCode);
    }
}
