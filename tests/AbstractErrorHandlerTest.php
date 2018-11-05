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

use PHPUnit\Framework\TestCase;

abstract class AbstractErrorHandlerTest extends TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $callbackMock;

    protected function setUp()
    {
        $this->callbackMock = $this->createPartialMock(\stdClass::class, ['__invoke']);
    }

    public function testConstructor()
    {
        try {
            $errorHandler = $this->createErrorHandler($this->callbackMock);
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
    public function testConstructorThrowsWhenReservedMemorySizeIsWrong($reservedMemorySize)
    {
        $this->createErrorHandler($this->callbackMock, $reservedMemorySize);
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
     * @dataProvider handleErrorShouldNotCaptureDataProvider
     */
    public function testHandleErrorShouldNotCapture(bool $expectedToCapture, int $captureAt, int $errorReporting)
    {
        if (!$expectedToCapture) {
            $this->callbackMock->expects($this->never())
                ->method('__invoke');
        }

        $errorHandler = $this->createErrorHandler($this->callbackMock);
        $errorHandler->captureAt($captureAt, true);

        $prevErrorReporting = error_reporting($errorReporting);

        try {
            $this->assertFalse($errorHandler->handleError(E_WARNING, 'Test', __FILE__, __LINE__));
        } finally {
            error_reporting($prevErrorReporting);
        }
    }

    public function handleErrorShouldNotCaptureDataProvider()
    {
        return [
            [false, E_ERROR, E_ERROR],
//            [false, E_ALL, E_ERROR], TODO fails
            [true, E_ERROR, E_ALL],
            [true, E_ALL, E_ALL],
        ];
    }

    /**
     * @dataProvider captureAtDataProvider
     */
    public function testCaptureAt($levels, $replace, $expectedCapturedErrors)
    {
        try {
            $errorHandler = $this->createErrorHandler($this->callbackMock);
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

    abstract protected function createErrorHandler(...$arguments);
}
