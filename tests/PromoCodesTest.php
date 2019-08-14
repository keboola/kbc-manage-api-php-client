<?php
/**
 * Created by PhpStorm.
 * User: martinhalamicek
 * Date: 15/10/15
 * Time: 15:29
 */

namespace Keboola\ManageApiTest;

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
}
