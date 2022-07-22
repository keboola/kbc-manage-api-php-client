<?php

declare(strict_types=1);

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Backend;
use Keboola\ManageApi\ClientException;

class DataPlanesTest extends ClientTestCase
{
    private const TEST_DATA_PLANE_OWNER = 'ManageApiTest\DataPlanesTest';

    private const TEST_DATA_PLANE_DATA = [
        'owner' => self::TEST_DATA_PLANE_OWNER,
        'parameters' => [
            'foo' => 'bar',
        ],
    ];

    public function setUp(): void
    {
        parent::setUp();

        foreach ($this->client->listMaintainers() as $maintainer) {
            if ($maintainer['name'] === self::TEST_DATA_PLANE_OWNER) {
                foreach ($this->client->listMaintainerOrganizations($maintainer['id']) as $organization) {
                    foreach ($this->client->listOrganizationProjects($organization['id']) as $project) {
                        $this->client->deleteProject($project['id']);
                    }

                    $this->client->deleteOrganization($organization['id']);
                }

                $this->client->deleteMaintainer($maintainer['id']);
            }
        }

        foreach ($this->client->listDataPlanes() as $dataPlane) {
            if ($dataPlane['owner'] === self::TEST_DATA_PLANE_OWNER) {
                $this->client->removeDataPlane($dataPlane['id']);
            }
        }
    }

    public function testCreateDataPlane(): void
    {
        $dataPlane = $this->client->createDataPlane(self::TEST_DATA_PLANE_DATA);
        $this->assertIsTestDataPlane($dataPlane);
    }

    public function testNormalAdminCannotCreateDataPlane(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Data planes can be managed only by super admin.');
        $this->expectExceptionCode(403);

        $this->normalUserClient->createDataPlane(self::TEST_DATA_PLANE_DATA);
    }

    public function testListDataPlanesAndDataPlaneDetail(): void
    {
        $dataPlane = $this->client->createDataPlane(self::TEST_DATA_PLANE_DATA);

        $dataPlanes = array_filter($this->client->listDataPlanes(), function (array $dataPlane) {
            return $dataPlane['owner'] === self::TEST_DATA_PLANE_OWNER;
        });

        self::assertCount(1, $dataPlanes);
        self::assertSame($dataPlane, reset($dataPlanes));

        $dataPlaneDetail = $this->client->getDataPlane($dataPlane['id']);
        self::assertSame($dataPlane, $dataPlaneDetail);
    }

    public function testNormalAdminCannotListDataPlanes(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Data planes details are available only for super admin or application token having "data-planes:read" scope.');
        $this->expectExceptionCode(403);

        $this->normalUserClient->listDataPlanes();
    }

    public function testUpdateDataPlane(): void
    {
        $dataPlane = $this->client->createDataPlane(self::TEST_DATA_PLANE_DATA);
        $this->assertIsTestDataPlane($dataPlane, ['foo' => 'bar']);

        $newParams = [
            'foo' => 'bar',
            'meta' => [
                'hello' => 'world',
            ],
        ];

        $dataPlane = $this->client->updateDataPlane($dataPlane['id'], ['parameters' => $newParams]);
        $this->assertIsTestDataPlane($dataPlane, $newParams);
    }

    public function testNormalAdminCannotUpdateDataPlanes(): void
    {
        $dataPlane = $this->client->createDataPlane(self::TEST_DATA_PLANE_DATA);
        $this->assertIsTestDataPlane($dataPlane, ['foo' => 'bar']);

        $newParams = [
            'foo' => 'bar',
            'meta' => [
                'hello' => 'world',
            ],
        ];

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Data planes can be managed only by super admin.');
        $this->expectExceptionCode(403);

        $this->normalUserClient->updateDataPlane($dataPlane['id'], ['parameters' => $newParams]);
    }

    public function testNormalAdminCannotGetDataPlaneDetail(): void
    {
        $dataPlane = $this->client->createDataPlane(self::TEST_DATA_PLANE_DATA);
        $this->assertIsTestDataPlane($dataPlane, ['foo' => 'bar']);

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Data planes details are available only for super admin or application token having "data-planes:read" scope.');
        $this->expectExceptionCode(403);

        $this->normalUserClient->getDataPlane($dataPlane['id']);
    }

    public function testDeleteDataPlane(): void
    {
        $dataPlane = $this->client->createDataPlane(self::TEST_DATA_PLANE_DATA);

        $dataPlanes = array_filter($this->client->listDataPlanes(), function (array $dataPlane) {
            return $dataPlane['owner'] === self::TEST_DATA_PLANE_OWNER;
        });

        self::assertCount(1, $dataPlanes);

        $this->client->removeDataPlane($dataPlane['id']);

        $dataPlanes = array_filter($this->client->listDataPlanes(), function (array $dataPlane) {
            return $dataPlane['owner'] === self::TEST_DATA_PLANE_OWNER;
        });

        self::assertCount(0, $dataPlanes);
    }

    public function testNormalAdminCannotDeleteDataPlane(): void
    {
        $dataPlane = $this->client->createDataPlane(self::TEST_DATA_PLANE_DATA);

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Data planes can be managed only by super admin.');
        $this->expectExceptionCode(403);

        $this->normalUserClient->removeDataPlane($dataPlane['id']);
    }

    public function testCreateAndUpdateMaintainerWithDataPlane(): void
    {
        $dataPlane = $this->client->createDataPlane(self::TEST_DATA_PLANE_DATA);

        $maintainer = $this->client->createMaintainer([
            'name' => self::TEST_DATA_PLANE_OWNER,
            'dataPlaneId' => $dataPlane['id'],
        ]);

        self::assertSame($dataPlane['id'], $maintainer['dataPlaneId']);

        $maintainer = $this->client->updateMaintainer($maintainer['id'], [
            'dataPlaneId' => null,
        ]);

        self::assertNull($maintainer['dataPlaneId']);
    }

    public function testCreateProjectWithDataPlaneAndUpdateMaintainerDataPlane(): void
    {
        $testMaintainer = $this->client->getMaintainer($this->testMaintainerId);
        $dataPlane = $this->client->createDataPlane(self::TEST_DATA_PLANE_DATA);

        $maintainer = $this->client->createMaintainer([
            'name' => self::TEST_DATA_PLANE_OWNER,
            'dataPlaneId' => $dataPlane['id'],
            'defaultConnectionSnowflakeId' => $testMaintainer['defaultConnectionSnowflakeId'],
        ]);

        $organization = $this->client->createOrganization($maintainer['id'], [
            'name' => self::TEST_DATA_PLANE_OWNER,
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => self::TEST_DATA_PLANE_OWNER,
            'defaultBackend' => Backend::SNOWFLAKE,
            'dataRetentionTimeInDays' => 1,
        ]);

        $project = $this->client->getProject($project['id']);
        self::assertArrayHasKey('dataPlanes', $project);
        self::assertSame([$dataPlane], $project['dataPlanes']);

        $maintainer = $this->client->updateMaintainer($maintainer['id'], [
            'dataPlaneId' => null,
        ]);
        $project = $this->client->getProject($project['id']);
        self::assertArrayHasKey('dataPlanes', $project);
        self::assertSame([], $project['dataPlanes']);
    }

    private function assertIsTestDataPlane(array $dataPlane, array $expectedParams = ['foo' => 'bar']): void
    {
        self::assertSame([
            'id',
            'owner',
            'parameters',
            'created',
            'creator',
        ], array_keys($dataPlane));

        self::assertIsInt($dataPlane['id']);
        self::assertSame('ManageApiTest\DataPlanesTest', $dataPlane['owner']);
        self::assertSame($expectedParams, $dataPlane['parameters']);
        self::assertNotEmpty($dataPlane['created']);
        self::assertSame([
            'id' => $this->superAdmin['id'],
            'name' => $this->superAdmin['name'],
        ], $dataPlane['creator']);
    }
}
