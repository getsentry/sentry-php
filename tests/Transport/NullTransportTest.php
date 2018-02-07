<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Raven\Configuration;
use Raven\Event;
use Raven\Transport\NullTransport;

class NullTransportTest extends TestCase
{
    public function testSend()
    {
        $transport = new NullTransport();
        $event = new Event(new Configuration());

        $this->assertTrue($transport->send($event));
    }
}
