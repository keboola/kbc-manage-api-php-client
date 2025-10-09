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

    public function getInternalDatabaseName(): string
    {
        return $this->prefix . '_INTERNAL';
    }

    public function getNetworkRulesSchemaName(): string
    {
        return 'NETWORK_RULES';
    }

    public function getNetworkRuleName(): string
    {
        return $this->prefix . '_NETWORK_RULE';
    }

    public function getUserRoleName(string $username): string
    {
        return $username . '_ROLE';
    }

    public function getSystemIpsOnlyPolicyName(): string
    {
        return $this->prefix . '_SYSTEM_IPS_ONLY';
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
