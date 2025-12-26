<?php

declare(strict_types=1);

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\SnowflakeNameHelper;
use PHPUnit\Framework\TestCase;

final class SnowflakeNameHelperTest extends TestCase
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

    public function testGetUserRoleName(): void
    {
        $helper = new SnowflakeNameHelper('test_prefix');
        $this->assertSame('testuser', $helper->getUserRoleName('testuser'));
        $this->assertSame('TEST_USER', $helper->getUserRoleName('TEST_USER'));
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
        $this->assertSame('user123', $helper->getUserRoleName('user123'));
        $this->assertSame($expectedPrefix . '_SAML_INTEGRATION', $helper->getSamlIntegrationName());
    }

    public function testConsistencyAcrossMultipleInstances(): void
    {
        $helper1 = new SnowflakeNameHelper('test_prefix');
        $helper2 = new SnowflakeNameHelper('test_prefix');

        $this->assertSame($helper1->getSamlIntegrationName(), $helper2->getSamlIntegrationName());
    }

    public function testUserRoleNameWithSpecialCharacters(): void
    {
        $helper = new SnowflakeNameHelper('test_prefix');

        $this->assertSame('user-name', $helper->getUserRoleName('user-name'));
        $this->assertSame('user.name', $helper->getUserRoleName('user.name'));
        $this->assertSame('user_name', $helper->getUserRoleName('user_name'));
        $this->assertSame('user123', $helper->getUserRoleName('user123'));
    }
}
