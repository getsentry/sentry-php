<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Tests\Processor;

use PHPUnit\Framework\TestCase;
use Raven\Client;
use Raven\ClientBuilder;
use Raven\Event;
use Raven\Processor\SanitizeStacktraceProcessor;
use Raven\Stacktrace;

class SanitizeStacktraceProcessorTest extends TestCase
{
    /**
     * @var SanitizeStacktraceProcessor
     */
    protected $processor;

    /**
     * @var Client
     */
    protected $client;

    protected function setUp()
    {
        $this->processor = new SanitizeStacktraceProcessor();
        $this->client = ClientBuilder::create(['auto_log_stacks' => true])
            ->getClient();
    }

    public function testProcess()
    {
        $exception = new \Exception();

        $event = new Event($this->client->getConfig());
        $event = $event->withStacktrace(Stacktrace::createFromBacktrace($this->client, $exception->getTrace(), $exception->getFile(), $exception->getLine()));

        $event = $this->processor->process($event);

        foreach ($event->getStacktrace()->getFrames() as $frame) {
            $this->assertNull($frame->getPreContext());
            $this->assertNull($frame->getContextLine());
            $this->assertNull($frame->getPostContext());
        }
    }

    public function testProcessWithPreviousException()
    {
        $exception1 = new \Exception();
        $exception2 = new \Exception('', 0, $exception1);

        $event = new Event($this->client->getConfig());
        $event = $event->withStacktrace(Stacktrace::createFromBacktrace($this->client, $exception2->getTrace(), $exception2->getFile(), $exception2->getLine()));

        $event = $this->processor->process($event);

        foreach ($event->getStacktrace()->toArray() as $frame) {
            $this->assertNull($frame->getPreContext());
            $this->assertNull($frame->getContextLine());
            $this->assertNull($frame->getPostContext());
        }
    }

    public function testProcessWithNoStacktrace()
    {
        $event = new Event($this->client->getConfig());

        $this->assertSame($event, $this->processor->process($event));
    }
}
