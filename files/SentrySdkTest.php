<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\SentrySdk;
use Sentry\State\Hub;

final class SentrySdkTest extends TestCase
{
    public function testInit(): void
    {
        $hub1 = SentrySdk::init();
        $hub2 = SentrySdk::getCurrentHub();

        $this->assertSame($hub1, $hub2);
        $this->assertNotSame(SentrySdk::init(), SentrySdk::init());
    }

    public function testGetCurrentHub(): void
    {
        SentrySdk::init();

        $hub2 = SentrySdk::getCurrentHub();
        $hub3 = SentrySdk::getCurrentHub();

        $this->assertSame($hub2, $hub3);
    }

    public function testSetCurrentHub(): void
    {
        $hub = new Hub();

        $this->assertSame($hub, SentrySdk::setCurrentHub($hub));
        $this->assertSame($hub, SentrySdk::getCurrentHub());
    }
}
