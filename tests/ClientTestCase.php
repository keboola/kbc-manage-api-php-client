<?php

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Client;

class ClientTestCase extends \PHPUnit_Framework_TestCase
{

    public const PRODUCTION_HOSTS = [
        'connection.keboola.com',
        'connection.eu-central-1.keboola.com',
    ];

    /**
     * Prefix of all maintainers created by tests
     */
    public const TESTS_MAINTAINER_PREFIX = 'KBC_MANAGE_TESTS';

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var Client
     */
    protected $normalUserClient;

    protected $testMaintainerId;

    protected $normalUser;

    protected $superAdmin;

    public static function setUpBeforeClass()
    {
        $manageApiUrl = getenv('KBC_MANAGE_API_URL');

        if (in_array(parse_url($manageApiUrl, PHP_URL_HOST), self::PRODUCTION_HOSTS)) {
            throw new \Exception('Tests cannot be executed against production host - ' . $manageApiUrl);
        }

        // cleanup organizations and projects created in testing maintainer
        $client = new Client([
            'token' => getenv('KBC_MANAGE_API_TOKEN'),
            'url' => $manageApiUrl,
            'backoffMaxTries' => 0,
        ]);
        $organizations = $client->listMaintainerOrganizations(getenv('KBC_TEST_MAINTAINER_ID'));
        foreach ($organizations as $organization) {
            foreach ($client->listOrganizationProjects($organization['id']) as $project) {
                $client->deleteProject($project['id']);
            }
            $client->deleteOrganization($organization['id']);
        }
    }

    public function setUp()
    {
        $this->client = new Client([
            'token' => getenv('KBC_MANAGE_API_TOKEN'),
            'url' => getenv('KBC_MANAGE_API_URL'),
            'backoffMaxTries' => 0,
        ]);
        $this->normalUserClient = new \Keboola\ManageApi\Client([
            'token' => getenv('KBC_TEST_ADMIN_TOKEN'),
            'url' => getenv('KBC_MANAGE_API_URL'),
            'backoffMaxTries' => 0,
        ]);
        $this->testMaintainerId = (int) getenv('KBC_TEST_MAINTAINER_ID');

        $this->normalUser = $this->normalUserClient->verifyToken()['user'];
        $this->superAdmin = $this->client->verifyToken()['user'];

        // cleanup maintainers created by tests
        $maintainers = $this->client->listMaintainers();

        foreach ($maintainers as $maintainer) {
            if ($maintainer['id'] === $this->testMaintainerId) {
                if (!$this->findMaintainerMember($this->testMaintainerId, $this->superAdmin['email'])) {
                    $this->client->addUserToMaintainer(
                        $this->testMaintainerId,
                        ['email' => $this->superAdmin['email']]
                    );
                }

                $members = $this->client->listMaintainerMembers($maintainer['id']);
                foreach ($members as $member) {
                    if ($member['id'] !== $this->superAdmin['id']) {
                        $this->client->removeUserFromMaintainer($maintainer['id'], $member['id']);
                    }
                }
            } elseif (strpos($maintainer['name'], self::TESTS_MAINTAINER_PREFIX) === 0) {
                $this->client->deleteMaintainer($maintainer['id']);
            }
        }
    }

    public function getRandomFeatureSuffix()
    {
        return uniqid('', true);
    }

    protected function findMaintainerMember(int $maintainerId, string $userEmail): ?array
    {
        $members = $this->client->listMaintainerMembers($maintainerId);

        foreach ($members as $member) {
            if ($member['email'] === $userEmail) {
                return $member;
            }
        }

        return null;
    }

    protected function findOrganizationMember(int $organizationId, string $userEmail): ?array
    {
        $members = $this->client->listOrganizationUsers($organizationId);

        foreach ($members as $member) {
            if ($member['email'] === $userEmail) {
                return $member;
            }
        }

        return null;
    }

    protected function findProjectUser(int $projectId, string $userEmail): ?array
    {
        $projectUsers = $this->client->listProjectUsers($projectId);

        foreach ($projectUsers as $projectUser) {
            if ($projectUser['email'] === $userEmail) {
                return $projectUser;
            }
        }

        return null;
    }
}
