<?php

namespace Keboola\ManageApiTest;

use GuzzleHttp\Exception\ClientException;

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
        $this->assertNotEmpty($token['lastUsed']);
        $this->assertFalse($token['isSessionToken']);

        $lastUsed = $token['lastUsed'];

        sleep(1);
        $token = $this->client->verifyToken();
        $this->assertNotEquals($lastUsed, $token['lastUsed']);
    }

    public function testVerifySuperToken()
    {
        $client = $this->getClient([
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
        $this->assertFalse($token['isSessionToken']);
    }

    public function testInvalidRequestBody()
    {
        $client = new \GuzzleHttp\Client([
            'base_uri' => getenv('KBC_MANAGE_API_URL'),
        ]);

        $requestOptions = [
            'headers' => [
                'X-KBC-ManageApiToken' => getenv('KBC_MANAGE_API_TOKEN'),
                'Accept-Encoding' => 'gzip',
                'Content-Type' => 'application/json',
                'User-Agent' => 'Keboola Manage API PHP Client',
            ],
            'body' => '{"key": "invalid json}',
        ];

        try {
            $client->request(
                'POST',
                '/manage/maintainers/' . $this->testMaintainerId . '/organizations',
                $requestOptions
            );
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            $this->assertContains('Request body not valid', (string) $e->getResponse()->getBody());
        }
    }
}
