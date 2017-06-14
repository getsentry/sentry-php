<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Tests\HttpClient\Stream;

use Http\Message\StreamFactory;
use Raven\HttpClient\Stream\DecoratingStreamFactory;
use Zend\Diactoros\Stream;

class DecoratingStreamFactoryTest extends \PHPUnit_Framework_TestCase
{
    public function testCreateStream()
    {
        $decoratedStream = new Stream('php://memory', 'r+');
        $decoratedStream->write('foo');
        $decoratedStream->rewind();

        /** @var StreamFactory|\PHPUnit_Framework_MockObject_MockObject $decoratedStreamFactory */
        $decoratedStreamFactory = $this->getMockBuilder(StreamFactory::class)
            ->getMock();

        $decoratedStreamFactory->expects($this->once())
            ->method('createStream')
            ->with($decoratedStream)
            ->willReturn($decoratedStream);

        $streamFactory = new DecoratingStreamFactory($decoratedStreamFactory);
        $stream = $streamFactory->createStream($decoratedStream);

        $this->assertEquals('eJxLy88HAAKCAUU=', $stream->getContents());
    }
}
