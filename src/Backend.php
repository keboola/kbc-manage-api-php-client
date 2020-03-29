<?php

declare(strict_types=1);

namespace Keboola\ManageApi;

final class Backend
{
    public const REDSHIFT = 'redshift';
    public const SNOWFLAKE = 'snowflake';
    public const SYNAPSE = 'synapse';

    public static function getDefaultBackend(): string
    {
        return self::SNOWFLAKE;
    }
}
