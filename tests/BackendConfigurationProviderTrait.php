<?php

declare(strict_types=1);

namespace Keboola\ManageApiTest;

trait BackendConfigurationProviderTrait
{
    public function getSnowflakeBackendCreateOptions(): array
    {
        return [
            'backend' => 'snowflake',
            'host' => getenv('KBC_TEST_SNOWFLAKE_HOST'),
            'warehouse' => getenv('KBC_TEST_SNOWFLAKE_WAREHOUSE'),
            'username' => getenv('KBC_TEST_SNOWFLAKE_BACKEND_NAME'),
            'password' => getenv('KBC_TEST_SNOWFLAKE_BACKEND_PASSWORD'),
            'region' => getenv('KBC_TEST_SNOWFLAKE_BACKEND_REGION'),
            'owner' => 'keboola',
        ];
    }
}
