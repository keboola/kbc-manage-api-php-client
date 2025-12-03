<?php

declare(strict_types=1);

namespace Keboola\ManageApi;

class SnowflakeNameHelper
{
    private string $prefix;

    public function __construct(string $prefix)
    {
        $this->prefix = strtoupper($prefix);
    }

    public function getUserRoleName(string $username): string
    {
        return $username;
    }

    public function getSamlIntegrationName(): string
    {
        return $this->prefix . '_SAML_INTEGRATION';
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }
}
