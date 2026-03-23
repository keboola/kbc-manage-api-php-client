<?php

declare(strict_types=1);

namespace Keboola\ManageApi;

final class Backend
{
    public const string SNOWFLAKE = 'snowflake';
    public const string BIGQUERY = 'bigquery';
    public const string SYNAPSE = 'synapse';
    public const string EXASOL = 'exasol';
    public const string TERADATA = 'teradata';
    public const string POSTGRES = 'postgres';

    public static function getDefaultBackend(): string
    {
        return self::SNOWFLAKE;
    }
}
