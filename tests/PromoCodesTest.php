<?php

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

class PromoCodesTest extends ClientTestCase
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

        $this->organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org for promo codes',
        ]);

        $this->client->addUserToOrganization($this->organization['id'], ['email' => 'spam@keboola.com']);
        $this->client->removeUserFromOrganization($this->organization['id'], $this->superAdmin['id']);
    }

    public function testMaintainerAdminCanListAndCreatePromoCodes()
    {
        $this->client->addUserToMaintainer($this->testMaintainerId, ['id' => $this->normalUser['id']]);
        $this->normalUserClient->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $this->normalUserClient->listPromoCodes($this->testMaintainerId);

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
        $this->client->createPromoCode($this->testMaintainerId, [
            'code' => 'TEST-' . time(),
            'expirationDays' => rand(5, 20),
            'organizationId' => $this->organization['id'],
            'projectTemplateStringId' => 'poc6months',
        ]);

        $listBeforeRemoveOrganization = $this->client->listPromoCodes($this->testMaintainerId);

        $this->client->deleteOrganization($this->organization['id']);

        $listAfterRemoveOrganization = $this->client->listPromoCodes($this->testMaintainerId);

        $this->assertLessThan(count($listBeforeRemoveOrganization), count($listAfterRemoveOrganization));
    }

    public function testDifferentOrganizationMaintainerCannotCreatePromoCodes()
    {
        $testMaintainer = $this->client->getMaintainer($this->testMaintainerId);

        $maintainerName = self::TESTS_MAINTAINER_PREFIX . " - test maintainer";
        $newMaintainer = $this->client->createMaintainer([
            'name' => $maintainerName,
            'defaultConnectionMysqlId' => $testMaintainer['defaultConnectionMysqlId'],
            'defaultConnectionRedshiftId' => $testMaintainer['defaultConnectionRedshiftId'],
            'defaultConnectionSnowflakeId' => $testMaintainer['defaultConnectionSnowflakeId'],
        ]);

        $this->client->addUserToMaintainer($newMaintainer['id'], ['id' => $this->normalUser['id']]);

        $this->expectException(ClientException::class);
        $this->expectExceptionCode(404);
        $this->normalUserClient->createPromoCode($this->testMaintainerId, [
            'code' => 'TEST-' . time(),
            'expirationDays' => rand(5, 20),
            'organizationId' => $this->organization['id'],
            'projectTemplateStringId' => 'poc15DaysGuideMode',
        ]);
    }

    public function testSuperAdminCanListAndCreatePromoCodes()
    {
        $promoCodesBeforeCreate = $this->client->listPromoCodes($this->testMaintainerId);
        $promoCode = $this->client->createPromoCode($this->testMaintainerId, [
            'code' => 'TEST-' . time(),
            'expirationDays' => rand(5, 20),
            'organizationId' => $this->organization['id'],
            'projectTemplateStringId' => 'poc15DaysGuideMode',
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
            'projectTemplateStringId' => 'poc15DaysGuideMode',
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

        $this->normalUserClient->listPromoCodes($this->testMaintainerId);
    }

    public function testOrganizationAdminCannotCreatePromoCode()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $this->expectException(ClientException::class);
        $this->expectExceptionCode(404);

        $this->normalUserClient->createPromoCode($this->testMaintainerId, [
            'code' => 'TEST-' . time(),
            'expirationDays' => rand(5, 20),
            'organizationId' => $this->organization['id'],
            'projectTemplateStringId' => 'poc15DaysGuideMode',
        ]);
    }

    public function testRandomAdminCannotCreatePromoCode()
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionCode(404);
        $this->normalUserClient->createPromoCode($this->testMaintainerId, [
            'code' => 'TEST-' . time(),
            'expirationDays' => rand(5, 20),
            'organizationId' => $this->organization['id'],
            'projectTemplateStringId' => 'poc15DaysGuideMode',
        ]);
    }

    public function testInvalidOrganization()
    {
        try {
            $this->client->createPromoCode($this->testMaintainerId, [
                'code' => 'TEST-' . time(),
                'expirationDays' => rand(5, 20),
                'organizationId' => 0,
                'projectTemplateStringId' => 'poc15DaysGuideMode',
            ]);
            $this->fail('Organization not found');
        } catch (ClientException $e) {
            $this->assertEquals(404, $e->getCode());
        }
    }

    public function testInvalidProjectTemplate()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org for promo codes',
        ]);
        try {
            $this->client->createPromoCode($this->testMaintainerId, [
                'code' => 'TEST-' . time(),
                'expirationDays' => rand(5, 20),
                'organizationId' => $organization->id,
                'projectTemplateStringId' => 'testInvalidProjectTemplate',
            ]);
            $this->fail('Project template not found');
        } catch (ClientException $e) {
            $this->assertEquals(404, $e->getCode());
        }
    }
}
