<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\ErrorHandler;
use Sentry\ExceptionListenerInterface;
use Sentry\Tests\Fixtures\classes\StubErrorListener;

final class ErrorHandlerTest extends TestCase
{
    public function testGetCurrent(): void
    {
        $errorHandler = ErrorHandler::getInstance();

        $this->assertSame($errorHandler, ErrorHandler::getInstance());
    }

    /**
     * @dataProvider constructorThrowsWhenReservedMemorySizeIsWrongDataProvider
     *
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage The $reservedMemorySize argument must be greater than 0.
     */
    public function testConstructorThrowsWhenReservedMemorySizeIsWrong(int $reservedMemorySize): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('The $reservedMemorySize argument must be greater than 0.');

        ErrorHandler::getInstance($reservedMemorySize);
    }

    public function constructorThrowsWhenReservedMemorySizeIsWrongDataProvider(): array
    {
        return [
            [-1],
            [0],
        ];
    }

    public function testHandleError(): void
    {
        $listener = new StubErrorListener();
        $errorLine = null;

        try {
            ErrorHandler::addErrorListener($listener);
            $errorHandler = ErrorHandler::getInstance();

            $reflectionProperty = new \ReflectionProperty(ErrorHandler::class, 'previousErrorHandler');
            $reflectionProperty->setAccessible(true);
            $reflectionProperty->setValue($errorHandler, null);
            $reflectionProperty->setAccessible(false);

            $errorLine = __LINE__ + 1;
            $this->assertFalse($errorHandler->handleError(E_USER_NOTICE, 'foo bar', __FILE__, __LINE__));
        } finally {
            restore_error_handler();
            restore_exception_handler();

            $exception = $listener->getError();

            $this->assertInstanceOf(\ErrorException::class, $exception);
            $this->assertEquals(__FILE__, $exception->getFile());
            $this->assertEquals($errorLine, $exception->getLine());
            $this->assertEquals('User Notice: foo bar', $exception->getMessage());
            $this->assertEquals(E_USER_NOTICE, $exception->getSeverity());

            $backtrace = $exception->getTrace();

            $this->assertGreaterThanOrEqual(2, $backtrace);

            $this->assertEquals('testHandleError', $backtrace[0]['function']);
            $this->assertEquals(self::class, $backtrace[0]['class']);
            $this->assertEquals('->', $backtrace[0]['type']);
        }
    }

    /**
     * @dataProvider handleErrorWithPreviousErrorHandlerDataProvider
     */
    public function testHandleErrorWithPreviousErrorHandler($previousErrorHandlerErrorReturnValue, bool $expectedHandleErrorReturnValue): void
    {
        $previousErrorHandler = $this->createPartialMock(\stdClass::class, ['__invoke']);
        $previousErrorHandler->expects($this->once())
            ->method('__invoke')
            ->with(E_USER_NOTICE, 'foo bar', __FILE__, 123)
            ->willReturn($previousErrorHandlerErrorReturnValue);

        $listener = new StubErrorListener();

        try {
            ErrorHandler::addErrorListener($listener);
            $errorHandler = ErrorHandler::getInstance();

            $reflectionProperty = new \ReflectionProperty(ErrorHandler::class, 'previousErrorHandler');
            $reflectionProperty->setAccessible(true);
            $reflectionProperty->setValue($errorHandler, $previousErrorHandler);
            $reflectionProperty->setAccessible(false);

            $this->assertEquals($expectedHandleErrorReturnValue, $errorHandler->handleError(E_USER_NOTICE, 'foo bar', __FILE__, 123));
        } finally {
            restore_error_handler();
            restore_exception_handler();

            $exception = $listener->getError();

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
            $this->assertEquals(self::class, $backtrace[1]['class']);
            $this->assertEquals('->', $backtrace[1]['type']);
        }
    }

    public function handleErrorWithPreviousErrorHandlerDataProvider(): array
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
        $listener = new StubErrorListener();

        try {
            ErrorHandler::addErrorListener($listener);
            $errorHandler = ErrorHandler::getInstance();

            $errorHandler->handleFatalError([
                'type' => E_PARSE,
                'message' => 'foo bar',
                'file' => __FILE__,
                'line' => 123,
            ]);
        } finally {
            restore_error_handler();
            restore_exception_handler();

            $exception = $listener->getError();

            $this->assertInstanceOf(\ErrorException::class, $exception);
            $this->assertEquals(__FILE__, $exception->getFile());
            $this->assertEquals(123, $exception->getLine());
            $this->assertEquals(E_PARSE, $exception->getSeverity());
            $this->assertEquals('Parse Error: foo bar', $exception->getMessage());
        }
    }

    public function testHandleFatalErrorWithNonFatalErrorDoesNothing(): void
    {
        $listener = new StubErrorListener();

        try {
            ErrorHandler::addErrorListener($listener);
            $errorHandler = ErrorHandler::getInstance();

            $errorHandler->handleFatalError([
                'type' => E_USER_NOTICE,
                'message' => 'foo bar',
                'file' => __FILE__,
                'line' => __LINE__,
            ]);
        } finally {
            restore_error_handler();
            restore_exception_handler();

            $this->assertNull($listener->getError());
        }
    }

    public function testHandleException(): void
    {
        $exception = new \Exception('foo bar');
        $listener = $this->createMock(ExceptionListenerInterface::class);
        $listener->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($exception));

        try {
            ErrorHandler::addExceptionListener($listener);

            $errorHandler = ErrorHandler::getInstance();

            try {
                $errorHandler->handleException($exception);

                $this->fail('Exception expected');
            } catch (\Exception $caughtException) {
                $this->assertSame($exception, $caughtException);
            }
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function testHandleExceptionWithPreviousExceptionHandler(): void
    {
        $exception = new \Exception('foo bar');

        $listener = $this->createMock(ExceptionListenerInterface::class);
        $listener->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($exception));

        $previousExceptionHandler = $this->createPartialMock(\stdClass::class, ['__invoke']);
        $previousExceptionHandler->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($exception));

        try {
            ErrorHandler::addExceptionListener($listener);

            $errorHandler = ErrorHandler::getInstance();

            $reflectionProperty = new \ReflectionProperty(ErrorHandler::class, 'previousExceptionHandler');
            $reflectionProperty->setAccessible(true);
            $reflectionProperty->setValue($errorHandler, $previousExceptionHandler);
            $reflectionProperty->setAccessible(false);

            try {
                $errorHandler->handleException($exception);

                $this->fail('Exception expected');
            } catch (\Exception $caughtException) {
                $this->assertSame($exception, $caughtException);
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
        $listener = $this->createMock(ExceptionListenerInterface::class);

        $listener->expects($this->exactly(2))
            ->method('__invoke')
            ->withConsecutive($this->identicalTo($exception1), $this->identicalTo($exception2));

        $previousExceptionHandler = $this->createPartialMock(\stdClass::class, ['__invoke']);
        $previousExceptionHandler->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($exception1))
            ->will($this->throwException($exception2));

        try {
            ErrorHandler::addExceptionListener($listener);

            $errorHandler = ErrorHandler::getInstance();

            $reflectionProperty = new \ReflectionProperty(ErrorHandler::class, 'previousExceptionHandler');
            $reflectionProperty->setAccessible(true);
            $reflectionProperty->setValue($errorHandler, $previousExceptionHandler);
            $reflectionProperty->setAccessible(false);

            try {
                $errorHandler->handleException($exception1);

                $this->fail('Exception expected');
            } catch (\Exception $caughtException) {
                $this->assertSame($exception2, $caughtException);
            }
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }
}
