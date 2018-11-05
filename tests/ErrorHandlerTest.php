<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry\Tests;

use Sentry\AbstractErrorHandler;
use Sentry\ErrorHandler;

class ErrorHandlerTest extends AbstractErrorHandlerTest
{
    public function testHandleError()
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
                $this->assertEquals(AbstractErrorHandler::class, $backtrace[0]['class']);
                $this->assertEquals('->', $backtrace[0]['type']);

                $this->assertEquals('testHandleError', $backtrace[1]['function']);
                $this->assertEquals(__CLASS__, $backtrace[1]['class']);
                $this->assertEquals('->', $backtrace[1]['type']);

                return true;
            }));

        try {
            $errorHandler = $this->createErrorHandler($this->callbackMock);
            $errorHandler->captureAt(0, true);

            $reflectionProperty = new \ReflectionProperty(AbstractErrorHandler::class, 'previousErrorHandler');
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

    public function testHandleErrorWithPreviousErrorHandler()
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
                $this->assertEquals(AbstractErrorHandler::class, $backtrace[0]['class']);
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
            $errorHandler = $this->createErrorHandler($this->callbackMock);

            $reflectionProperty = new \ReflectionProperty(AbstractErrorHandler::class, 'previousErrorHandler');
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
            $errorHandler = $this->createErrorHandler($this->callbackMock);
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

    public function testHandleFatalErrorWithNonFatalErrorDoesNothing()
    {
        $this->callbackMock->expects($this->never())
            ->method('__invoke');

        try {
            $errorHandler = $this->createErrorHandler($this->callbackMock);
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

        $this->callbackMock->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($exception));

        try {
            $errorHandler = $this->createErrorHandler($this->callbackMock);

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

        $this->callbackMock->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($exception));

        $previousExceptionHandler = $this->createPartialMock(\stdClass::class, ['__invoke']);
        $previousExceptionHandler->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($exception));

        try {
            $errorHandler = $this->createErrorHandler($this->callbackMock);

            $reflectionProperty = new \ReflectionProperty(AbstractErrorHandler::class, 'previousExceptionHandler');
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

        $this->callbackMock->expects($this->exactly(2))
            ->method('__invoke')
            ->withConsecutive($this->identicalTo($exception1), $this->identicalTo($exception2));

        $previousExceptionHandler = $this->createPartialMock(\stdClass::class, ['__invoke']);
        $previousExceptionHandler->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($exception1))
            ->will($this->throwException($exception2));

        try {
            $errorHandler = $this->createErrorHandler($this->callbackMock);

            $reflectionProperty = new \ReflectionProperty(AbstractErrorHandler::class, 'previousExceptionHandler');
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

    protected function createErrorHandler(...$arguments): ErrorHandler
    {
        return ErrorHandler::register(...$arguments);
    }

//    public function testHandleError()
//    {
//        $this->callbackMock->expects($this->once())
//            ->method('__invoke')
//            ->with($this->callback(function ($breadcrumb) {
//                /* @var Breadcrumb $breadcrumb */
//                $this->assertInstanceOf(Breadcrumb::class, $breadcrumb);
//                $this->assertEquals('User Notice: foo bar', $breadcrumb->getMessage());
//                $this->assertEquals(Breadcrumb::TYPE_ERROR, $breadcrumb->getType());
//                $this->assertEquals(Breadcrumb::LEVEL_INFO, $breadcrumb->getLevel());
//                $this->assertEquals('error_reporting', $breadcrumb->getCategory());
//                $this->assertArraySubset([
//                    'code' => 0,
//                    'file' => __FILE__,
//                    'line' => __LINE__ + 20,
//                ], $breadcrumb->getMetadata());
//
//                return true;
//            }));
//
//        try {
//            $errorHandler = $this->createErrorHandler($this->callbackMock);
//            $errorHandler->captureAt(0, true);
//
//            $reflectionProperty = new \ReflectionProperty(ErrorHandler::class, 'previousErrorHandler');
//            $reflectionProperty->setAccessible(true);
//            $reflectionProperty->setValue($errorHandler, null);
//            $reflectionProperty->setAccessible(false);
//
//            $this->assertFalse($errorHandler->handleError(0, 'foo bar', __FILE__, __LINE__));
//
//            $errorHandler->captureAt(E_USER_NOTICE, true);
//
//            $this->assertFalse($errorHandler->handleError(E_USER_WARNING, 'foo bar', __FILE__, __LINE__));
//            $this->assertFalse($errorHandler->handleError(E_USER_NOTICE, 'foo bar', __FILE__, __LINE__));
//        } finally {
//            restore_error_handler();
//            restore_exception_handler();
//        }
//    }
//
//    public function testHandleErrorWithPreviousErrorHandler()
//    {
//        $this->callbackMock->expects($this->once())
//            ->method('__invoke')
//            ->with($this->callback(function ($breadcrumb) {
//                /* @var Breadcrumb $breadcrumb */
//                $this->assertInstanceOf(Breadcrumb::class, $breadcrumb);
//                $this->assertEquals('User Notice: foo bar', $breadcrumb->getMessage());
//                $this->assertEquals(Breadcrumb::TYPE_ERROR, $breadcrumb->getType());
//                $this->assertEquals(Breadcrumb::LEVEL_INFO, $breadcrumb->getLevel());
//                $this->assertEquals('error_reporting', $breadcrumb->getCategory());
//                $this->assertArraySubset([
//                    'code' => 0,
//                    'file' => __FILE__,
//                    'line' => __LINE__ + 20,
//                ], $breadcrumb->getMetadata());
//
//                return true;
//            }));
//
//        $previousErrorHandler = $this->createPartialMock(\stdClass::class, ['__invoke']);
//        $previousErrorHandler->expects($this->once())
//            ->method('__invoke')
//            ->with(E_USER_NOTICE, 'foo bar', __FILE__, __LINE__ + 11)
//            ->willReturn(false);
//
//        try {
//            $errorHandler = $this->createErrorHandler($this->callbackMock);
//
//            $reflectionProperty = new \ReflectionProperty(ErrorHandler::class, 'previousErrorHandler');
//            $reflectionProperty->setAccessible(true);
//            $reflectionProperty->setValue($errorHandler, $previousErrorHandler);
//            $reflectionProperty->setAccessible(false);
//
//            $errorHandler->handleError(E_USER_NOTICE, 'foo bar', __FILE__, __LINE__);
//        } finally {
//            restore_error_handler();
//            restore_exception_handler();
//        }
//    }
//
//    public function testHandleFatalError()
//    {
//        $this->callbackMock->expects($this->once())
//            ->method('__invoke')
//            ->with($this->callback(function ($breadcrumb) {
//                /* @var Breadcrumb $breadcrumb */
//                $this->assertInstanceOf(Breadcrumb::class, $breadcrumb);
//                $this->assertEquals('Parse Error: foo bar', $breadcrumb->getMessage());
//                $this->assertEquals(Breadcrumb::TYPE_ERROR, $breadcrumb->getType());
//                $this->assertEquals(Breadcrumb::LEVEL_CRITICAL, $breadcrumb->getLevel());
//                $this->assertEquals('error_reporting', $breadcrumb->getCategory());
//                $this->assertArraySubset([
//                    'code' => 0,
//                    'file' => __FILE__,
//                    'line' => __LINE__ + 12,
//                ], $breadcrumb->getMetadata());
//
//                return true;
//            }));
//
//        try {
//            $errorHandler = $this->createErrorHandler($this->callbackMock);
//            $errorHandler->handleFatalError([
//                'type' => E_PARSE,
//                'message' => 'foo bar',
//                'file' => __FILE__,
//                'line' => __LINE__,
//            ]);
//        } finally {
//            restore_error_handler();
//            restore_exception_handler();
//        }
//    }
//
//    public function testHandleFatalErrorWithNonFatalErrorDoesNothing()
//    {
//        $this->callbackMock->expects($this->never())
//            ->method('__invoke');
//
//        try {
//            $errorHandler = $this->createErrorHandler($this->callbackMock);
//            $errorHandler->handleFatalError([
//                'type' => E_USER_NOTICE,
//                'message' => 'foo bar',
//                'file' => __FILE__,
//                'line' => __LINE__,
//            ]);
//        } finally {
//            restore_error_handler();
//            restore_exception_handler();
//        }
//    }
//
//    public function testHandleExceptionSkipsNotErrorExceptionException()
//    {
//        $exception = new \Exception('foo bar');
//
//        $this->callbackMock->expects($this->never())
//            ->method('__invoke');
//
//        try {
//            $errorHandler = $this->createErrorHandler($this->callbackMock);
//
//            try {
//                $errorHandler->handleException($exception);
//
//                $this->fail('Exception expected');
//            } catch (\Exception $catchedException) {
//                $this->assertSame($exception, $catchedException);
//            }
//        } finally {
//            restore_error_handler();
//            restore_exception_handler();
//        }
//    }
//
//    public function testHandleExceptionWithPreviousExceptionHandler()
//    {
//        $exception = new \ErrorException('foo bar', 0, E_USER_NOTICE);
//
//        $this->callbackMock->expects($this->once())
//            ->method('__invoke')
//            ->with($this->callback(function ($breadcrumb) {
//                /* @var Breadcrumb $breadcrumb */
//                $this->assertInstanceOf(Breadcrumb::class, $breadcrumb);
//                $this->assertEquals('foo bar', $breadcrumb->getMessage());
//                $this->assertEquals(Breadcrumb::TYPE_ERROR, $breadcrumb->getType());
//                $this->assertEquals(Breadcrumb::LEVEL_INFO, $breadcrumb->getLevel());
//                $this->assertEquals('error_reporting', $breadcrumb->getCategory());
//                $this->assertArraySubset([
//                    'code' => 0,
//                    'file' => __FILE__,
//                    'line' => __LINE__ - 14,
//                ], $breadcrumb->getMetadata());
//
//                return true;
//            }));
//
//        $previousExceptionHandler = $this->createPartialMock(\stdClass::class, ['__invoke']);
//        $previousExceptionHandler->expects($this->once())
//            ->method('__invoke')
//            ->with($this->identicalTo($exception));
//
//        try {
//            $errorHandler = $this->createErrorHandler($this->callbackMock);
//
//            $reflectionProperty = new \ReflectionProperty(ErrorHandler::class, 'previousExceptionHandler');
//            $reflectionProperty->setAccessible(true);
//            $reflectionProperty->setValue($errorHandler, $previousExceptionHandler);
//            $reflectionProperty->setAccessible(false);
//
//            try {
//                $errorHandler->handleException($exception);
//
//                $this->fail('Exception expected');
//            } catch (\Exception $catchedException) {
//                $this->assertSame($exception, $catchedException);
//            }
//        } finally {
//            restore_error_handler();
//            restore_exception_handler();
//        }
//    }
//
//    public function testHandleExceptionWithThrowingPreviousExceptionHandler()
//    {
//        $exception1 = new \ErrorException('foo bar', 0, E_USER_NOTICE);
//        $exception2 = new \ErrorException('bar foo', 0, E_USER_NOTICE);
//
//        $this->callbackMock->expects($this->exactly(2))
//            ->method('__invoke')
//            ->withConsecutive($this->callback(function ($breadcrumb) {
//                /* @var Breadcrumb $breadcrumb */
//                $this->assertInstanceOf(Breadcrumb::class, $breadcrumb);
//                $this->assertEquals('foo bar', $breadcrumb->getMessage());
//                $this->assertEquals(Breadcrumb::TYPE_ERROR, $breadcrumb->getType());
//                $this->assertEquals(Breadcrumb::LEVEL_INFO, $breadcrumb->getLevel());
//                $this->assertEquals('error_reporting', $breadcrumb->getCategory());
//                $this->assertArraySubset([
//                    'code' => 0,
//                    'file' => __FILE__,
//                    'line' => __LINE__ - 15,
//                ], $breadcrumb->getMetadata());
//
//                return true;
//            }), $this->callback(function ($breadcrumb) {
//                /* @var Breadcrumb $breadcrumb */
//                $this->assertInstanceOf(Breadcrumb::class, $breadcrumb);
//                $this->assertEquals('bar foo', $breadcrumb->getMessage());
//                $this->assertEquals(Breadcrumb::TYPE_ERROR, $breadcrumb->getType());
//                $this->assertEquals(Breadcrumb::LEVEL_INFO, $breadcrumb->getLevel());
//                $this->assertEquals('error_reporting', $breadcrumb->getCategory());
//                $this->assertArraySubset([
//                    'code' => 0,
//                    'file' => __FILE__,
//                    'line' => __LINE__ - 29,
//                ], $breadcrumb->getMetadata());
//
//                return true;
//            }));
//
//        $previousExceptionHandler = $this->createPartialMock(\stdClass::class, ['__invoke']);
//        $previousExceptionHandler->expects($this->once())
//            ->method('__invoke')
//            ->with($this->identicalTo($exception1))
//            ->will($this->throwException($exception2));
//
//        try {
//            $errorHandler = $this->createErrorHandler($this->callbackMock);
//
//            $reflectionProperty = new \ReflectionProperty(ErrorHandler::class, 'previousExceptionHandler');
//            $reflectionProperty->setAccessible(true);
//            $reflectionProperty->setValue($errorHandler, $previousExceptionHandler);
//            $reflectionProperty->setAccessible(false);
//
//            try {
//                $errorHandler->handleException($exception1);
//
//                $this->fail('Exception expected');
//            } catch (\Exception $catchedException) {
//                $this->assertSame($exception2, $catchedException);
//            }
//        } finally {
//            restore_error_handler();
//            restore_exception_handler();
//        }
//    }
//
//    public function testThrownErrorLeavesBreadcrumb()
//    {
//        $this->callbackMock->expects($this->once())
//            ->method('__invoke')
//            ->with($this->callback(function ($breadcrumb) {
//                /* @var Breadcrumb $breadcrumb */
//                $this->assertInstanceOf(Breadcrumb::class, $breadcrumb);
//                $this->assertEquals('User Warning: foo bar', $breadcrumb->getMessage());
//                $this->assertEquals(Breadcrumb::TYPE_ERROR, $breadcrumb->getType());
//                $this->assertEquals(Breadcrumb::LEVEL_WARNING, $breadcrumb->getLevel());
//                $this->assertEquals('error_reporting', $breadcrumb->getCategory());
//                $this->assertArraySubset([
//                    'code' => 0,
//                    'file' => __FILE__,
//                    'line' => __LINE__ + 9,
//                ], $breadcrumb->getMetadata());
//
//                return true;
//            }));
//
//        try {
//            $this->createErrorHandler($this->callbackMock);
//
//            @trigger_error('foo bar', E_USER_WARNING);
//        } finally {
//            restore_error_handler();
//            restore_exception_handler();
//        }
//    }
}
