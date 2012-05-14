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
    public function testErrorsAreLoggedAsExceptions()
    {
        $client = $this->getMock('Client', array('captureException', 'getIdent'));
        $client->expects($this->once())
               ->method('captureException')
               ->with($this->isInstanceOf('ErrorException'));

        $handler = new Raven_ErrorHandler($client);
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
}
