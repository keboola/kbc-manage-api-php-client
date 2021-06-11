<?php

namespace Keboola\ManageApiTest;

class ParallelClientTestCase extends ClientTestCase
{
    /**
     * Prefix of all maintainers created by tests
     */
    public const TESTS_MAINTAINER_PREFIX = 'KBC_MANAGE_PARA-TESTS';

    public static function setUpBeforeClass()
    {
        $manageApiUrl = getenv('KBC_MANAGE_API_URL');

        if (in_array(parse_url($manageApiUrl, PHP_URL_HOST), self::PRODUCTION_HOSTS)) {
            throw new \Exception('Tests cannot be executed against production host - ' . $manageApiUrl);
        }
    }

    protected function getTestMaintainerPrefix()
    {
        return sprintf(
            '%s-%s',
            self::TESTS_MAINTAINER_PREFIX,
            $this->getParallelPrefix()
        );
    }

    protected function getParallelPrefix()
    {
        return md5(sprintf(
            '%s-%s',
            self::getSuiteName(),
            $this->getTestName()
        ));
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
        $this->normalUser2Client = $this->getClient([
            'token' => getenv('KBC_TEST_ADMIN2_TOKEN'),
            'url' => getenv('KBC_MANAGE_API_URL'),
            'backoffMaxTries' => 0,
        ]);
        $this->normalUserWithMfaClient = $this->getClient([
            'token' => getenv('KBC_TEST_ADMIN_WITH_MFA_TOKEN'),
            'url' => getenv('KBC_MANAGE_API_URL'),
        ]);

        $this->normalUser = $this->normalUserClient->verifyToken()['user'];
        $this->superAdmin = $this->client->verifyToken()['user'];
        $this->normalUser2 = $this->normalUser2Client->verifyToken()['user'];
        $this->normalUserWithMfa = $this->normalUserWithMfaClient->verifyToken()['user'];

        // cleanup maintainers created by tests
        $maintainers = $this->client->listMaintainers();
        $maintainerPrefix = $this->getTestMaintainerPrefix();

        $parallelTestMaintainer = null;
        $syncTestsMaintainerId = (int) getenv('KBC_TEST_MAINTAINER_ID');
        $syncTestsMaintainer = null;
        foreach ($maintainers as $item) {
            if ($item['name'] === $maintainerPrefix) {
                $parallelTestMaintainer = $item;
            }
            if ($item['id'] === $syncTestsMaintainerId) {
                $syncTestsMaintainer = $item;
            }
        }

        if ($parallelTestMaintainer !== null) {
            $isChangeInDefaultBackends = false;
            // check for default backend change in sync maintainer
            if ($syncTestsMaintainer['defaultConnectionMysqlId'] !== $parallelTestMaintainer['defaultConnectionMysqlId']) {
                $isChangeInDefaultBackends = true;
            }
            if ($syncTestsMaintainer['defaultConnectionRedshiftId'] !== $parallelTestMaintainer['defaultConnectionRedshiftId']) {
                $isChangeInDefaultBackends = true;
            }
            if ($syncTestsMaintainer['defaultConnectionSnowflakeId'] !== $parallelTestMaintainer['defaultConnectionSnowflakeId']) {
                $isChangeInDefaultBackends = true;
            }
            if ($syncTestsMaintainer['defaultConnectionSynapseId'] !== $parallelTestMaintainer['defaultConnectionSynapseId']) {
                $isChangeInDefaultBackends = true;
            }
            if ($isChangeInDefaultBackends) {
                $parallelTestMaintainer = $this->client->updateMaintainer($parallelTestMaintainer['id'], [
                    'name' => $maintainerPrefix,
                    'defaultConnectionMysqlId' => $syncTestsMaintainer['defaultConnectionMysqlId'],
                    'defaultConnectionRedshiftId' => $syncTestsMaintainer['defaultConnectionRedshiftId'],
                    'defaultConnectionSnowflakeId' => $syncTestsMaintainer['defaultConnectionSnowflakeId'],
                    'defaultConnectionSynapseId' => $syncTestsMaintainer['defaultConnectionSynapseId'],
                ]);
            }
        }

        if ($parallelTestMaintainer === null) {
            $parallelTestMaintainer = $this->client->createMaintainer([
                'name' => $maintainerPrefix,
                'defaultConnectionMysqlId' => $syncTestsMaintainer['defaultConnectionMysqlId'],
                'defaultConnectionRedshiftId' => $syncTestsMaintainer['defaultConnectionRedshiftId'],
                'defaultConnectionSnowflakeId' => $syncTestsMaintainer['defaultConnectionSnowflakeId'],
                'defaultConnectionSynapseId' => $syncTestsMaintainer['defaultConnectionSynapseId'],
            ]);
        }
        $this->testMaintainerId = $parallelTestMaintainer['id'];

        // clean up projects and organizations
        $organizations = $this->client->listMaintainerOrganizations($this->testMaintainerId);
        foreach ($organizations as $organization) {
            foreach ($this->client->listOrganizationProjects($organization['id']) as $project) {
                $this->client->deleteProject($project['id']);
            }
            $this->client->deleteOrganization($organization['id']);
        }

        // ensure super admin is present
        if (!$this->findMaintainerMember($this->testMaintainerId, $this->superAdmin['email'])) {
            $this->client->addUserToMaintainer(
                $this->testMaintainerId,
                ['email' => $this->superAdmin['email']]
            );
        }

        // clean up other users
        $members = $this->client->listMaintainerMembers($this->testMaintainerId);
        foreach ($members as $member) {
            if ($member['id'] !== $this->superAdmin['id']) {
                $this->client->removeUserFromMaintainer($this->testMaintainerId, $member['id']);
            }
        }

        // drop other maintainers from same test
        foreach ($maintainers as $maintainer) {
            if ($maintainer['id'] !== $this->testMaintainerId && strpos($maintainer['name'], $maintainerPrefix) === 0) {
                $this->client->deleteMaintainer($maintainer['id']);
            }
        }
    }
}
