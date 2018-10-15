<?php

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\Hub;

class HubTest extends TestCase
{
    public function testWithScope()
    {
        $hub = new Hub();
    }
}
