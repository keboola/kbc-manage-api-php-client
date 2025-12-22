<?php

declare(strict_types=1);

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Client;
use Keboola\ManageApiTest\Utils\EnvVariableHelper;

class BaseFeatureTest extends ClientTestCase
{
    public const MANAGE_TOKEN_CLIENT = 'manageTokenClient';
    public const SESSION_TOKEN_CLIENT = 'sessionTokenClient';

    /** @var array<mixed> */
    protected array $organization = [];

    public function setUp(): void
    {
        parent::setUp();

        // 1. add a user as placeholder for the maintainer, later I will need to remove a $this->client and it wouldn't work if there was only one
        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => 'devel-tests+spam@keboola.com']);

        // 2. Resetting the settings maintainer admins created in tests, because in some tests we add a user to the maintainer to make it an maintainer admin.
        foreach ($this->client->listMaintainerMembers($this->testMaintainerId) as $member) {
            if ($member['id'] === $this->normalUser['id']) {
                $this->client->removeUserFromMaintainer($this->testMaintainerId, $member['id']);
            }

            if ($member['id'] === $this->superAdmin['id']) {
                $this->client->removeUserFromMaintainer($this->testMaintainerId, $member['id']);
            }
        }

        // 3. create new org for tests, because in some tests we add a user to the org to make it an org admin.
        // This way we ensure that every time the test is run, everything is reset
        $this->organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        // 4. add a user as placeholder for the organization, later I will need to remove a $this->client and it wouldn't work if there was only one
        $this->client->addUserToOrganization($this->organization['id'], ['email' => $this->normalUserWithMfa['email']]);

        // 5. remove superAdmin from org, we want to have super admin without maintainer and org. admin
        $this->client->removeUserFromOrganization($this->organization['id'], $this->superAdmin['id']);
    }

    protected function getNormalUserClient(): CLient
    {
        if ($this->usesDataProvider() && in_array(self::SESSION_TOKEN_CLIENT, $this->providedData(), true)) {
            $sessionToken = $this->normalUserClient->createSessionToken();
            return $this->getClient([
                'token' => $sessionToken['token'],
                'url' => EnvVariableHelper::getKbcManageApiUrl(),
                'backoffMaxTries' => 0,
            ]);
        }

        return $this->normalUserClient;
    }

    protected function getSuperAdminClient(): Client
    {
        if ($this->usesDataProvider() && in_array(self::SESSION_TOKEN_CLIENT, $this->providedData(), true)) {
            $sessionToken = $this->client->createSessionToken();
            return $this->getClient([
                'token' => $sessionToken['token'],
                'url' => EnvVariableHelper::getKbcManageApiUrl(),
                'backoffMaxTries' => 0,
            ]);
        }

        return $this->client;
    }

    public static function canBeManageByAdminProvider(): array
    {
        return [
            'admin can manage with manage token' => [true, self::MANAGE_TOKEN_CLIENT],
            'admin cannot manage with manage token' => [false, self::MANAGE_TOKEN_CLIENT],
            'admin can manage with session token' => [true, self::SESSION_TOKEN_CLIENT],
            'admin cannot manage with session token' => [false, self::SESSION_TOKEN_CLIENT],
        ];
    }

    public static function provideVariousOfTokensClient(): array
    {
        return [
            'manage token' => [self::MANAGE_TOKEN_CLIENT],
            'session token' => [self::SESSION_TOKEN_CLIENT],
        ];
    }
}
