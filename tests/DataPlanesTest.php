<?php

declare(strict_types=1);

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\ClientException;

class DataPlanesTest extends ClientTestCase
{
    private const TEST_DATA_PLANE_OWNER = 'ManageApiTest\DataPlanesTest';

    private const TEST_DATA_PLANE_DATA = [
        'owner' => self::TEST_DATA_PLANE_OWNER,
        'provider' => 'aws',
        'region' => 'eu-central',
        'parameters' => [
            'foo' => 'bar',
        ],
    ];

    public function setUp(): void
    {
        parent::setUp();

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

    public function testListDataPlanes(): void
    {
        $dataPlane = $this->client->createDataPlane(self::TEST_DATA_PLANE_DATA);

        $dataPlanes = array_filter($this->client->listDataPlanes(), function (array $dataPlane) {
            return $dataPlane['owner'] === self::TEST_DATA_PLANE_OWNER;
        });

        self::assertCount(1, $dataPlanes);
        self::assertSame($dataPlane, reset($dataPlanes));
    }

    public function testNormalAdminCannotListDataPlanes(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Data planes can be managed only by super admin.');
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

    private function assertIsTestDataPlane(array $dataPlane, array $expectedParams = ['foo' => 'bar']): void
    {
        self::assertSame([
            'id',
            'owner',
            'region',
            'provider',
            'parameters',
            'created',
            'creator',
        ], array_keys($dataPlane));

        self::assertIsInt($dataPlane['id']);
        self::assertSame('ManageApiTest\DataPlanesTest', $dataPlane['owner']);
        self::assertSame('eu-central', $dataPlane['region']);
        self::assertSame('aws', $dataPlane['provider']);
        self::assertSame($expectedParams, $dataPlane['parameters']);
        self::assertNotEmpty($dataPlane['created']);
        self::assertSame([
            'id' => $this->superAdmin['id'],
            'name' => $this->superAdmin['name'],
        ], $dataPlane['creator']);
    }
}