<?php

/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sentry\Tests\Breadcrumbs;

use Sentry\BreadcrumbErrorHandler;
use Sentry\Breadcrumbs\Breadcrumb;
use Sentry\Client;
use Sentry\Tests\AbstractErrorHandlerTest;

class BreadcrumbErrorHandlerTest extends AbstractErrorHandlerTest
{
    public function testHandleError()
    {
        $this->client->expects($this->once())
            ->method('leaveBreadcrumb')
            ->with($this->callback(function ($breadcrumb) {
                /* @var Breadcrumb $breadcrumb */
                $this->assertInstanceOf(Breadcrumb::class, $breadcrumb);
                $this->assertEquals('User Notice: foo bar', $breadcrumb->getMessage());
                $this->assertEquals(Breadcrumb::TYPE_ERROR, $breadcrumb->getType());
                $this->assertEquals(Client::LEVEL_INFO, $breadcrumb->getLevel());
                $this->assertEquals('error_reporting', $breadcrumb->getCategory());
                $this->assertArraySubset([
                    'code' => 0,
                    'file' => __FILE__,
                    'line' => __LINE__ + 20,
                ], $breadcrumb->getMetadata());

                return true;
            }));

        try {
            $errorHandler = $this->createErrorHandler($this->client);
            $errorHandler->captureAt(0, true);

            $reflectionProperty = new \ReflectionProperty(BreadcrumbErrorHandler::class, 'previousErrorHandler');
            $reflectionProperty->setAccessible(true);
            $reflectionProperty->setValue($errorHandler, null);
            $reflectionProperty->setAccessible(false);

            $this->assertFalse($errorHandler->handleError(0, 'foo bar', __FILE__, __LINE__));

            $errorHandler->captureAt(E_USER_NOTICE, true);

            $this->assertFalse($errorHandler->handleError(E_USER_WARNING, 'foo bar', __FILE__, __LINE__));
            $this->assertTrue($errorHandler->handleError(E_USER_NOTICE, 'foo bar', __FILE__, __LINE__));
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function testHandleErrorWithPreviousErrorHandler()
    {
        $this->client->expects($this->once())
            ->method('leaveBreadcrumb')
            ->with($this->callback(function ($breadcrumb) {
                /* @var Breadcrumb $breadcrumb */
                $this->assertInstanceOf(Breadcrumb::class, $breadcrumb);
                $this->assertEquals('User Notice: foo bar', $breadcrumb->getMessage());
                $this->assertEquals(Breadcrumb::TYPE_ERROR, $breadcrumb->getType());
                $this->assertEquals(Client::LEVEL_INFO, $breadcrumb->getLevel());
                $this->assertEquals('error_reporting', $breadcrumb->getCategory());
                $this->assertArraySubset([
                    'code' => 0,
                    'file' => __FILE__,
                    'line' => __LINE__ + 20,
                ], $breadcrumb->getMetadata());

                return true;
            }));

        $previousErrorHandler = $this->createPartialMock(\stdClass::class, ['__invoke']);
        $previousErrorHandler->expects($this->once())
            ->method('__invoke')
            ->with(E_USER_NOTICE, 'foo bar', __FILE__, __LINE__ + 11)
            ->willReturn(false);

        try {
            $errorHandler = $this->createErrorHandler($this->client);

            $reflectionProperty = new \ReflectionProperty(BreadcrumbErrorHandler::class, 'previousErrorHandler');
            $reflectionProperty->setAccessible(true);
            $reflectionProperty->setValue($errorHandler, $previousErrorHandler);
            $reflectionProperty->setAccessible(false);

            $errorHandler->handleError(E_USER_NOTICE, 'foo bar', __FILE__, __LINE__);
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function testHandleFatalError()
    {
        $this->client->expects($this->once())
            ->method('leaveBreadcrumb')
            ->with($this->callback(function ($breadcrumb) {
                /* @var Breadcrumb $breadcrumb */
                $this->assertInstanceOf(Breadcrumb::class, $breadcrumb);
                $this->assertEquals('Parse Error: foo bar', $breadcrumb->getMessage());
                $this->assertEquals(Breadcrumb::TYPE_ERROR, $breadcrumb->getType());
                $this->assertEquals(Client::LEVEL_FATAL, $breadcrumb->getLevel());
                $this->assertEquals('error_reporting', $breadcrumb->getCategory());
                $this->assertArraySubset([
                    'code' => 0,
                    'file' => __FILE__,
                    'line' => __LINE__ + 12,
                ], $breadcrumb->getMetadata());

                return true;
            }));

        try {
            $errorHandler = $this->createErrorHandler($this->client);
            $errorHandler->handleFatalError([
                'type' => E_PARSE,
                'message' => 'foo bar',
                'file' => __FILE__,
                'line' => __LINE__,
            ]);
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    public function testHandleFatalErrorWithNonFatalErrorDoesNothing()
    {
        $this->client->expects($this->never())
            ->method('leaveBreadcrumb');

        try {
            $errorHandler = $this->createErrorHandler($this->client);
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

    public function testHandleExceptionSkipsNotErrorExceptionException()
    {
        $exception = new \Exception('foo bar');

        $this->client->expects($this->never())
            ->method('leaveBreadcrumb');

        try {
            $errorHandler = $this->createErrorHandler($this->client);

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
        $exception = new \ErrorException('foo bar', 0, E_USER_NOTICE);

        $this->client->expects($this->once())
            ->method('leaveBreadcrumb')
            ->with($this->callback(function ($breadcrumb) {
                /* @var Breadcrumb $breadcrumb */
                $this->assertInstanceOf(Breadcrumb::class, $breadcrumb);
                $this->assertEquals('foo bar', $breadcrumb->getMessage());
                $this->assertEquals(Breadcrumb::TYPE_ERROR, $breadcrumb->getType());
                $this->assertEquals(Client::LEVEL_INFO, $breadcrumb->getLevel());
                $this->assertEquals('error_reporting', $breadcrumb->getCategory());
                $this->assertArraySubset([
                    'code' => 0,
                    'file' => __FILE__,
                    'line' => __LINE__ - 14,
                ], $breadcrumb->getMetadata());

                return true;
            }));

        $previousExceptionHandler = $this->createPartialMock(\stdClass::class, ['__invoke']);
        $previousExceptionHandler->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($exception));

        try {
            $errorHandler = $this->createErrorHandler($this->client);

            $reflectionProperty = new \ReflectionProperty(BreadcrumbErrorHandler::class, 'previousExceptionHandler');
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
        $exception1 = new \ErrorException('foo bar', 0, E_USER_NOTICE);
        $exception2 = new \ErrorException('bar foo', 0, E_USER_NOTICE);

        $this->client->expects($this->exactly(2))
            ->method('leaveBreadcrumb')
            ->withConsecutive($this->callback(function ($breadcrumb) {
                /* @var Breadcrumb $breadcrumb */
                $this->assertInstanceOf(Breadcrumb::class, $breadcrumb);
                $this->assertEquals('foo bar', $breadcrumb->getMessage());
                $this->assertEquals(Breadcrumb::TYPE_ERROR, $breadcrumb->getType());
                $this->assertEquals(Client::LEVEL_INFO, $breadcrumb->getLevel());
                $this->assertEquals('error_reporting', $breadcrumb->getCategory());
                $this->assertArraySubset([
                    'code' => 0,
                    'file' => __FILE__,
                    'line' => __LINE__ - 15,
                ], $breadcrumb->getMetadata());

                return true;
            }), $this->callback(function ($breadcrumb) {
                /* @var Breadcrumb $breadcrumb */
                $this->assertInstanceOf(Breadcrumb::class, $breadcrumb);
                $this->assertEquals('bar foo', $breadcrumb->getMessage());
                $this->assertEquals(Breadcrumb::TYPE_ERROR, $breadcrumb->getType());
                $this->assertEquals(Client::LEVEL_INFO, $breadcrumb->getLevel());
                $this->assertEquals('error_reporting', $breadcrumb->getCategory());
                $this->assertArraySubset([
                    'code' => 0,
                    'file' => __FILE__,
                    'line' => __LINE__ - 29,
                ], $breadcrumb->getMetadata());

                return true;
            }));

        $previousExceptionHandler = $this->createPartialMock(\stdClass::class, ['__invoke']);
        $previousExceptionHandler->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($exception1))
            ->will($this->throwException($exception2));

        try {
            $errorHandler = $this->createErrorHandler($this->client);

            $reflectionProperty = new \ReflectionProperty(BreadcrumbErrorHandler::class, 'previousExceptionHandler');
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

    public function testThrownErrorLeavesBreadcrumb()
    {
        $this->client->expects($this->once())
            ->method('leaveBreadcrumb')
            ->with($this->callback(function ($breadcrumb) {
                /* @var Breadcrumb $breadcrumb */
                $this->assertInstanceOf(Breadcrumb::class, $breadcrumb);
                $this->assertEquals('User Warning: foo bar', $breadcrumb->getMessage());
                $this->assertEquals(Breadcrumb::TYPE_ERROR, $breadcrumb->getType());
                $this->assertEquals(Client::LEVEL_WARNING, $breadcrumb->getLevel());
                $this->assertEquals('error_reporting', $breadcrumb->getCategory());
                $this->assertArraySubset([
                    'code' => 0,
                    'file' => __FILE__,
                    'line' => __LINE__ + 9,
                ], $breadcrumb->getMetadata());

                return true;
            }));

        try {
            $this->createErrorHandler($this->client);

            @trigger_error('foo bar', E_USER_WARNING);
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    protected function createErrorHandler(...$arguments)
    {
        return BreadcrumbErrorHandler::register(...$arguments);
    }
}
