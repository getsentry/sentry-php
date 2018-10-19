<?php

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\State\Hub;
use function Sentry\init;

class SdkTest extends TestCase
{
    public function testInit()
    {
        init();
        $this->assertNotNull(Hub::getCurrent()->getClient());
    }
}
