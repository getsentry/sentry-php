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

use Http\Mock\Client as MockClient;
use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumbs\Breadcrumb;
use Sentry\Client;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\Options;
use Sentry\Severity;
use Sentry\Stacktrace;
use Sentry\State\Hub;
use Sentry\State\Scope;
use Sentry\Tests\Fixtures\classes\CarelessException;
use Sentry\Transport\TransportInterface;

class ClientTest extends TestCase
{
    public function testTransactionEventAttributeIsPopulated()
    {
        $_SERVER['PATH_INFO'] = '/foo';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        /** @var TransportInterface|\PHPUnit_Framework_MockObject_MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);

        $transport->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Event $event) {
                $this->assertInstanceOf(Event::class, $event);
                $this->assertEquals('/foo', $event->getTransaction());

                return true;
            }));

        $client = new Client(new Options(), $transport);
        $client->captureMessage('test');

        unset($_SERVER['PATH_INFO']);
        unset($_SERVER['REQUEST_METHOD']);
    }

    public function testTransactionEventAttributeIsNotPopulatedInCli()
    {
        /** @var TransportInterface|\PHPUnit_Framework_MockObject_MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);

        $transport->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Event $event) {
                $this->assertInstanceOf(Event::class, $event);
                $this->assertNull($event->getTransaction());

                return true;
            }));

        $client = new Client(new Options(), $transport);
        $client->captureMessage('test');
    }

    public function testCaptureMessage()
    {
        /** @var Client|\PHPUnit_Framework_MockObject_MockObject $client */
        $client = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['captureMessage'])
            ->getMock();

        $client->expects($this->once())
            ->method('captureEvent')
            ->with([
                'message' => 'foo',
                'level' => Severity::fatal(),
            ]);

        $client->captureMessage('foo', Severity::fatal());
    }

    public function testCaptureException()
    {
        $exception = new \Exception();

        /** @var Client|\PHPUnit_Framework_MockObject_MockObject $client */
        $client = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['captureException'])
            ->getMock();

        $client->expects($this->once())
            ->method('captureEvent')
            ->with([
                'exception' => $exception,
            ]);

        $client->captureException(new \Exception());
    }

    public function testCapture()
    {
        /** @var TransportInterface|\PHPUnit_Framework_MockObject_MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
            ->method('send')
            ->willReturn('500a339f3ab2450b96dee542adf36ba7');

        $client = ClientBuilder::create(['dsn' => 'http://public:secret@example.com/1'])
            ->setTransport($transport)
            ->getClient();

        $inputData = [
            'transaction' => 'foo bar',
            'level' => Severity::debug(),
            'logger' => 'foo',
            'tags_context' => ['foo', 'bar'],
            'extra_context' => ['foo' => 'bar'],
            'user_context' => ['bar' => 'foo'],
        ];

        $this->assertEquals('500a339f3ab2450b96dee542adf36ba7', $client->captureEvent($inputData));
    }

    public function testCaptureLastError()
    {
        /** @var TransportInterface|\PHPUnit_Framework_MockObject_MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);

        $transport->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Event $event) {
                $exception = $event->getException()[0];

                $this->assertEquals('ErrorException', $exception['type']);
                $this->assertEquals('foo', $exception['value']);

                return true;
            }));

        $client = ClientBuilder::create(['dsn' => 'http://public:secret@example.com/1'])
            ->setTransport($transport)
            ->getClient();

        @trigger_error('foo', E_USER_NOTICE);

        $client->captureLastError();

        $this->clearLastError();
    }

    public function testCaptureLastErrorDoesNothingWhenThereIsNoError()
    {
        /** @var TransportInterface|\PHPUnit_Framework_MockObject_MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);

        $transport->expects($this->never())
            ->method('send');

        $client = ClientBuilder::create(['dsn' => 'http://public:secret@example.com/1'])
            ->setTransport($transport)
            ->getClient();

        $this->clearLastError();

        $client->captureLastError();
    }

    public function testAppPathLinux()
    {
        $client = ClientBuilder::create(['project_root' => '/foo/bar'])->getClient();

        $this->assertEquals('/foo/bar/', $client->getOptions()->getProjectRoot());

        $client->getOptions()->setProjectRoot('/foo/baz/');

        $this->assertEquals('/foo/baz/', $client->getOptions()->getProjectRoot());
    }

    public function testAppPathWindows()
    {
        $client = ClientBuilder::create(['project_root' => 'C:\\foo\\bar\\'])->getClient();

        $this->assertEquals('C:\\foo\\bar\\', $client->getOptions()->getProjectRoot());
    }

    private function assertMixedValueAndArray($expected_value, $actual_value)
    {
        if (null === $expected_value) {
            $this->assertNull($actual_value);
        } elseif (true === $expected_value) {
            $this->assertTrue($actual_value);
        } elseif (false === $expected_value) {
            $this->assertFalse($actual_value);
        } elseif (\is_string($expected_value) || \is_numeric($expected_value)) {
            $this->assertEquals($expected_value, $actual_value);
        } elseif (\is_array($expected_value)) {
            $this->assertInternalType('array', $actual_value);
            $this->assertEquals(\count($expected_value), \count($actual_value));
            foreach ($expected_value as $key => $value) {
                $this->assertArrayHasKey($key, $actual_value);
                $this->assertMixedValueAndArray($value, $actual_value[$key]);
            }
        } elseif (\is_callable($expected_value)) {
            $this->assertEquals($expected_value, $actual_value);
        } elseif (\is_object($expected_value)) {
            $this->assertEquals(spl_object_hash($expected_value), spl_object_hash($actual_value));
        }
    }

    public function testSendChecksBeforeSendOption()
    {
        $beforeSendCalled = false;

        /** @var TransportInterface|\PHPUnit_Framework_MockObject_MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);

        $transport->expects($this->never())
            ->method('send');

        $client = ClientBuilder::create([
            'dsn' => 'http://public:secret@example.com/1',
            'before_send' => function () use (&$beforeSendCalled) {
                $beforeSendCalled = true;

                return null;
            },
        ])->setTransport($transport)->getClient();

        $client->captureEvent([]);

        $this->assertTrue($beforeSendCalled);
    }

    /**
     * @dataProvider sampleRateAbsoluteDataProvider
     */
    public function testSampleRateAbsolute($options)
    {
        $httpClient = new MockClient();

        $client = ClientBuilder::create($options)
            ->setHttpClient($httpClient)
            ->getClient();

        for ($i = 0; $i < 10; ++$i) {
            $client->captureMessage('foobar');
        }

        switch ($options['sample_rate']) {
            case 0:
                $this->assertEmpty($httpClient->getRequests());
                break;
            case 1:
                $this->assertNotEmpty($httpClient->getRequests());
                break;
        }
    }

    public function sampleRateAbsoluteDataProvider()
    {
        return [
            [
                [
                    'dsn' => 'http://public:secret@example.com/1',
                    'sample_rate' => 0,
                ],
            ],
            [
                [
                    'dsn' => 'http://public:secret@example.com/1',
                    'sample_rate' => 1,
                ],
            ],
        ];
    }

    /**
     * @dataProvider addBreadcrumbDoesNothingIfMaxBreadcrumbsLimitIsTooLowDataProvider
     */
    public function testAddBreadcrumbDoesNothingIfMaxBreadcrumbsLimitIsTooLow(int $maxBreadcrumbs): void
    {
        $client = ClientBuilder::create(['max_breadcrumbs' => $maxBreadcrumbs])->getClient();
        $scope = new Scope();

        $client->addBreadcrumb(new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting'), $scope);

        $this->assertEmpty($scope->getBreadcrumbs());
    }

    public function addBreadcrumbDoesNothingIfMaxBreadcrumbsLimitIsTooLowDataProvider(): array
    {
        return [
            [0],
            [-1],
        ];
    }

    public function testAddBreadcrumbRespectsMaxBreadcrumbsLimit(): void
    {
        $client = ClientBuilder::create(['max_breadcrumbs' => 2])->getClient();
        $scope = new Scope();

        $breadcrumb1 = new Breadcrumb(Breadcrumb::LEVEL_WARNING, Breadcrumb::TYPE_ERROR, 'error_reporting', 'foo');
        $breadcrumb2 = new Breadcrumb(Breadcrumb::LEVEL_WARNING, Breadcrumb::TYPE_ERROR, 'error_reporting', 'bar');
        $breadcrumb3 = new Breadcrumb(Breadcrumb::LEVEL_WARNING, Breadcrumb::TYPE_ERROR, 'error_reporting', 'baz');

        $client->addBreadcrumb($breadcrumb1, $scope);
        $client->addBreadcrumb($breadcrumb2, $scope);

        $this->assertSame([$breadcrumb1, $breadcrumb2], $scope->getBreadcrumbs());

        $client->addBreadcrumb($breadcrumb3, $scope);

        $this->assertSame([$breadcrumb2, $breadcrumb3], $scope->getBreadcrumbs());
    }

    public function testAddBreadcrumbDoesNothingWhenBeforeBreadcrumbCallbackReturnsNull(): void
    {
        $scope = new Scope();
        $client = ClientBuilder::create(
            [
                'before_breadcrumb' => function (): ?Breadcrumb {
                    return null;
                },
            ]
        )->getClient();

        $client->addBreadcrumb(new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting'), $scope);

        $this->assertEmpty($scope->getBreadcrumbs());
    }

    public function testAddBreadcrumbStoresBreadcrumbReturnedByBeforeBreadcrumbCallback(): void
    {
        $breadcrumb1 = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');
        $breadcrumb2 = new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'error_reporting');

        $scope = new Scope();
        $client = ClientBuilder::create(
            [
                'before_breadcrumb' => function () use ($breadcrumb2): ?Breadcrumb {
                    return $breadcrumb2;
                },
            ]
        )->getClient();

        $client->addBreadcrumb($breadcrumb1, $scope);

        $this->assertSame([$breadcrumb2], $scope->getBreadcrumbs());
    }

    public function testHandlingExceptionThrowingAnException()
    {
        /** @var TransportInterface|\PHPUnit_Framework_MockObject_MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);

        $client = new Client(new Options(), $transport, []);

        Hub::getCurrent()->bindClient($client);

        $transport->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($event) {
                /* @var Event $event*/
                // Make sure the exception is of the careless exception and not the exception thrown inside
                // the __set method of that exception caused by setting the event_id on the exception instance
                $this->assertSame(CarelessException::class, $event->getException()[0]['type']);

                return true;
            }));

        $client->captureException($this->createCarelessExceptionWithStacktrace(), Hub::getCurrent()->getScope());
    }

    /**
     * @dataProvider convertExceptionDataProvider
     */
    public function testConvertException(\Exception $exception, array $clientConfig, array $expectedResult)
    {
        $options = new Options($clientConfig);

        $assertHasStacktrace = $options->getAutoLogStacks();

        /** @var TransportInterface|\PHPUnit_Framework_MockObject_MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);

        $transport->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Event $event) use ($expectedResult, $assertHasStacktrace) {
                $this->assertNotNull($event);
                $this->assertArraySubset($expectedResult, $event->toArray());
                $this->assertArrayNotHasKey('values', $event->getException());
                $this->assertArrayHasKey('values', $event->toArray()['exception']);

                foreach ($event->getException() as $exceptionData) {
                    if ($assertHasStacktrace) {
                        $this->assertArrayHasKey('stacktrace', $exceptionData);
                        $this->assertInstanceOf(Stacktrace::class, $exceptionData['stacktrace']);
                    } else {
                        $this->assertArrayNotHasKey('stacktrace', $exceptionData);
                    }
                }

                return true;
            }));

        $client = new Client($options, $transport, []);
        $client->captureException($exception);
    }

    public function convertExceptionDataProvider()
    {
        return [
            [
                new \RuntimeException('foo'),
                [],
                [
                    'level' => Severity::ERROR,
                    'exception' => [
                        'values' => [
                            [
                                'type' => \RuntimeException::class,
                                'value' => 'foo',
                            ],
                        ],
                    ],
                ],
            ],
            [
                new \RuntimeException('foo'),
                [
                    'auto_log_stacks' => false,
                ],
                [
                    'level' => Severity::ERROR,
                    'exception' => [
                        'values' => [
                            [
                                'type' => \RuntimeException::class,
                                'value' => 'foo',
                            ],
                        ],
                    ],
                ],
            ],
            [
                new \ErrorException('foo', 0, E_USER_WARNING),
                [],
                [
                    'level' => Severity::WARNING,
                    'exception' => [
                        'values' => [
                            [
                                'type' => \ErrorException::class,
                                'value' => 'foo',
                            ],
                        ],
                    ],
                ],
            ],
            [
                new \BadMethodCallException('baz', 0, new \BadFunctionCallException('bar', 0, new \LogicException('foo', 0))),
                [
                    'excluded_exceptions' => [\BadMethodCallException::class],
                ],
                [
                    'level' => Severity::ERROR,
                    'exception' => [
                        'values' => [
                            [
                                'type' => \LogicException::class,
                                'value' => 'foo',
                            ],
                            [
                                'type' => \BadFunctionCallException::class,
                                'value' => 'bar',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function testConvertExceptionContainingLatin1Characters()
    {
        $options = new Options(['mb_detect_order' => ['ISO-8859-1', 'ASCII', 'UTF-8']]);

        $utf8String = 'äöü';
        $latin1String = utf8_decode($utf8String);

        /** @var TransportInterface|\PHPUnit_Framework_MockObject_MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);

        $transport->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Event $event) use ($utf8String) {
                $this->assertNotNull($event);

                $expectedValue = [
                    [
                        'type' => \Exception::class,
                        'value' => $utf8String,
                    ],
                ];

                $this->assertArraySubset($expectedValue, $event->getException());

                return true;
            }));

        $client = new Client($options, $transport, []);
        $client->captureException(new \Exception($latin1String));
    }

    public function testConvertExceptionContainingInvalidUtf8Characters()
    {
        $malformedString = "\xC2\xA2\xC2"; // ill-formed 2-byte character U+00A2 (CENT SIGN)

        /** @var TransportInterface|\PHPUnit_Framework_MockObject_MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);

        $transport->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Event $event) {
                $this->assertNotNull($event);

                $expectedValue = [
                    [
                        'type' => \Exception::class,
                        'value' => "\xC2\xA2\x3F",
                    ],
                ];

                $this->assertArraySubset($expectedValue, $event->getException());

                return true;
            }));

        $client = new Client(new Options(), $transport, []);
        $client->captureException(new \Exception($malformedString));
    }

    public function testConvertExceptionThrownInLatin1File()
    {
        $options = new Options([
            'auto_log_stacks' => true,
            'mb_detect_order' => ['ISO-8859-1', 'ASCII', 'UTF-8'],
        ]);

        /** @var TransportInterface|\PHPUnit_Framework_MockObject_MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);

        $transport->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Event $event) {
                $this->assertNotNull($event);

                $result = $event->getException();
                $expectedValue = [
                    [
                        'type' => \Exception::class,
                        'value' => 'foo',
                    ],
                ];

                $this->assertArraySubset($expectedValue, $result);

                $latin1StringFound = false;

                /** @var \Sentry\Frame $frame */
                foreach ($result[0]['stacktrace']->getFrames() as $frame) {
                    if (null !== $frame->getPreContext() && \in_array('// äöü', $frame->getPreContext(), true)) {
                        $latin1StringFound = true;

                        break;
                    }
                }

                $this->assertTrue($latin1StringFound);

                return true;
            }));

        $client = new Client($options, $transport, []);
        $client->captureException(require_once __DIR__ . '/Fixtures/code/Latin1File.php');
    }

    public function testConvertExceptionWithAutoLogStacksDisabled()
    {
        $options = new Options(['auto_log_stacks' => false]);

        /** @var TransportInterface|\PHPUnit_Framework_MockObject_MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);

        $transport->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Event $event) {
                $this->assertNotNull($event);

                $result = $event->getException();

                $this->assertNotEmpty($result);
                $this->assertInternalType('array', $result[0]);
                $this->assertEquals(\Exception::class, $result[0]['type']);
                $this->assertEquals('foo', $result[0]['value']);
                $this->assertArrayNotHasKey('stacktrace', $result[0]);

                return true;
            }));

        $client = new Client($options, $transport, []);
        $client->captureException(new \Exception('foo'));
    }

    /**
     * @see https://github.com/symfony/polyfill/blob/52332f49d18c413699d2dccf465234356f8e0b2c/src/Php70/Php70.php#L52-L61
     */
    private function clearLastError()
    {
        $handler = function () {
            return false;
        };

        set_error_handler($handler);
        @trigger_error('');
        restore_error_handler();
    }

    private function createCarelessExceptionWithStacktrace()
    {
        try {
            throw new CarelessException('Foo bar');
        } catch (\Exception $ex) {
            return $ex;
        }
    }
}
