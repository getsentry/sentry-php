<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Tests\Middleware;

use PHPUnit\Framework\TestCase;
use Raven\ClientBuilder;
use Raven\Event;
use Raven\Middleware\ProcessorMiddleware;
use Raven\Processor\ProcessorInterface;

class ProcessorMiddlewareTest extends TestCase
{
    public function testInvoke()
    {
        $client = ClientBuilder::create()->getClient();
        $event = new Event($client->getConfig());
        $processorRegistry = $this->getObjectAttribute($client, 'processorRegistry');

        /** @var ProcessorInterface|\PHPUnit_Framework_MockObject_MockObject $processor */
        $processor = $this->createMock(ProcessorInterface::class);
        $processor->expects($this->once())
            ->method('process')
            ->willReturnArgument(0);

        $client->addProcessor($processor);

        $middleware = new ProcessorMiddleware($processorRegistry);
        $middleware($event, function () {
            // Do nothing, it's just a middleware added to end the chain
        });
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage The processor must return an instance of the "Raven\Event" class.
     */
    public function testInvokeProcessorThatReturnsNothingThrows()
    {
        $client = ClientBuilder::create()->getClient();
        $event = new Event($client->getConfig());
        $processorRegistry = $this->getObjectAttribute($client, 'processorRegistry');

        /** @var ProcessorInterface|\PHPUnit_Framework_MockObject_MockObject $processor */
        $processor = $this->createMock(ProcessorInterface::class);
        $processor->expects($this->once())
            ->method('process');

        $client->addProcessor($processor);

        $middleware = new ProcessorMiddleware($processorRegistry);
        $middleware($event, function () {
            // Do nothing, it's just a middleware added to end the chain
        });
    }
}
