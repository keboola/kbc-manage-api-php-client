<?php
/**
 * Created by PhpStorm.
 * User: martinhalamicek
 * Date: 15/10/15
 * Time: 15:29
 */

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Client;

class CommonTest extends ClientTestCase
{

    public function testVerifyAdminToken()
    {
        $token = $this->client->verifyToken();

        $this->assertInternalType('int', $token['id']);
        $this->assertNotEmpty($token['description']);
        $this->assertNotEmpty($token['created']);
        $this->assertFalse($token['isDisabled']);
        $this->assertFalse($token['isExpired']);
        $this->assertInternalType('array', $token['scopes']);
        $this->assertEquals($token['type'], 'admin');
    }

    public function testVerifySuperToken()
    {
        $client = new Client([
            'token' => getenv('KBC_SUPER_API_TOKEN'),
            'url' => getenv('KBC_MANAGE_API_URL'),
            'backoffMaxTries' => 1,
        ]);
        $token = $client->verifyToken();

        $this->assertInternalType('int', $token['id']);
        $this->assertNotEmpty($token['description']);
        $this->assertNotEmpty($token['created']);
        $this->assertFalse($token['isDisabled']);
        $this->assertFalse($token['isExpired']);
        $this->assertInternalType('array', $token['scopes']);
        $this->assertEquals($token['type'], 'super');
    }
}