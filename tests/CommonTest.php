<?php
/**
 * Created by PhpStorm.
 * User: martinhalamicek
 * Date: 15/10/15
 * Time: 15:29
 */

namespace Keboola\ManageApiTest;

class CommonTest extends ClientTestCase
{

    public function testVerifyToken()
    {
        $token = $this->client->verifyToken();

        $this->assertInternalType('int', $token['id']);
        $this->assertNotEmpty($token['description']);
        $this->assertNotEmpty($token['created']);
        $this->assertFalse($token['isDisabled']);
        $this->assertFalse($token['isExpired']);
        $this->assertInternalType('array', $token['scopes']);
        $this->assertContains('projects', $token['scopes']);
    }
}