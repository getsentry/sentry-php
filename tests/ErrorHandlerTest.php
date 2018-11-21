<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\ErrorHandler;

final class ErrorHandlerTest extends TestCase
{
    protected $callbackMock;

    protected function setUp(): void
    {
        $this->callbackMock = $this->createPartialMock(\stdClass::class, ['__invoke']);
    }

    public function testConstructor(): void
    {
        try {
            $errorHandler = ErrorHandler::register($this->callbackMock);
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
     * @expectedExceptionMessage The $reservedMemorySize argument must be greater than 0.
     */
    public function testConstructorThrowsWhenReservedMemorySizeIsWrong(int $reservedMemorySize): void
    {
        ErrorHandler::register($this->callbackMock, $reservedMemorySize);
    }

    public function constructorThrowsWhenReservedMemorySizeIsWrongDataProvider(): array
    {
        return [
            [-1],
            [0],
        ];
    }

    /**
     * @dataProvider handleErrorShouldNotCaptureDataProvider
     */
    public function testHandleErrorShouldNotCapture(bool $expectedToCapture, int $captureAt): void
    {
        if (!$expectedToCapture) {
            $this->callbackMock->expects($this->never())
                ->method('__invoke');
        }

        $errorHandler = ErrorHandler::register($this->callbackMock);
        $errorHandler->captureAt($captureAt, true);

        $prevErrorReporting = error_reporting(E_ERROR); // to avoid making the test error bubble up and make the test fail

        try {
            $this->assertFalse($errorHandler->handleError(E_WARNING, 'Test', __FILE__, __LINE__));
        } finally {
            error_reporting($prevErrorReporting);
            restore_error_handler();
            restore_exception_handler();
            $this->addToAssertionCount(1);
        }
    }

    public function handleErrorShouldNotCaptureDataProvider(): array
    {
        return [
            [false, E_ERROR],
            [true, E_ALL],
        ];
    }

    /**
     * @dataProvider captureAtDataProvider
     */
    public function testCaptureAt($levels, $replace, $expectedCapturedErrors): void
    {
        try {
            $errorHandler = ErrorHandler::register($this->callbackMock);
            $previousCapturedErrors = $this->getObjectAttribute($errorHandler, 'capturedErrors');

            $this->assertEquals($previousCapturedErrors, $errorHandler->captureAt($levels, $replace));
            $this->assertAttributeEquals($expectedCapturedErrors, 'capturedErrors', $errorHandler);
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function captureAtDataProvider(): array
    {
        return [
            [E_USER_NOTICE, false, E_ALL],
            [E_USER_NOTICE, true, E_USER_NOTICE],
        ];
    }

    public function testHandleError(): void
    {
        $this->callbackMock->expects($this->exactly(1))
            ->method('__invoke')
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
            $errorHandler = ErrorHandler::register($this->callbackMock);
            $errorHandler->captureAt(0, true);

            $reflectionProperty = new \ReflectionProperty(ErrorHandler::class, 'previousErrorHandler');
            $reflectionProperty->setAccessible(true);
            $reflectionProperty->setValue($errorHandler, null);
            $reflectionProperty->setAccessible(false);

            $this->assertFalse($errorHandler->handleError(0, 'foo bar', __FILE__, __LINE__));

            $errorHandler->captureAt(E_USER_NOTICE, true);

            $this->assertFalse($errorHandler->handleError(E_USER_WARNING, 'foo bar', __FILE__, __LINE__));
            $this->assertFalse($errorHandler->handleError(E_USER_NOTICE, 'foo bar', __FILE__, 123));
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    /**
     * @dataProvider testHandleErrorWithPreviousErrorHandlerDataProvider
     */
    public function testHandleErrorWithPreviousErrorHandler($previousErrorHandlerErrorReturnValue, bool $expectedHandleErrorReturnValue): void
    {
        $this->callbackMock->expects($this->once())
            ->method('__invoke')
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
            ->willReturn($previousErrorHandlerErrorReturnValue);

        try {
            $errorHandler = ErrorHandler::register($this->callbackMock);

            $reflectionProperty = new \ReflectionProperty(ErrorHandler::class, 'previousErrorHandler');
            $reflectionProperty->setAccessible(true);
            $reflectionProperty->setValue($errorHandler, $previousErrorHandler);
            $reflectionProperty->setAccessible(false);

            $this->assertEquals($expectedHandleErrorReturnValue, $errorHandler->handleError(E_USER_NOTICE, 'foo bar', __FILE__, 123));
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function testHandleErrorWithPreviousErrorHandlerDataProvider(): array
    {
        return [
            [false, false],
            [true, true],
            [0, true], // check that we're using strict comparison instead of shallow
            [1, true], // check that we're using strict comparison instead of shallow
            ['0', true], // check that we're using strict comparison instead of shallow
            ['1', true], // check that we're using strict comparison instead of shallow
        ];
    }

    public function testHandleFatalError(): void
    {
        $this->callbackMock->expects($this->exactly(1))
            ->method('__invoke')
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
            $errorHandler = ErrorHandler::register($this->callbackMock);
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

    public function testHandleFatalErrorWithNonFatalErrorDoesNothing(): void
    {
        $this->callbackMock->expects($this->never())
            ->method('__invoke');

        try {
            $errorHandler = ErrorHandler::register($this->callbackMock);
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

    public function testHandleException(): void
    {
        $exception = new \Exception('foo bar');

        $this->callbackMock->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($exception));

        try {
            $errorHandler = ErrorHandler::register($this->callbackMock);

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

    public function testHandleExceptionWithPreviousExceptionHandler(): void
    {
        $exception = new \Exception('foo bar');

        $this->callbackMock->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($exception));

        $previousExceptionHandler = $this->createPartialMock(\stdClass::class, ['__invoke']);
        $previousExceptionHandler->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($exception));

        try {
            $errorHandler = ErrorHandler::register($this->callbackMock);

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

    public function testHandleExceptionWithThrowingPreviousExceptionHandler(): void
    {
        $exception1 = new \Exception('foo bar');
        $exception2 = new \Exception('bar foo');

        $this->callbackMock->expects($this->exactly(2))
            ->method('__invoke')
            ->withConsecutive($this->identicalTo($exception1), $this->identicalTo($exception2));

        $previousExceptionHandler = $this->createPartialMock(\stdClass::class, ['__invoke']);
        $previousExceptionHandler->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($exception1))
            ->will($this->throwException($exception2));

        try {
            $errorHandler = ErrorHandler::register($this->callbackMock);

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
