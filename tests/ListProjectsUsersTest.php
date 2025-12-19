<?php

declare(strict_types=1);

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

final class ListProjectsUsersTest extends ClientTestCase
{
    private const EXCEPTION_MESSAGE = 'Only organization members can get list of projects users';

    private $organization;

    public function setUp(): void
    {
        parent::setUp();

        $this->client->addUserToMaintainer($this->testMaintainerId, ['email' => 'devel-tests+spam@keboola.com']);

        foreach ($this->client->listMaintainerMembers($this->testMaintainerId) as $member) {
            if ($member['id'] === $this->normalUser['id']) {
                $this->client->removeUserFromMaintainer($this->testMaintainerId, $member['id']);
            }

            if ($member['id'] === $this->superAdmin['id']) {
                $this->client->removeUserFromMaintainer($this->testMaintainerId, $member['id']);
            }
        }

        $this->organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $this->client->addUserToOrganization($this->organization['id'], ['email' => 'devel-tests+spam@keboola.com']);
        $this->client->removeUserFromOrganization($this->organization['id'], $this->superAdmin['id']);
    }

    public function testSuperAdminCannotGetList(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage(self::EXCEPTION_MESSAGE);
        $this->expectExceptionCode(403);

        $this->client->listOrganizationProjectsUsers($this->organization['id']);
    }

    public function testMaintainerCannotGetList(): void
    {
        $this->client->addUserToMaintainer($this->testMaintainerId, [
            'email' => $this->normalUser['email'],
        ]);

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage(self::EXCEPTION_MESSAGE);
        $this->expectExceptionCode(403);

        $this->normalUserClient->listOrganizationProjectsUsers($this->organization['id']);
    }

    public function testOrganizationUserCanGetList(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], [
            'email' => $this->normalUser['email'],
        ]);

        $testProject = $this->createRedshiftProjectForClient($this->normalUserClient, $this->organization['id'], [
            'name' => 'Test Project',
        ]);

        $this->normalUserClient->addUserToProject($testProject['id'], [
            'email' => 'devel-tests+spam@keboola.com',
        ]);

        $this->normalUserClient->addUserToProject($testProject['id'], [
            'email' => $this->superAdmin['email'],
        ]);

        $this->normalUserClient->removeUserFromProject($testProject['id'], $this->normalUser['id']);

        $results = $this->normalUserClient->listOrganizationProjectsUsers($this->organization['id']);

        $this->assertCount(2, $results);
        foreach ($results as $result) {
            $this->assertArrayHasKey('id', $result);
            $this->assertArrayHasKey('name', $result);
            $this->assertArrayHasKey('email', $result);
        }
    }

    public function testUserOneOfProjectInOrg(): void
    {
        $this->client->addUserToOrganization($this->organization['id'], [
            'email' => $this->normalUser['email'],
        ]);

        $this->createRedshiftProjectForClient($this->normalUserClient, $this->organization['id'], [
            'name' => 'Test Project',
        ]);

        $this->normalUserClient->removeUserFromOrganization($this->organization['id'], $this->normalUser['id']);

        $this->expectException(ClientException::class);
        $this->expectExceptionCode(403);

        $this->normalUserClient->listOrganizationProjectsUsers($this->organization['id']);
    }

    public function testNormalUserCannotGetList(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionCode(403);

        $this->normalUserClient->listOrganizationProjectsUsers($this->organization['id']);
    }
}
