<?php
/**
 * Created by PhpStorm.
 * User: martinhalamicek
 * Date: 15/10/15
 * Time: 15:29
 */

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

class PromoCodesTest extends ClientTestCase
{

    public function testListAndCreatePromoCodes()
    {
        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org for promo codes',
        ]);

        $promoCodesBeforeCreate = $this->client->listPromoCodesRequest($this->testMaintainerId);
        $promoCode = $this->client->createPromoCodeRequest($this->testMaintainerId, [
            'code' => 'TEST-' . time(),
            'expirationDays' => rand(5, 20),
            'organizationId' => $organization['id'],
            'projectTemplateStringId' => 'poc15DaysGuideMode',
        ]);
        $promoCodesAfterCreate = $this->client->listPromoCodesRequest($this->testMaintainerId);

        $this->assertEquals(count($promoCodesBeforeCreate) + 1, count($promoCodesAfterCreate));

        $this->assertEquals($promoCode, $promoCodesAfterCreate[0]);
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
            $this->assertEquals(400, $e->getCode());
        }
    }
}
