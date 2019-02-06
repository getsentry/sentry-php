<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\ErrorHandler;
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
     */
    public function testConstructorThrowsWhenReservedMemorySizeIsWrong(int $reservedMemorySize): void
    {
        $this->expectException(\InvalidArgumentException::class);
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
        $listenerCalled = false;
        $exception = new \Exception('foo bar');
        $listener = function (\Throwable $throwable) use ($exception, &$listenerCalled): void {
            $listenerCalled = true;
            $this->assertSame($exception, $throwable);
        };

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

            $this->assertTrue($listenerCalled, 'Listener was not called');
        }
    }

    public function testHandleExceptionWithPreviousExceptionHandler(): void
    {
        $listenerCalled = false;
        $exception = new \Exception('foo bar');

        $listener = function (\Throwable $throwable) use ($exception, &$listenerCalled): void {
            $listenerCalled = true;
            $this->assertSame($exception, $throwable);
        };

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

            $this->assertTrue($listenerCalled, 'Listener was not called');
        }
    }

    public function testHandleExceptionWithThrowingPreviousExceptionHandler(): void
    {
        $listenerCalled = 0;
        $exception1 = new \Exception('foo bar');
        $exception2 = new \Exception('bar foo');
        $captured1 = $captured2 = null;

        $listener = function (\Throwable $throwable) use (&$captured1, &$captured2, &$listenerCalled): void {
            if (0 === $listenerCalled) {
                $captured1 = $throwable;
            } elseif (1 === $listenerCalled) {
                $captured2 = $throwable;
            }

            ++$listenerCalled;
        };

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

            $this->assertSame(2, $listenerCalled);
            $this->assertSame($exception1, $captured1);
            $this->assertSame($exception2, $captured2);
        }
    }

    /**
     * @dataProvider callableProvider
     */
    public function testAddListener(callable $listener): void
    {
        $exception = new \Exception();

        try {
            ErrorHandler::addExceptionListener($listener);

            ErrorHandler::getInstance()->handleException($exception);
        } catch (\Throwable $rethrownException) {
            $this->assertSame($exception, $rethrownException);
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function callableProvider(): array
    {
        $stubErrorListener = new StubErrorListener();

        return [
            [[$stubErrorListener, '__invoke']],
            [[new ExtendedStubListener(), 'parent::someCallable']],
            [\Closure::fromCallable([$stubErrorListener, '__invoke'])],
            [$stubErrorListener],
            [function (\Throwable $throwable): void {}],
        ];
    }
}

final class ExtendedStubListener extends StubErrorListener
{
    public function someCallable(\ErrorException $error): void
    {
        trigger_error('Stop everything!');
    }
}
