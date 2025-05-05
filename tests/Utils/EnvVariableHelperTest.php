<?php

declare(strict_types=1);

namespace Keboola\ManageApi\Test\Utils;

use Keboola\ManageApiTest\Utils\EnvVariableHelper;
use Keboola\ManageApiTest\Utils\MissingEnvVariableException;
use PHPUnit\Framework\TestCase;

class EnvVariableHelperTest extends TestCase
{
    private string $originalManageApiUrl = '';
    private string $originalTestAbsKey = '';

    protected function setUp(): void
    {
        parent::setUp();
        // Backup original values
        $this->originalManageApiUrl = getenv('KBC_MANAGE_API_URL') ?: '';
        $this->originalTestAbsKey = getenv('TEST_ABS_ACCOUNT_KEY') ?: '';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Restore original values to avoid affecting other tests
        putenv('KBC_MANAGE_API_URL=' . $this->originalManageApiUrl);
        putenv('TEST_ABS_ACCOUNT_KEY=' . $this->originalTestAbsKey);
    }

    public function testGetKbcManageApiUrlSuccess(): void
    {
        $expectedUrl = 'https://example.com';
        putenv('KBC_MANAGE_API_URL=' . $expectedUrl);
        self::assertSame($expectedUrl, EnvVariableHelper::getKbcManageApiUrl());
    }

    public function testGetKbcManageApiUrlMissing(): void
    {
        putenv('KBC_MANAGE_API_URL'); // Unset the variable

        $this->expectException(MissingEnvVariableException::class);
        $this->expectExceptionMessage(
            "Missing required environment variable 'KBC_MANAGE_API_URL'. Description: URL where Keboola Connection is running" .
            "\nPlease set it according to the instructions in README.md."
        );
        EnvVariableHelper::getKbcManageApiUrl();
    }

    public function testGetKbcManageApiUrlEmpty(): void
    {
        putenv('KBC_MANAGE_API_URL='); // Set to empty string

        $this->expectException(MissingEnvVariableException::class);
        $this->expectExceptionMessage(
            "Missing required environment variable 'KBC_MANAGE_API_URL'. Description: URL where Keboola Connection is running" .
            "\nPlease set it according to the instructions in README.md."
        );
        EnvVariableHelper::getKbcManageApiUrl();
    }

    public function testGetTestAbsAccountKeySuccess(): void
    {
        $expectedKey = 'someAbsKey123';
        putenv('TEST_ABS_ACCOUNT_KEY=' . $expectedKey);
        self::assertSame($expectedKey, EnvVariableHelper::getTestAbsAccountKey());
    }

    public function testGetTestAbsAccountKeyMissing(): void
    {
        putenv('TEST_ABS_ACCOUNT_KEY'); // Unset the variable

        $this->expectException(MissingEnvVariableException::class);
        $this->expectExceptionMessage(
            "Missing required environment variable 'TEST_ABS_ACCOUNT_KEY'. Description: First secret key for Azure Storage account" .
            "\nPlease set it according to the instructions in README.md."
        );
        EnvVariableHelper::getTestAbsAccountKey();
    }
}
