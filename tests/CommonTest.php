<?php

namespace Keboola\ManageApiTest;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Keboola\ManageApiTest\Utils\EnvVariableHelper;
use Psr\Http\Message\ResponseInterface;

class CommonTest extends ClientTestCase
{
    public function testVerifyAdminToken(): void
    {
        $token = $this->client->verifyToken();

        $this->assertNotEmpty($token['description']);
        $this->assertNotEmpty($token['created']);
        $this->assertFalse($token['isDisabled']);
        $this->assertFalse($token['isExpired']);
        $this->assertEquals($token['type'], 'admin');
        $this->assertNotEmpty($token['lastUsed']);
        $this->assertFalse($token['isSessionToken']);

        $lastUsed = $token['lastUsed'];

        sleep(1);
        $token = $this->client->verifyToken();
        $this->assertNotEquals($lastUsed, $token['lastUsed']);
    }

    public function testVerifySuperToken(): void
    {
        $client = $this->getClient([
            'token' => EnvVariableHelper::getKbcSuperApiToken(),
            'url' => EnvVariableHelper::getKbcManageApiUrl(),
            'backoffMaxTries' => 1,
        ]);
        $token = $client->verifyToken();

        $this->assertNotEmpty($token['description']);
        $this->assertNotEmpty($token['created']);
        $this->assertFalse($token['isDisabled']);
        $this->assertFalse($token['isExpired']);
        $this->assertEquals($token['type'], 'super');
        $this->assertFalse($token['isSessionToken']);
    }

    public function testInvalidRequestBody(): void
    {
        $client = new Client([
            'base_uri' => EnvVariableHelper::getKbcManageApiUrl(),
        ]);

        $requestOptions = [
            'headers' => [
                'X-KBC-ManageApiToken' => EnvVariableHelper::getKbcManageApiToken(),
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
            $this->fail('Should fail');
        } catch (ClientException $e) {
            $this->assertEquals(400, $e->getCode());
            /** @var ResponseInterface $response */
            $response = $e->getResponse();
            $this->assertStringContainsString('Request body not valid', (string) $response->getBody());
        }
    }
}
