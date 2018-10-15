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
use Sentry\AbstractErrorHandler;
use Sentry\Client;

abstract class AbstractErrorHandlerTest extends TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|Client
     */
    protected $client;

    protected function setUp()
    {
        $this->client = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['translateSeverity'])
            ->getMock();
    }

    public function testConstructor()
    {
        try {
            $errorHandler = $this->createErrorHandler($this->client);
            $previousErrorHandler = set_error_handler('var_dump');

            restore_error_handler();

            $this->assertSame([$errorHandler, 'handleError'], $previousErrorHandler);
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    /**
     * @dataProvider errorReportingConstantProvider
     */
    public function testHandleErrorShouldNotCapture(bool $expectedToCapture, int $captureAt, int $errorReporting)
    {
        if (!$expectedToCapture) {
            $this->client->expects($this->never())
                ->method('capture');
        }
        $errorHandler = $this->createErrorHandler($this->client);
        $errorHandler->captureAt($captureAt, true);

        try {
            $prevErrorReporting = error_reporting($errorReporting);
            $this->assertFalse($errorHandler->handleError(E_WARNING, 'Test', __FILE__, __LINE__));
        } finally {
            error_reporting($prevErrorReporting);
        }
    }

    public function errorReportingConstantProvider()
    {
        yield [false, E_ERROR, E_ERROR];
        yield [false, E_ALL, E_ERROR];
        yield [true, E_ERROR, E_ALL];
        yield [true, E_ALL, E_ALL];
    }

    /**
     * @dataProvider constructorThrowsWhenReservedMemorySizeIsWrongDataProvider
     *
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage The value of the $reservedMemorySize argument must be an integer greater than 0.
     */
    public function testConstructorThrowsWhenReservedMemorySizeIsWrong($reservedMemorySize)
    {
        $this->createErrorHandler($this->client, $reservedMemorySize);
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
            $errorHandler = $this->createErrorHandler($this->client);
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

    abstract protected function createErrorHandler(...$arguments): AbstractErrorHandler;
}
