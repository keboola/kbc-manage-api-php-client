<?php
namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Client;

abstract class ClientMfaTestCase extends ClientTestCase
{
    public const DUMMY_USER_EMAIL = 'spam+spam@keboola.com';

    protected function findProjectUser(int $projectId, string $userEmail): ?array
    {
        $projectUsers = $this->normalUserWithMfaClient->listProjectUsers($projectId);

        foreach ($projectUsers as $projectUser) {
            if ($projectUser['email'] === $userEmail) {
                return $projectUser;
            }
        }

        return null;
    }

    protected function findOrganizationMember(int $organizationId, string $userEmail): ?array
    {
        $members = $this->normalUserWithMfaClient->listOrganizationUsers($organizationId);

        foreach ($members as $member) {
            if ($member['email'] === $userEmail) {
                return $member;
            }
        }

        return null;
    }
}
