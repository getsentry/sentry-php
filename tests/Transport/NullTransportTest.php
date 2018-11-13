<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Sentry\Event;
use Sentry\Transport\NullTransport;

class NullTransportTest extends TestCase
{
    public function testSend()
    {
        $transport = new NullTransport();
        $event = new Event();

        $this->assertEquals($event->getId(), $transport->send($event));
    }
}
