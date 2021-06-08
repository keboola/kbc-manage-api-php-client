<?php

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Backend;
use Keboola\ManageApi\Client;
use Keboola\StorageApi\Client as StorageClient;
use PHPUnit\Framework\TestCase;

class ClientTestCase extends TestCase
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
     * @var Client super-admin
     */
    protected $client;

    /**
     * @var Client
     */
    protected $normalUserClient;

    /** @var Client */
    protected $normalUserWithMfaClient;

    protected $testMaintainerId;

    protected $normalUser;

    protected $superAdmin;

    /** @var array */
    protected $normalUserWithMfa;

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

    /**
     * @return Client
     */
    protected function getClient(array $options)
    {
        $tokenParts = explode('-', $options['token']);
        $tokenAgentString = '';
        if (count($tokenParts) === 2) {
            $tokenAgentString = sprintf(
                'Token: %s, ',
                $tokenParts[0]
            );
        }

        $options['userAgent'] = sprintf(
            '%s%sStack: %s, %sTest: %s',
            $this->getBuildId(),
            $this->getSuiteName(),
            $options['url'],
            $tokenAgentString,
            $this->getTestName()
        );
        return new Client($options);
    }

    /**
     * @param array $token
     * @return StorageClient
     */
    protected function getStorageClient($options)
    {
        $tokenParts = explode('-', $options['token']);
        $tokenAgentString = '';
        if (count($tokenParts) === 3) {
            // token comes in from of <projectId>-<tokenId>-<hash>
            $tokenAgentString = sprintf(
                'Project: %s, Token: %s, ',
                $tokenParts[0],
                $tokenParts[1]
            );
        }
        $options['userAgent'] = sprintf(
            '%s%sStack: %s, %sTest: %s',
            $this->getBuildId(),
            $this->getSuiteName(),
            $options['url'],
            $tokenAgentString,
            $this->getTestName()
        );

        return new StorageClient($options);
    }

    /**
     * @return string
     */
    protected function getTestName()
    {
        return get_class($this) . '::' . $this->getName();
    }


    public function setUp()
    {
        $this->client = $this->getClient([
            'token' => getenv('KBC_MANAGE_API_TOKEN'),
            'url' => getenv('KBC_MANAGE_API_URL'),
            'backoffMaxTries' => 0,
        ]);
        $this->normalUserClient = $this->getClient([
            'token' => getenv('KBC_TEST_ADMIN_TOKEN'),
            'url' => getenv('KBC_MANAGE_API_URL'),
            'backoffMaxTries' => 0,
        ]);
        $this->normalUserWithMfaClient = $this->getClient([
            'token' => getenv('KBC_TEST_ADMIN_WITH_MFA_TOKEN'),
            'url' => getenv('KBC_MANAGE_API_URL'),
        ]);

        $this->testMaintainerId = (int) getenv('KBC_TEST_MAINTAINER_ID');

        $this->normalUser = $this->normalUserClient->verifyToken()['user'];
        $this->superAdmin = $this->client->verifyToken()['user'];
        $this->normalUserWithMfa = $this->normalUserWithMfaClient->verifyToken()['user'];

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

    /**
     * @return string
     */
    protected function getBuildId()
    {
        $buildId = '';
        if (getenv('TRAVIS_BUILD_ID')) {
            $buildId = sprintf('Build id: %s, ', getenv('TRAVIS_BUILD_ID'));
        }
        return $buildId;
    }

    /**
     * @return string
     */
    protected function getSuiteName()
    {
        $testSuiteName = '';
        if (getenv('SUITE_NAME')) {
            $testSuiteName = sprintf('Suite: %s, ', getenv('SUITE_NAME'));
        }
        return $testSuiteName;
    }

    protected function createProjectWithNormalAdminMember(int $organizationId, ?string $name = 'My test'): int
    {
        $project = $this->normalUserClient->createProject($organizationId, [
            'name' => $name,
            'defaultBackend' => Backend::REDSHIFT,
        ]);

        return $project['id'];
    }

    protected function createProjectWithSuperAdminMember(int $organizationId, ?string $name = 'My test'): int
    {
        $project = $this->client->createProject($organizationId, [
            'name' => $name,
            'defaultBackend' => Backend::REDSHIFT,
        ]);

        return $project['id'];
    }

    protected function createProjectWithAdminHavingMfaEnabled(int $organizationId, ?string $name = 'My test'): int
    {
        $project = $this->normalUserWithMfaClient->createProject($organizationId, [
            'name' => $name,
            'defaultBackend' => Backend::REDSHIFT,
        ]);

        return $project['id'];
    }

    /**
     * @param Client $client
     * @param int $organizationId
     * @param array<mixed> $params
     * @return int
     */
    protected function createRedshiftProjectForClient($client, int $organizationId, $params = [])
    {
        $params['defaultBackend'] = Backend::REDSHIFT;
        return $client->createProject($organizationId, $params);
    }
}
