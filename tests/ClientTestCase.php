<?php

namespace Keboola\ManageApiTest;

use Exception;
use Keboola\ManageApi\Backend;
use Keboola\ManageApi\Client;
use Keboola\ManageApi\ClientException;
use Keboola\ManageApiTest\Utils\EnvVariableHelper;
use Keboola\StorageApi\Client as StorageClient;
use PHPUnit\Framework\TestCase;
use PHPUnitRetry\RetryTrait;

class ClientTestCase extends TestCase
{
    protected const CAN_MANAGE_PROJECT_SETTINGS_FEATURE_NAME = 'can-update-project-settings';

    use RetryTrait;

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

    protected Client $unverifiedUserClient;

    /** @var Client */
    protected $normalUserWithMfaClient;

    protected $testMaintainerId;

    protected $normalUser;

    protected $unverifiedUser;

    protected $superAdmin;

    /** @var array */
    protected $normalUserWithMfa;

    public static function setUpBeforeClass(): void
    {
        $manageApiUrl = EnvVariableHelper::getKbcManageApiUrl();

        if (in_array(parse_url($manageApiUrl, PHP_URL_HOST), self::PRODUCTION_HOSTS)) {
            throw new Exception('Tests cannot be executed against production host - ' . $manageApiUrl);
        }

        // cleanup organizations and projects created in testing maintainer
        $client = new Client([
            'token' => EnvVariableHelper::getKbcManageApiToken(),
            'url' => $manageApiUrl,
            'backoffMaxTries' => 0,
        ]);
        $organizations = $client->listMaintainerOrganizations(EnvVariableHelper::getKbcTestMaintainerId());
        foreach ($organizations as $organization) {
            foreach ($client->listOrganizationProjects($organization['id']) as $project) {
                $client->deleteProject($project['id']);
            }
            $client->deleteOrganization($organization['id']);
        }
    }

    protected function getClient(array $options): Client
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

    protected function cleanupFeatures(string $featureName, string $type): void
    {
        $features = $this->client->listFeatures(['type' => $type]);
        foreach ($features as $item) {
            if ($item['name'] === $featureName) {
                $this->client->removeFeature($item['id']);
                break;
            }
        }
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

    public function getSessionTokenClient(Client $client): Client
    {
        return $this->getClient([
            'token' => $client->createSessionToken()['token'],
            'url' => EnvVariableHelper::getKbcManageApiUrl(),
            'backoffMaxTries' => 0,
        ]);
    }

    /**
     * @return string
     */
    protected function getTestName()
    {
        return get_class($this) . '::' . $this->getName();
    }


    public function setUp(): void
    {
        $this->client = $this->getClient([
            'token' => EnvVariableHelper::getKbcManageApiToken(),
            'url' => EnvVariableHelper::getKbcManageApiUrl(),
            'backoffMaxTries' => 0,
        ]);
        $this->normalUserClient = $this->getClient([
            'token' => EnvVariableHelper::getKbcTestAdminToken(),
            'url' => EnvVariableHelper::getKbcManageApiUrl(),
            'backoffMaxTries' => 0,
        ]);

        $this->unverifiedUserClient = $this->getClient([
            'token' => EnvVariableHelper::getKbcTestUnverifiedAdminToken(),
            'url' => EnvVariableHelper::getKbcManageApiUrl(),
            'backoffMaxTries' => 0,
        ]);

        $this->normalUserWithMfaClient = $this->getClient([
            'token' => EnvVariableHelper::getKbcTestAdminWithMfaToken(),
            'url' => EnvVariableHelper::getKbcManageApiUrl(),
        ]);
        $this->testMaintainerId = (int) EnvVariableHelper::getKbcTestMaintainerId();

        $tokenInfo = $this->normalUserClient->verifyToken();
        $this->assertArrayHasKey('user', $tokenInfo);
        $this->normalUser = $tokenInfo['user'];

        $tokenInfo = $this->unverifiedUserClient->verifyToken();
        $this->assertArrayHasKey('user', $tokenInfo);
        $this->unverifiedUser = $tokenInfo['user'];

        $tokenInfo = $this->client->verifyToken();
        $this->assertArrayHasKey('user', $tokenInfo);
        $this->superAdmin = $tokenInfo['user'];

        $tokenInfo = $this->normalUserWithMfaClient->verifyToken();
        $this->assertArrayHasKey('user', $tokenInfo);
        $this->normalUserWithMfa = $tokenInfo['user'];

        // cleanup maintainers created by tests
        $maintainers = $this->client->listMaintainers();

        foreach ($maintainers as $maintainer) {
            if ($maintainer['id'] === $this->testMaintainerId) {
                // ensure the maintainer member exists
                if (!$this->findMaintainerMember($this->testMaintainerId, $this->superAdmin['email'])) {
                    $this->client->addUserToMaintainer(
                        $this->testMaintainerId,
                        ['email' => $this->superAdmin['email']]
                    );
                }

                // ensure there are no other maintainer group members
                $members = $this->client->listMaintainerMembers($maintainer['id']);
                foreach ($members as $member) {
                    if ($member['id'] !== $this->superAdmin['id']) {
                        $this->client->removeUserFromMaintainer($maintainer['id'], $member['id']);
                    }
                }
            } elseif (strpos($maintainer['name'], self::TESTS_MAINTAINER_PREFIX) === 0) {
                // cleanup orgranizations and projects to delete maintainer at the end
                // get organizations for maintainer
                $organizations = $this->client->listMaintainerOrganizations($maintainer['id']);
                foreach ($organizations as $organization) {
                    // get projects for organization
                    $projects = $this->client->listOrganizationProjects($organization['id']);
                    foreach ($projects as $project) {
                        $this->client->deleteProject($project['id']);
                    }
                    $this->client->deleteOrganization($organization['id']);
                }
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
     * @return array
     */
    protected function createRedshiftProjectForClient($client, int $organizationId, $params = [])
    {
        $params['defaultBackend'] = Backend::REDSHIFT;
        return $client->createProject($organizationId, $params);
    }

    protected function waitForProjectPurge($projectId)
    {
        $startTime = time();
        $maxWaitTimeSeconds = 120;

        // wait until project is deleted
        do {
            $isProjectDeleted = false;
            try {
                $this->client->getProject($projectId);
            } catch (ClientException $e) {
                $isProjectDeleted = true;
            }
            if (time() - $startTime > $maxWaitTimeSeconds) {
                throw new Exception('Project delete timed out.');
            }
            sleep(1);
        } while ($isProjectDeleted !== true);

        // reset the clock
        $startTime = time();

        // purge all data async
        $purgeResponse = $this->client->purgeDeletedProject($projectId);
        $this->assertArrayHasKey('commandExecutionId', $purgeResponse);
        $this->assertNotNull($purgeResponse['commandExecutionId']);
        do {
            $deletedProject = $this->client->getDeletedProject($projectId);
            if (time() - $startTime > $maxWaitTimeSeconds) {
                throw new Exception('Project purge timed out.');
            }
            sleep(1);
        } while ($deletedProject['isPurged'] !== true);
        $this->assertNotNull($deletedProject['purgedTime']);
    }

    protected function generateDescriptionForTestObject(): string
    {
        $testSuiteName = '';

        /** @phpstan-ignore-next-line */
        if (SUITE_NAME) {
            $testSuiteName = sprintf('%s::', SUITE_NAME);
        }

        return $testSuiteName . get_class($this) . '\\' . $this->getName();
    }

    public function sortByKey($data, $sortKey): array
    {
        $comparsion = function ($attrLeft, $attrRight) use ($sortKey) {
            return strcmp($attrLeft[$sortKey], $attrRight[$sortKey]);
        };
        usort($data, $comparsion);
        return $data;
    }

    protected function testFeatureName(): string
    {
        return 'test-feature-' . sha1($this->generateDescriptionForTestObject());
    }
}
