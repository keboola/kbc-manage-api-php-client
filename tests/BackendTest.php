<?php

declare(strict_types=1);

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Backend;
use PHPUnit\Framework\TestCase;

class BackendTest extends TestCase
{
    public function testDefaultBackend()
    {
        $this->assertEquals('snowflake', Backend::getDefaultBackend());
    }
}
