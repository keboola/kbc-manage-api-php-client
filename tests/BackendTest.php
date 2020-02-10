<?php

declare(strict_types=1);

namespace Keboola\ManageApiTest;

use Keboola\ManageApi\Backend;

class BackendTest extends \PHPUnit_Framework_TestCase
{
    public function testDefaultBackend()
    {
        $this->assertEquals('snowflake', Backend::getDefaultBackend());
    }
}
