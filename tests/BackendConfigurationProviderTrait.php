<?php

declare(strict_types=1);

namespace Keboola\ManageApiTest;

use Keboola\ManageApiTest\Utils\EnvVariableHelper;

trait BackendConfigurationProviderTrait
{
    public static function getSnowflakeBackendCreateOptions(): array
    {
        return [
            'backend' => 'snowflake',
            'host' => EnvVariableHelper::getKbcTestSnowflakeHost(),
            'warehouse' => EnvVariableHelper::getKbcTestSnowflakeWarehouse(),
            'username' => EnvVariableHelper::getKbcTestSnowflakeBackendName(),
            'password' => EnvVariableHelper::getKbcTestSnowflakeBackendPassword(),
            'region' => EnvVariableHelper::getKbcTestSnowflakeBackendRegion(),
            'owner' => 'keboola',
            'technicalOwner' => 'internal',
        ];
    }
}
