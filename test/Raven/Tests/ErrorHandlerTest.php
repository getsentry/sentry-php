<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class Raven_Tests_ErrorHandlerTest extends PHPUnit_Framework_TestCase
{
    private $errorLevel;

    public function setUp()
    {
        $this->errorLevel = error_reporting();
        $this->errorHandlerCalled = false;
        $this->existingErrorHandler = set_error_handler(array($this, 'errorHandler'), -1);
    }

    public function errorHandler()
    {
        $this->errorHandlerCalled = true;
    }

    public function tearDown()
    {
        // XXX(dcramer): this isn't great as it doesnt restore the old error reporting level
        set_error_handler(array($this, 'errorHandler'), error_reporting());
        error_reporting($this->errorLevel);
    }

    public function testErrorsAreLoggedAsExceptions()
    {
        $client = $this->getMock('Client', array('captureException', 'getIdent', 'sendUnsentErrors'));
        $client->expects($this->once())
               ->method('captureException')
               ->with($this->isInstanceOf('ErrorException'));

        $handler = new Raven_ErrorHandler($client, E_ALL);
        $handler->handleError(E_WARNING, 'message');
    }

    public function testExceptionsAreLogged()
    {
        $client = $this->getMock('Client', array('captureException', 'getIdent'));
        $client->expects($this->once())
               ->method('captureException')
               ->with($this->isInstanceOf('ErrorException'));

        $e = new ErrorException('message', 0, E_WARNING, '', 0);

        $handler = new Raven_ErrorHandler($client);
        $handler->handleException($e);
    }

    public function testErrorHandlerPassErrorReportingPass()
    {
        $client = $this->getMock('Client', array('captureException', 'getIdent'));
        $client->expects($this->once())
               ->method('captureException');

        $handler = new Raven_ErrorHandler($client);
        $handler->registerErrorHandler(false, -1);

        error_reporting(E_USER_WARNING);
        trigger_error('Warning', E_USER_WARNING);
    }

    public function testErrorHandlerPropagates()
    {
        $client = $this->getMock('Client', array('captureException', 'getIdent'));
        $client->expects($this->never())
               ->method('captureException');

        $handler = new Raven_ErrorHandler($client);
        $handler->registerErrorHandler(true, E_DEPRECATED);

        error_reporting(E_USER_WARNING);
        trigger_error('Warning', E_USER_WARNING);

        $this->assertEquals($this->errorHandlerCalled, 1);
    }

    public function testErrorHandlerRespectsErrorReportingDefault()
    {
        $client = $this->getMock('Client', array('captureException', 'getIdent'));
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
    public function testSilentErrorsAreReported()
    {
        $client = $this->getMock('Client', array('captureException', 'getIdent'));
        $client->expects($this->never())
               ->method('captureException');

        error_reporting(E_USER_WARNING);

        $handler = new Raven_ErrorHandler($client);
        $handler->registerErrorHandler(false);

        @trigger_error('Silent', E_USER_WARNING);
    }

    public function testErrorHandlerDefaultsErrorReporting()
    {
        $client = $this->getMock('Client', array('captureException', 'getIdent'));
        $client->expects($this->never())
               ->method('captureException');

        error_reporting(E_USER_ERROR);

        $handler = new Raven_ErrorHandler($client);
        $handler->registerErrorHandler(false);

        trigger_error('Warning', E_USER_WARNING);
    }
    public function testHandleFatalError()
    {
        $client = $this->getMock('Client', array('captureException'));
        $client->expects($this->once())
               ->method('captureException');

        $handler = new Raven_ErrorHandler($client);

        # http://php.net/manual/en/function.error-get-last.php#113518
        set_error_handler('var_dump', 0);
        @$undef_var;
        restore_error_handler();

        $handler->handleFatalError();
    }

    public function testHandleFatalErrorDuplicate()
    {
        $client = $this->getMock('Client', array('captureException'));
        $client->expects($this->once())
               ->method('captureException');

        $handler = new Raven_ErrorHandler($client);

        # http://php.net/manual/en/function.error-get-last.php#113518
        set_error_handler('var_dump', 0);
        @$undef_var;
        restore_error_handler();

        $error = error_get_last();

        $handler->handleError($error['type'], $error['message'], $error['file'], $error['line']);
        $handler->handleFatalError();
    }

    public function testFluidInterface()
    {
        $client = $this->getMock('Client', array('captureException', 'getIdent'));
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
}
