<?php

declare(strict_types=1);

namespace Keboola\ManageApiTest;

use Retry\BackOff\ExponentialRandomBackOffPolicy;
use Retry\Policy\SimpleRetryPolicy;
use Retry\RetryProxy;

class BigqueryProjectsTest extends ClientTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $featuresToRemoveFromUsers = [
            self::CAN_MANAGE_PROJECT_SETTINGS_FEATURE_NAME,
        ];

        foreach ($featuresToRemoveFromUsers as $feature) {
            $this->client->removeUserFeature($this->normalUser['email'], $feature);
        }
    }

    public function testNormalUserWithFeatureCanUpdateTimezone(): void
    {
        $this->client->addUserFeature($this->normalUser['email'], self::CAN_MANAGE_PROJECT_SETTINGS_FEATURE_NAME);

        $organization = $this->client->createOrganization($this->testMaintainerId, [
            'name' => 'My org',
        ]);

        $project = $this->client->createProject($organization['id'], [
            'name' => 'My test',
            'dataRetentionTimeInDays' => 1,
        ]);

        $this->assertNull($project['timezone']);

        $backends = $this->client->listStorageBackend();
        $backendToAssign = null;
        foreach ($backends as $item) {
            if ($item['backend'] === 'bigquery') {
                $backendToAssign = $item;
            }
        }

        $gcsStorage = $this->client->listGcsFileStorage()[0];

        // assign supported storage
        $this->client->assignFileStorage(
            $project['id'],
            $gcsStorage['id']
        );

        $this->client->assignProjectStorageBackend(
            $project['id'],
            $backendToAssign['id']
        );

        $retryPolicy = new SimpleRetryPolicy(10);
        $backOffPolicy = new ExponentialRandomBackOffPolicy(
            1_000, // initial interval: 1s
            1.8,
            60_000, // max interval: 60s
        );
        $proxy = new RetryProxy($retryPolicy, $backOffPolicy);
        $updatedProject = $proxy->call(function () use ($project): array {
            return $this->normalUserClient->updateProject(
                $project['id'],
                [
                    'timezone' => 'America/Detroit',
                ],
            );
        });

        $this->assertSame('America/Detroit', $updatedProject['timezone']);

        $this->client->removeUserFeature($this->normalUser['email'], 'can-update-project-settings');
    }
}
