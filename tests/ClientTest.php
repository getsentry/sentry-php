<?php

declare(strict_types=1);

namespace Sentry\Tests;

use Http\Mock\Client as MockClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumb;
use Sentry\Client;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\Options;
use Sentry\Serializer\RepresentationSerializerInterface;
use Sentry\Serializer\Serializer;
use Sentry\Serializer\SerializerInterface;
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

        /** @var TransportInterface|MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);

        $transport->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Event $event): bool {
                $this->assertEquals('/foo', $event->getTransaction());

                return true;
            }));

        $client = new Client(new Options(), $transport, $this->createMock(SerializerInterface::class), $this->createMock(RepresentationSerializerInterface::class));
        $client->captureMessage('test');

        unset($_SERVER['PATH_INFO']);
        unset($_SERVER['REQUEST_METHOD']);
    }

    public function testTransactionEventAttributeIsNotPopulatedInCli()
    {
        /** @var TransportInterface|MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);

        $transport->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Event $event): bool {
                $this->assertNull($event->getTransaction());

                return true;
            }));

        $client = new Client(new Options(), $transport, $this->createMock(SerializerInterface::class), $this->createMock(RepresentationSerializerInterface::class));
        $client->captureMessage('test');
    }

    public function testCaptureMessage()
    {
        /** @var Client|MockObject $client */
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

        /** @var Client|MockObject $client */
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
        /** @var TransportInterface|MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
            ->method('send')
            ->willReturn('500a339f3ab2450b96dee542adf36ba7');

        $client = ClientBuilder::create()
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
        /** @var TransportInterface|MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Event $event): bool {
                $exception = $event->getExceptions()[0];

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
        /** @var TransportInterface|MockObject $transport */
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

    public function testSendChecksBeforeSendOption()
    {
        $beforeSendCalled = false;

        /** @var TransportInterface|MockObject $transport */
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
        /** @var TransportInterface|MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($event) {
                /* @var Event $event*/
                // Make sure the exception is of the careless exception and not the exception thrown inside
                // the __set method of that exception caused by setting the event_id on the exception instance
                $this->assertSame(CarelessException::class, $event->getExceptions()[0]['type']);

                return true;
            }));

        $client = new Client(new Options(), $transport, $this->createMock(SerializerInterface::class), $this->createMock(RepresentationSerializerInterface::class), []);

        Hub::getCurrent()->bindClient($client);
        $client->captureException($this->createCarelessExceptionWithStacktrace(), Hub::getCurrent()->getScope());
    }

    /**
     * @dataProvider convertExceptionDataProvider
     */
    public function testConvertException(\Exception $exception, array $clientConfig, array $expectedResult)
    {
        /** @var TransportInterface|MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Event $event) use ($expectedResult): bool {
                $this->assertArraySubset($expectedResult, $event->toArray());
                $this->assertArrayNotHasKey('values', $event->getExceptions());
                $this->assertArrayHasKey('values', $event->toArray()['exception']);

                foreach ($event->getExceptions() as $exceptionData) {
                    $this->assertArrayHasKey('stacktrace', $exceptionData);
                    $this->assertInstanceOf(Stacktrace::class, $exceptionData['stacktrace']);
                }

                return true;
            }));

        $client = ClientBuilder::create($clientConfig)
            ->setTransport($transport)
            ->getClient();

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

    public function testConvertExceptionThrownInLatin1File()
    {
        /** @var TransportInterface|MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Event $event): bool {
                $result = $event->getExceptions();
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

        $serializer = new Serializer();
        $serializer->setMbDetectOrder('ISO-8859-1, ASCII, UTF-8');

        $client = ClientBuilder::create()
            ->setTransport($transport)
            ->setSerializer($serializer)
            ->getClient();

        $client->captureException(require_once __DIR__ . '/Fixtures/code/Latin1File.php');
    }

    public function testAttachStacktrace()
    {
        /** @var TransportInterface|MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Event $event): bool {
                $result = $event->getStacktrace();

                $this->assertNotNull($result);
                $this->assertNotEmpty($result->getFrames());

                return true;
            }));

        $client = ClientBuilder::create(['attach_stacktrace' => true])
            ->setTransport($transport)
            ->getClient();

        $client->captureMessage('test');
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
