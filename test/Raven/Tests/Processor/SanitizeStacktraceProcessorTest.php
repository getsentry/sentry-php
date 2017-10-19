<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class Raven_Tests_SanitizeStacktraceProcessorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Raven_Client|PHPUnit_Framework_MockObject_MockObject
     */
    protected $client;

    /**
     * @var Raven_Processor_SanitizeStacktraceProcessor
     */
    protected $processor;

    protected function setUp()
    {
        $this->client = $this->getMockBuilder('Raven_Client')
            ->setMethods(array_diff($this->getClassMethods('Raven_Client'), array('captureException', 'capture', 'get_default_data', 'get_http_data', 'get_user_data', 'get_extra_data')))
            ->getMock();

        $this->client->store_errors_for_bulk_send = true;

        $this->processor = new Raven_Processor_SanitizeStacktraceProcessor($this->client);
    }

    public function testProcess()
    {
        try {
            throw new \Exception();
        } catch (\Exception $exception) {
            $this->client->captureException($exception);
        }

        foreach ($this->client->_pending_events[0]['exception']['values'] as $exceptionValue) {
            foreach ($exceptionValue['stacktrace']['frames'] as $frame) {
                $this->assertArrayHasKey('pre_context', $frame);
                $this->assertArrayHasKey('context_line', $frame);
                $this->assertArrayHasKey('post_context', $frame);
            }
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

        foreach ($this->client->_pending_events[0]['exception']['values'] as $exceptionValue) {
            foreach ($exceptionValue['stacktrace']['frames'] as $frame) {
                $this->assertArrayHasKey('pre_context', $frame);
                $this->assertArrayHasKey('context_line', $frame);
                $this->assertArrayHasKey('post_context', $frame);
            }
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

    /**
     * Gets all the public and abstracts methods of a given class.
     *
     * @param string $className The FCQN of the class
     *
     * @return array
     */
    private function getClassMethods($className)
    {
        $class = new ReflectionClass($className);
        $methods = array();

        foreach ($class->getMethods() as $method) {
            if ($method->isPublic() || $method->isAbstract()) {
                $methods[] = $method->getName();
            }
        }

        return $methods;
    }
}
