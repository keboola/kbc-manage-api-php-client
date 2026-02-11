<?php

declare(strict_types=1);

namespace Keboola\ManageApi;

final class Backend
{
    public const SNOWFLAKE = 'snowflake';
    public const SYNAPSE = 'synapse';
    public const EXASOL = 'exasol';
    public const TERADATA = 'teradata';

    public static function getDefaultBackend(): string
    {
        return self::SNOWFLAKE;
    }
}
