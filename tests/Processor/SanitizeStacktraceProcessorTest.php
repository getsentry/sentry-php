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
use Raven\Processor\SanitizeStacktraceProcessor;

class SanitizeStacktraceProcessorTest extends TestCase
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var SanitizeStacktraceProcessor
     */
    protected $processor;

    protected function setUp()
    {
        $this->client = ClientBuilder::create()->getClient();
        $this->client->store_errors_for_bulk_send = true;

        $this->processor = new SanitizeStacktraceProcessor($this->client);
    }

    public function testProcess()
    {
        try {
            throw new \Exception();
        } catch (\Exception $exception) {
            $this->client->captureException($exception);
        }

        $this->processor->process($this->client->_pending_events[0]);

        foreach ($this->client->_pending_events[0]['exception']['values'] as $exceptionValue) {
            foreach ($exceptionValue['stacktrace']['frames'] as $frame) {
                $this->assertArrayNotHasKey('pre_context', $frame);
                $this->assertArrayNotHasKey('context_line', $frame);
                $this->assertArrayNotHasKey('post_context', $frame);
            }
        }
    }

    public function testProcessWithPreviousException()
    {
        try {
            try {
                throw new \Exception('foo');
            } catch (\Exception $exception) {
                throw new \Exception('bar', 0, $exception);
            }
        } catch (\Exception $exception) {
            $this->client->captureException($exception);
        }

        $this->processor->process($this->client->_pending_events[0]);

        foreach ($this->client->_pending_events[0]['exception']['values'] as $exceptionValue) {
            foreach ($exceptionValue['stacktrace']['frames'] as $frame) {
                $this->assertArrayNotHasKey('pre_context', $frame);
                $this->assertArrayNotHasKey('context_line', $frame);
                $this->assertArrayNotHasKey('post_context', $frame);
            }
        }
    }
}
