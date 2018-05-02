<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class Dummy_CarelessSetException extends Exception
{
    public function __set($var, $value)
    {
        if ($var === 'event_id') {
            throw new Exception('I am carelessly throwing an exception here!');
        }
    }
}

class Raven_Tests_ErrorHandlerTest extends \PHPUnit\Framework\TestCase
{
    private $errorLevel;
    private $errorHandlerCalled;
    private $existingErrorHandler;

    public function setUp()
    {
        $this->errorLevel = error_reporting();
        $this->errorHandlerCalled = false;
        $this->existingErrorHandler = set_error_handler(array($this, 'errorHandler'), -1);
        // improves the reliability of tests
        if (function_exists('error_clear_last')) {
            error_clear_last();
        }
    }

    public function errorHandler()
    {
        $this->errorHandlerCalled = true;
    }

    public function tearDown()
    {
        restore_exception_handler();
        set_error_handler($this->existingErrorHandler);
        // // XXX(dcramer): this isn't great as it doesnt restore the old error reporting level
        // set_error_handler(array($this, 'errorHandler'), error_reporting());
        error_reporting($this->errorLevel);
    }

    public function testErrorsAreLoggedAsExceptions()
    {
        $client = $this->getMockBuilder('Client')
                       ->setMethods(array('captureException', 'sendUnsentErrors'))
                       ->getMock();
        $client->expects($this->once())
               ->method('captureException')
               ->with($this->isInstanceOf('ErrorException'));

        $handler = new Raven_ErrorHandler($client, E_ALL);
        $handler->handleError(E_WARNING, 'message');
    }

    public function testExceptionsAreLogged()
    {
        $client = $this->getMockBuilder('Client')
                       ->setMethods(array('captureException'))
                       ->getMock();
        $client->expects($this->once())
               ->method('captureException')
               ->with($this->isInstanceOf('ErrorException'));

        $e = new ErrorException('message', 0, E_WARNING, '', 0);

        $handler = new Raven_ErrorHandler($client);
        $handler->handleException($e);
    }

    public function testErrorHandlerPassErrorReportingPass()
    {
        $client = $this->getMockBuilder('Client')
                       ->setMethods(array('captureException'))
                       ->getMock();
        $client->expects($this->once())
               ->method('captureException');

        $handler = new Raven_ErrorHandler($client);
        $handler->registerErrorHandler(false, -1);

        error_reporting(E_USER_WARNING);
        trigger_error('Warning', E_USER_WARNING);
    }

    public function testErrorHandlerPropagates()
    {
        $client = $this->getMockBuilder('Client')
                       ->setMethods(array('captureException'))
                       ->getMock();
        $client->expects($this->never())
               ->method('captureException');

        $handler = new Raven_ErrorHandler($client);
        $handler->registerErrorHandler(true, E_DEPRECATED);

        error_reporting(E_USER_WARNING);
        trigger_error('Warning', E_USER_WARNING);

        $this->assertEquals($this->errorHandlerCalled, 1);
    }

    public function testExceptionHandlerPropagatesToNative()
    {
        $client = $this->getMockBuilder('Client')
                       ->setMethods(array('captureException'))
                       ->getMock();
        $client->expects($this->exactly(2))
               ->method('captureException')
               ->with($this->isInstanceOf('Exception'));

        $handler = new Raven_ErrorHandler($client);

        set_exception_handler(null);
        $handler->registerExceptionHandler(false);

        $testException = new Exception('Test exception');

        $didRethrow = false;
        try {
            $handler->handleException($testException);
        } catch (Exception $e) {
            $didRethrow = true;
        }

        $this->assertFalse($didRethrow);

        set_exception_handler(null);
        $handler->registerExceptionHandler(true);

        $didRethrow = false;
        $rethrownException = null;
        try {
            $handler->handleException($testException);
        } catch (Exception $e) {
            $didRethrow = true;
            $rethrownException = $e;
        }

        $this->assertTrue($didRethrow);
        $this->assertSame($testException, $rethrownException);
    }

    public function testErrorHandlerRespectsErrorReportingDefault()
    {
        $client = $this->getMockBuilder('Client')
                       ->setMethods(array('captureException'))
                       ->getMock();
        $client->expects($this->once())
               ->method('captureException');

        error_reporting(E_DEPRECATED);

        $handler = new Raven_ErrorHandler($client);
        $handler->registerErrorHandler(true);

        error_reporting(E_ALL);
        trigger_error('Warning', E_USER_WARNING);

        $this->assertEquals($this->errorHandlerCalled, 1);
    }

    // Because we cannot **know** that a user silenced an error, we always
    // defer to respecting the error reporting settings.
    public function testSilentErrorsAreNotReportedWithGlobal()
    {
        $client = $this->getMockBuilder('Client')
                       ->setMethods(array('captureException'))
                       ->getMock();
        $client->expects($this->never())
               ->method('captureException');

        error_reporting(E_ALL);

        $handler = new Raven_ErrorHandler($client);
        $handler->registerErrorHandler(true);

        @$undefined;

        // also ensure it doesnt get reported by the fatal handler
        $handler->handleFatalError();
    }

    // Because we cannot **know** that a user silenced an error, we always
    // defer to respecting the error reporting settings.
    public function testSilentErrorsAreNotReportedWithLocal()
    {
        $client = $this->getMockBuilder('Client')
                       ->setMethods(array('captureException'))
                       ->getMock();
        $client->expects($this->never())
               ->method('captureException');

        $handler = new Raven_ErrorHandler($client);
        $handler->registerErrorHandler(true, E_ALL);

        @$my_array[2];

        // also ensure it doesnt get reported by the fatal handler
        $handler->handleFatalError();
    }

    public function testShouldCaptureFatalErrorBehavior()
    {
        $client = $this->getMockBuilder('Client')
                       ->setMethods(array('captureException'))
                       ->getMock();
        $handler = new Raven_ErrorHandler($client);

        $this->assertEquals($handler->shouldCaptureFatalError(E_WARNING), false);
    }

    public function testErrorHandlerDefaultsErrorReporting()
    {
        $client = $this->getMockBuilder('Client')
                       ->setMethods(array('captureException'))
                       ->getMock();
        $client->expects($this->never())
               ->method('captureException');

        error_reporting(E_USER_ERROR);

        $handler = new Raven_ErrorHandler($client);
        $handler->registerErrorHandler(false);

        trigger_error('Warning', E_USER_WARNING);
    }

    public function testFluidInterface()
    {
        $client = $this->getMockBuilder('Client')
                       ->setMethods(array('captureException'))
                       ->getMock();
        $handler = new Raven_ErrorHandler($client);
        $result = $handler->registerErrorHandler();
        $this->assertEquals($result, $handler);
        $result = $handler->registerExceptionHandler();
        $this->assertEquals($result, $handler);
        // TODO(dcramer): cant find a great way to test resetting the shutdown
        // handler
        // $result = $handler->registerShutdownHandler();
        // $this->assertEquals($result, $handler);
    }

    public function testHandlingExceptionThrowingAnException()
    {
        $client = new Dummy_Raven_Client();
        $handler = new Raven_ErrorHandler($client);
        $handler->handleException($this->create_careless_exception());
        $events = $client->getSentEvents();
        $this->assertCount(1, $events);
        $event = array_pop($events);

        // Make sure the exception is of the careless exception and not the exception thrown inside
        // the __set method of that exception caused by setting the event_id on the exception instance
        $this->assertEquals('Dummy_CarelessSetException', $event['exception']['values'][0]['type']);
    }

    private function create_careless_exception()
    {
        try {
            throw new Dummy_CarelessSetException('Foo bar');
        } catch (Exception $ex) {
            return $ex;
        }
    }
}
