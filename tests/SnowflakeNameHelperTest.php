<?php

declare(strict_types=1);

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\SnowflakeNameHelper;
use PHPUnit\Framework\TestCase;

class SnowflakeNameHelperTest extends TestCase
{
    public function testConstructorWithLowercasePrefix(): void
    {
        $helper = new SnowflakeNameHelper('test_prefix');
        $this->assertSame('TEST_PREFIX', $helper->getPrefix());
    }

    public function testConstructorWithUppercasePrefix(): void
    {
        $helper = new SnowflakeNameHelper('TEST_PREFIX');
        $this->assertSame('TEST_PREFIX', $helper->getPrefix());
    }

    public function testConstructorWithMixedCasePrefix(): void
    {
        $helper = new SnowflakeNameHelper('TeSt_PrEfIx');
        $this->assertSame('TEST_PREFIX', $helper->getPrefix());
    }

    public function testGetInternalDatabaseName(): void
    {
        $helper = new SnowflakeNameHelper('test_prefix');
        $this->assertSame('TEST_PREFIX_INTERNAL', $helper->getInternalDatabaseName());
    }

    public function testGetNetworkRulesSchemaName(): void
    {
        $helper = new SnowflakeNameHelper('test_prefix');
        $this->assertSame('NETWORK_RULES', $helper->getNetworkRulesSchemaName());
    }

    public function testGetNetworkRuleName(): void
    {
        $helper = new SnowflakeNameHelper('test_prefix');
        $this->assertSame('TEST_PREFIX_NETWORK_RULE', $helper->getNetworkRuleName());
    }

    public function testGetUserRoleName(): void
    {
        $helper = new SnowflakeNameHelper('test_prefix');
        $this->assertSame('testuser_ROLE', $helper->getUserRoleName('testuser'));
        $this->assertSame('TEST_USER_ROLE', $helper->getUserRoleName('TEST_USER'));
    }

    public function testGetSystemIpsOnlyPolicyName(): void
    {
        $helper = new SnowflakeNameHelper('test_prefix');
        $this->assertSame('TEST_PREFIX_SYSTEM_IPS_ONLY', $helper->getSystemIpsOnlyPolicyName());
    }

    public function testGetSamlIntegrationName(): void
    {
        $helper = new SnowflakeNameHelper('test_prefix');
        $this->assertSame('TEST_PREFIX_SAML_INTEGRATION', $helper->getSamlIntegrationName());
    }


    public function testAllMethodsWithComplexPrefix(): void
    {
        $helper = new SnowflakeNameHelper('my-complex_test.prefix123');
        $expectedPrefix = 'MY-COMPLEX_TEST.PREFIX123';

        $this->assertSame($expectedPrefix, $helper->getPrefix());
        $this->assertSame($expectedPrefix . '_INTERNAL', $helper->getInternalDatabaseName());
        $this->assertSame('NETWORK_RULES', $helper->getNetworkRulesSchemaName());
        $this->assertSame($expectedPrefix . '_NETWORK_RULE', $helper->getNetworkRuleName());
        $this->assertSame('user123_ROLE', $helper->getUserRoleName('user123'));
        $this->assertSame($expectedPrefix . '_SYSTEM_IPS_ONLY', $helper->getSystemIpsOnlyPolicyName());
        $this->assertSame($expectedPrefix . '_SAML_INTEGRATION', $helper->getSamlIntegrationName());
    }

    public function testConsistencyAcrossMultipleInstances(): void
    {
        $helper1 = new SnowflakeNameHelper('test_prefix');
        $helper2 = new SnowflakeNameHelper('test_prefix');

        $this->assertSame($helper1->getInternalDatabaseName(), $helper2->getInternalDatabaseName());
        $this->assertSame($helper1->getNetworkRuleName(), $helper2->getNetworkRuleName());
        $this->assertSame($helper1->getSystemIpsOnlyPolicyName(), $helper2->getSystemIpsOnlyPolicyName());
        $this->assertSame($helper1->getSamlIntegrationName(), $helper2->getSamlIntegrationName());
    }

    public function testUserRoleNameWithSpecialCharacters(): void
    {
        $helper = new SnowflakeNameHelper('test_prefix');

        $this->assertSame('user-name_ROLE', $helper->getUserRoleName('user-name'));
        $this->assertSame('user.name_ROLE', $helper->getUserRoleName('user.name'));
        $this->assertSame('user_name_ROLE', $helper->getUserRoleName('user_name'));
        $this->assertSame('user123_ROLE', $helper->getUserRoleName('user123'));
    }
}
