<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Tests;

use PHPUnit\Framework\TestCase;
use Raven\Client;
use Raven\ErrorHandler;

class ErrorHandlerTest extends TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|Client
     */
    protected $client;

    protected function setUp()
    {
        $this->client = $this->createMock(Client::class);
    }

    public function testConstructor()
    {
        try {
            $errorHandler = ErrorHandler::register($this->client);
            $previousErrorHandler = set_error_handler('var_dump');

            restore_error_handler();

            $this->assertSame([$errorHandler, 'handleError'], $previousErrorHandler);
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    /**
     * @dataProvider constructorThrowsWhenReservedMemorySizeIsWrongDataProvider
     *
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage The value of the $reservedMemorySize argument must be an integer greater than 0.
     */
    public function testConstructorThrowsWhenReservedMemorySizeIsWrong()
    {
        ErrorHandler::register($this->client, 0);
    }

    public function constructorThrowsWhenReservedMemorySizeIsWrongDataProvider()
    {
        return [
            [-1],
            [0],
            ['foo'],
        ];
    }

    /**
     * @dataProvider captureAtDataProvider
     */
    public function testCaptureAt($levels, $replace, $expectedCapturedErrors)
    {
        try {
            $errorHandler = ErrorHandler::register($this->client);
            $previousCapturedErrors = $this->getObjectAttribute($errorHandler, 'capturedErrors');

            $this->assertEquals($previousCapturedErrors, $errorHandler->captureAt($levels, $replace));
            $this->assertAttributeEquals($expectedCapturedErrors, 'capturedErrors', $errorHandler);
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function captureAtDataProvider()
    {
        return [
            [E_USER_NOTICE, false, E_ALL],
            [E_USER_NOTICE, true, E_USER_NOTICE],
        ];
    }

    public function testHandleError()
    {
        $this->client->expects($this->exactly(1))
            ->method('captureException')
            ->with($this->callback(function ($exception) {
                /* @var \ErrorException $exception */
                $this->assertInstanceOf(\ErrorException::class, $exception);
                $this->assertEquals(__FILE__, $exception->getFile());
                $this->assertEquals(123, $exception->getLine());
                $this->assertEquals(E_USER_NOTICE, $exception->getSeverity());
                $this->assertEquals('User Notice: foo bar', $exception->getMessage());

                $backtrace = $exception->getTrace();

                $this->assertGreaterThanOrEqual(2, $backtrace);

                $this->assertEquals('handleError', $backtrace[0]['function']);
                $this->assertEquals(ErrorHandler::class, $backtrace[0]['class']);
                $this->assertEquals('->', $backtrace[0]['type']);

                $this->assertEquals('testHandleError', $backtrace[1]['function']);
                $this->assertEquals(__CLASS__, $backtrace[1]['class']);
                $this->assertEquals('->', $backtrace[1]['type']);

                return true;
            }));

        try {
            $errorHandler = ErrorHandler::register($this->client);
            $errorHandler->captureAt(0, true);

            $reflectionProperty = new \ReflectionProperty(ErrorHandler::class, 'previousErrorHandler');
            $reflectionProperty->setAccessible(true);
            $reflectionProperty->setValue($errorHandler, null);
            $reflectionProperty->setAccessible(false);

            $this->assertFalse($errorHandler->handleError(0, 'foo bar', __FILE__, __LINE__));

            $errorHandler->captureAt(E_USER_NOTICE, true);

            $this->assertFalse($errorHandler->handleError(E_USER_WARNING, 'foo bar', __FILE__, __LINE__));
            $this->assertTrue($errorHandler->handleError(E_USER_NOTICE, 'foo bar', __FILE__, 123));
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function testHandleErrorWithPreviousErrorHandler()
    {
        $this->client->expects($this->once())
            ->method('captureException')
            ->with($this->callback(function ($exception) {
                /* @var \ErrorException $exception */
                $this->assertInstanceOf(\ErrorException::class, $exception);
                $this->assertEquals(__FILE__, $exception->getFile());
                $this->assertEquals(123, $exception->getLine());
                $this->assertEquals(E_USER_NOTICE, $exception->getSeverity());
                $this->assertEquals('User Notice: foo bar', $exception->getMessage());

                $backtrace = $exception->getTrace();

                $this->assertGreaterThanOrEqual(2, $backtrace);

                $this->assertEquals('handleError', $backtrace[0]['function']);
                $this->assertEquals(ErrorHandler::class, $backtrace[0]['class']);
                $this->assertEquals('->', $backtrace[0]['type']);

                $this->assertEquals('testHandleErrorWithPreviousErrorHandler', $backtrace[1]['function']);
                $this->assertEquals(__CLASS__, $backtrace[1]['class']);
                $this->assertEquals('->', $backtrace[1]['type']);

                return true;
            }));

        $previousErrorHandler = $this->createPartialMock(\stdClass::class, ['__invoke']);
        $previousErrorHandler->expects($this->once())
            ->method('__invoke')
            ->with(E_USER_NOTICE, 'foo bar', __FILE__, 123)
            ->willReturn(false);

        try {
            $errorHandler = ErrorHandler::register($this->client);

            $reflectionProperty = new \ReflectionProperty(ErrorHandler::class, 'previousErrorHandler');
            $reflectionProperty->setAccessible(true);
            $reflectionProperty->setValue($errorHandler, $previousErrorHandler);
            $reflectionProperty->setAccessible(false);

            $errorHandler->handleError(E_USER_NOTICE, 'foo bar', __FILE__, 123);
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function testHandleFatalError()
    {
        $this->client->expects($this->exactly(1))
            ->method('captureException')
            ->with($this->callback(function ($exception) {
                /* @var \ErrorException $exception */
                $this->assertInstanceOf(\ErrorException::class, $exception);
                $this->assertEquals(__FILE__, $exception->getFile());
                $this->assertEquals(123, $exception->getLine());
                $this->assertEquals(E_PARSE, $exception->getSeverity());
                $this->assertEquals('Parse Error: foo bar', $exception->getMessage());

                return true;
            }));

        try {
            $errorHandler = ErrorHandler::register($this->client);
            $errorHandler->handleFatalError([
                'type' => E_PARSE,
                'message' => 'foo bar',
                'file' => __FILE__,
                'line' => 123,
            ]);
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function testHandleFatalErrorWithNonFatalError()
    {
        $this->client->expects($this->never())
            ->method('captureException');

        try {
            $errorHandler = ErrorHandler::register($this->client);
            $errorHandler->handleFatalError([
                'type' => E_USER_NOTICE,
                'message' => 'foo bar',
                'file' => __FILE__,
                'line' => __LINE__,
            ]);
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function testHandleException()
    {
        $exception = new \Exception('foo bar');

        $this->client->expects($this->once())
            ->method('captureException')
            ->with($this->identicalTo($exception));

        try {
            $errorHandler = ErrorHandler::register($this->client);

            try {
                $errorHandler->handleException($exception);

                $this->fail('Exception expected');
            } catch (\Exception $catchedException) {
                $this->assertSame($exception, $catchedException);
            }
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function testHandleExceptionWithPreviousExceptionHandler()
    {
        $exception = new \Exception('foo bar');

        $this->client->expects($this->once())
            ->method('captureException')
            ->with($this->identicalTo($exception));

        $previousExceptionHandler = $this->createPartialMock(\stdClass::class, ['__invoke']);
        $previousExceptionHandler->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($exception));

        try {
            $errorHandler = ErrorHandler::register($this->client);

            $reflectionProperty = new \ReflectionProperty(ErrorHandler::class, 'previousExceptionHandler');
            $reflectionProperty->setAccessible(true);
            $reflectionProperty->setValue($errorHandler, $previousExceptionHandler);
            $reflectionProperty->setAccessible(false);

            try {
                $errorHandler->handleException($exception);

                $this->fail('Exception expected');
            } catch (\Exception $catchedException) {
                $this->assertSame($exception, $catchedException);
            }
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function testHandleExceptionWithThrowingPreviousExceptionHandler()
    {
        $exception1 = new \Exception('foo bar');
        $exception2 = new \Exception('bar foo');

        $this->client->expects($this->exactly(2))
            ->method('captureException')
            ->withConsecutive($this->identicalTo($exception1), $this->identicalTo($exception2));

        $previousExceptionHandler = $this->createPartialMock(\stdClass::class, ['__invoke']);
        $previousExceptionHandler->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($exception1))
            ->will($this->throwException($exception2));

        try {
            $errorHandler = ErrorHandler::register($this->client);

            $reflectionProperty = new \ReflectionProperty(ErrorHandler::class, 'previousExceptionHandler');
            $reflectionProperty->setAccessible(true);
            $reflectionProperty->setValue($errorHandler, $previousExceptionHandler);
            $reflectionProperty->setAccessible(false);

            try {
                $errorHandler->handleException($exception1);

                $this->fail('Exception expected');
            } catch (\Exception $catchedException) {
                $this->assertSame($exception2, $catchedException);
            }
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }
}
