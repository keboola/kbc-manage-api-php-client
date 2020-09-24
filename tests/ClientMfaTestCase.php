<?php
namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Client;

abstract class ClientMfaTestCase extends ClientTestCase
{
    public const DUMMY_USER_EMAIL = 'spam+spam@keboola.com';

    /** @var Client */
    protected $normalUserWithMfaClient;

    /** @var array */
    protected $normalUserWithMfa;

    /**
     * Test setup
     * - Create empty organization
     * - Add dummy user to maintainer. Remove all other members
     * - Add user having MFA enabled to organization. Remove all other members
     */
    public function setUp()
    {
        parent::setUp();

        $this->normalUserWithMfaClient = $this->getClient([
            'token' => getenv('KBC_TEST_ADMIN_WITH_MFA_TOKEN'),
            'url' => getenv('KBC_MANAGE_API_URL'),
        ]);

        $this->normalUserWithMfa = $this->normalUserWithMfaClient->verifyToken()['user'];
    }

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

    protected function createProjectWithAdminHavingMfaEnabled(int $organizationId): int
    {
        $project = $this->normalUserWithMfaClient->createProject($organizationId, [
            'name' => 'My test',
        ]);

        return $project['id'];
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
