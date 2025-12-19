<?php

declare(strict_types=1);

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Backend;
use PHPUnit\Framework\TestCase;

final class BackendTest extends TestCase
{
    public function testDefaultBackend(): void
    {
        $this->assertEquals('snowflake', Backend::getDefaultBackend());
    }
}
