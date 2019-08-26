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

    public function testSuperAdminCanListAndCreatePromoCodes()
    {
        $promoCodesBeforeCreate = $this->client->listPromoCodesRequest($this->testMaintainerId);
        $promoCode = $this->client->createPromoCodeRequest($this->testMaintainerId, [
            'code' => 'TEST-' . time(),
            'expirationDays' => rand(5, 20),
            'organizationId' => $this->organization['id'],
            'projectTemplateStringId' => 'poc15DaysGuideMode',
        ]);
        $promoCodesAfterCreate = $this->client->listPromoCodesRequest($this->testMaintainerId);

        $this->assertEquals(count($promoCodesBeforeCreate) + 1, count($promoCodesAfterCreate));

        $this->assertEquals($promoCode, $promoCodesAfterCreate[0]);
    }

    public function testCannotCreateDuplicatePromoCode()
    {
        $promoCode = [
            'code' => 'TEST-' . time(),
            'expirationDays' => rand(5, 20),
            'organizationId' => $this->organization['id'],
            'projectTemplateStringId' => 'poc15DaysGuideMode',
        ];
        $addedPromoCode = $this->client->createPromoCodeRequest($this->testMaintainerId, $promoCode);

        $this->assertEquals($promoCode['code'], $addedPromoCode['code']);

        $this->expectException(ClientException::class);
        $this->expectExceptionCode(400);
        $this->client->createPromoCodeRequest($this->testMaintainerId, $promoCode);
    }

    public function testOrganizationAdminCannotListPromoCode()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $this->expectException(ClientException::class);
        $this->expectExceptionCode(403);

        $this->normalUserClient->listPromoCodesRequest($this->testMaintainerId);
    }

    public function testOrganizationAdminCannotCreatePromoCode()
    {
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUser['email']]);

        $this->expectException(ClientException::class);
        $this->expectExceptionCode(404);

        $this->normalUserClient->createPromoCodeRequest($this->testMaintainerId, [
            'code' => 'TEST-' . time(),
            'expirationDays' => rand(5, 20),
            'organizationId' => $this->organization['id'],
            'projectTemplateStringId' => 'poc15DaysGuideMode',
        ]);
    }

    public function testInvalidOrganization()
    {
        try {
            $this->client->createPromoCodeRequest($this->testMaintainerId, [
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
            $this->client->createPromoCodeRequest($this->testMaintainerId, [
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
