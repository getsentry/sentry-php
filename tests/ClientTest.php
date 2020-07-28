<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\MockObject\Matcher\Invocation;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\Severity;
use Sentry\Stacktrace;
use Sentry\State\Scope;
use Sentry\Transport\TransportFactoryInterface;
use Sentry\Transport\TransportInterface;

class ClientTest extends TestCase
{
    public function testCaptureMessage(): void
    {
        /** @var TransportInterface&MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Event $event): bool {
                $this->assertSame('foo', $event->getMessage());
                $this->assertEquals(Severity::fatal(), $event->getLevel());

                return true;
            }));

        $client = ClientBuilder::create()
            ->setTransportFactory($this->createTransportFactory($transport))
            ->getClient();

        $client->captureMessage('foo', Severity::fatal());
    }

    public function testCaptureException(): void
    {
        $exception = new \Exception('Some foo error');

        /** @var TransportInterface&MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Event $event) use ($exception): bool {
                $this->assertCount(1, $event->getExceptions());

                $exceptionData = $event->getExceptions()[0];

                $this->assertSame(\get_class($exception), $exceptionData['type']);
                $this->assertSame($exception->getMessage(), $exceptionData['value']);

                return true;
            }));

        $client = ClientBuilder::create()
            ->setTransportFactory($this->createTransportFactory($transport))
            ->getClient();

        $client->captureException($exception);
    }

    /**
     * @dataProvider captureExceptionDoesNothingIfExcludedExceptionsOptionMatchesDataProvider
     */
    public function testCaptureExceptionDoesNothingIfExcludedExceptionsOptionMatches(bool $shouldCapture, string $excluded, \Throwable $thrownException): void
    {
        /** @var TransportInterface&MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transportFactory = $this->createTransportFactory($transport);

        $transport->expects($shouldCapture ? $this->once() : $this->never())
            ->method('send')
            ->with($this->callback(function (Event $event): bool {
                $this->assertNotEmpty($event->getExceptions());

                return true;
            }));

        $client = ClientBuilder::create(['excluded_exceptions' => [$excluded]])
            ->setTransportFactory($transportFactory)
            ->getClient();

        SentrySdk::getCurrentHub()->bindClient($client);
        SentrySdk::getCurrentHub()->captureException($thrownException);
    }

    public function captureExceptionDoesNothingIfExcludedExceptionsOptionMatchesDataProvider(): array
    {
        return [
            [
                true,
                \Exception::class,
                new \Error(),
            ],
            [
                false,
                \Exception::class,
                new \LogicException(),
            ],
            [
                false,
                \Throwable::class,
                new \Error(),
            ],
        ];
    }

    public function testCaptureEvent(): void
    {
        /** @var TransportInterface&MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
            ->method('send')
            ->willReturn('500a339f3ab2450b96dee542adf36ba7');

        $client = ClientBuilder::create()
            ->setTransportFactory($this->createTransportFactory($transport))
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

    /**
     * @dataProvider captureEventAttachesStacktraceAccordingToAttachStacktraceOptionDataProvider
     */
    public function testCaptureEventAttachesStacktraceAccordingToAttachStacktraceOption(bool $attachStacktraceOption, array $payload, bool $shouldAttachStacktrace): void
    {
        /** @var TransportInterface&MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
            ->method('send')
            ->with($this->callback(static function (Event $event) use ($shouldAttachStacktrace): bool {
                if ($shouldAttachStacktrace && null === $event->getStacktrace()) {
                    return false;
                }

                if (!$shouldAttachStacktrace && null !== $event->getStacktrace()) {
                    return false;
                }

                return true;
            }))
            ->willReturn('500a339f3ab2450b96dee542adf36ba7');

        $client = ClientBuilder::create(['attach_stacktrace' => $attachStacktraceOption])
            ->setTransportFactory($this->createTransportFactory($transport))
            ->getClient();

        $this->assertEquals('500a339f3ab2450b96dee542adf36ba7', $client->captureEvent($payload));
    }

    public function captureEventAttachesStacktraceAccordingToAttachStacktraceOptionDataProvider(): \Generator
    {
        yield 'Stacktrace attached when attach_stacktrace = true and both payload.exception and payload.stacktrace are unset' => [
            true,
            [],
            true,
        ];

        yield 'No stacktrace attached when attach_stacktrace = false' => [
            false,
            [],
            false,
        ];

        yield 'No stacktrace attached when attach_stacktrace = true and payload.exception is set' => [
            true,
            [
                'exception' => new \Exception(),
            ],
            false,
        ];

        yield 'No stacktrace attached when attach_stacktrace = false and payload.exception is set' => [
            true,
            [
                'exception' => new \Exception(),
            ],
            false,
        ];
    }

    public function testCaptureEventPrefersExplicitStacktrace(): void
    {
        $explicitStacktrace = $this->createMock(Stacktrace::class);
        $payload = ['stacktrace' => $explicitStacktrace];

        /** @var TransportInterface&MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
            ->method('send')
            ->with($this->callback(static function (Event $event) use ($explicitStacktrace): bool {
                return $explicitStacktrace === $event->getStacktrace();
            }))
            ->willReturn('500a339f3ab2450b96dee542adf36ba7');

        $client = ClientBuilder::create(['attach_stacktrace' => true])
            ->setTransportFactory($this->createTransportFactory($transport))
            ->getClient();

        $this->assertEquals('500a339f3ab2450b96dee542adf36ba7', $client->captureEvent($payload));
    }

    public function testCaptureLastError(): void
    {
        /** @var TransportInterface&MockObject $transport */
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
            ->setTransportFactory($this->createTransportFactory($transport))
            ->getClient();

        @trigger_error('foo', E_USER_NOTICE);

        $client->captureLastError();

        $this->clearLastError();
    }

    public function testCaptureLastErrorDoesNothingWhenThereIsNoError(): void
    {
        /** @var TransportInterface&MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->never())
            ->method('send');

        $client = ClientBuilder::create(['dsn' => 'http://public:secret@example.com/1'])
            ->setTransportFactory($this->createTransportFactory($transport))
            ->getClient();

        $this->clearLastError();

        $client->captureLastError();
    }

    /**
     * @group legacy
     *
     * @dataProvider captureEventThrowsDeprecationErrorIfContextLinesOptionIsNotNullAndFrameContextifierIntegrationIsNotUsedDataProvider
     *
     * @expectedDeprecation Relying on the "Sentry\Stacktrace" class to contexify the frames of the stacktrace is deprecated since version 2.4 and will stop working in 3.0. Set the $shouldReadSourceCodeExcerpts parameter to "false" and use the "Sentry\Integration\FrameContextifierIntegration" integration instead.
     */
    public function testCaptureEventThrowsDeprecationErrorIfContextLinesOptionIsNotNullAndFrameContextifierIntegrationIsNotUsed(array $payload): void
    {
        ClientBuilder::create(['attach_stacktrace' => true, 'default_integrations' => false])
            ->getClient()
            ->captureEvent($payload);
    }

    public function captureEventThrowsDeprecationErrorIfContextLinesOptionIsNotNullAndFrameContextifierIntegrationIsNotUsedDataProvider(): \Generator
    {
        yield [[]];

        yield [['exception' => new \Exception()]];
    }

    /**
     * @group legacy
     *
     * @requires OSFAMILY Linux
     */
    public function testAppPathLinux(): void
    {
        $client = ClientBuilder::create(['project_root' => '/foo/bar'])->getClient();

        $this->assertEquals('/foo/bar', $client->getOptions()->getProjectRoot());

        $client->getOptions()->setProjectRoot('/foo/baz/');

        $this->assertEquals('/foo/baz/', $client->getOptions()->getProjectRoot());
    }

    /**
     * @group legacy
     */
    public function testAppPathWindows(): void
    {
        $client = ClientBuilder::create(['project_root' => 'C:\\foo\\bar\\'])->getClient();

        $this->assertEquals('C:\\foo\\bar\\', $client->getOptions()->getProjectRoot());
    }

    public function testSendChecksBeforeSendOption(): void
    {
        $beforeSendCalled = false;

        /** @var TransportInterface&MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->never())
            ->method('send');

        $options = new Options(['dsn' => 'http://public:secret@example.com/1']);
        $options->setBeforeSendCallback(function () use (&$beforeSendCalled) {
            $beforeSendCalled = true;

            return null;
        });

        $client = (new ClientBuilder($options))
            ->setTransportFactory($this->createTransportFactory($transport))
            ->getClient();

        $client->captureEvent([]);

        $this->assertTrue($beforeSendCalled);
    }

    /**
     * @dataProvider processEventDiscardsEventWhenItIsSampledDueToSampleRateOptionDataProvider
     */
    public function testProcessEventDiscardsEventWhenItIsSampledDueToSampleRateOption(float $sampleRate, Invocation $transportCallInvocationMatcher, Invocation $loggerCallInvocationMatcher): void
    {
        /** @var TransportInterface&MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($transportCallInvocationMatcher)
            ->method('send');

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($loggerCallInvocationMatcher)
            ->method('info')
            ->with('The event will be discarded because it has been sampled.', $this->callback(static function (array $context): bool {
                return isset($context['event']) && $context['event'] instanceof Event;
            }));

        $client = ClientBuilder::create(['sample_rate' => $sampleRate])
            ->setTransportFactory($this->createTransportFactory($transport))
            ->setLogger($logger)
            ->getClient();

        for ($i = 0; $i < 10; ++$i) {
            $client->captureMessage('foo');
        }
    }

    public function processEventDiscardsEventWhenItIsSampledDueToSampleRateOptionDataProvider(): \Generator
    {
        yield [
            0,
            $this->never(),
            $this->exactly(10),
        ];

        yield [
            1,
            $this->exactly(10),
            $this->never(),
        ];
    }

    public function testProcessEventDiscardsEventWhenBeforeSendCallbackReturnsNull(): void
    {
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('The event will be discarded because the "before_send" callback returned `null`.', $this->callback(static function (array $context): bool {
                return isset($context['event']) && $context['event'] instanceof Event;
            }));

        $options = [
            'before_send' => static function () {
                return null;
            },
        ];

        $client = ClientBuilder::create($options)
            ->setLogger($logger)
            ->getClient();

        $client->captureMessage('foo');
    }

    public function testProcessEventDiscardsEventWhenEventProcessorReturnsNull(): void
    {
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('The event will be discarded because one of the event processors returned `null`.', $this->callback(static function (array $context): bool {
                return isset($context['event']) && $context['event'] instanceof Event;
            }));

        $client = ClientBuilder::create([])
            ->setLogger($logger)
            ->getClient();

        $scope = new Scope();
        $scope->addEventProcessor(static function () {
            return null;
        });

        $client->captureMessage('foo', Severity::debug(), $scope);
    }

    /**
     * @dataProvider convertExceptionDataProvider
     */
    public function testConvertException(\Exception $exception, array $expectedResult): void
    {
        /** @var TransportInterface&MockObject $transport */
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

        $client = ClientBuilder::create()
            ->setTransportFactory($this->createTransportFactory($transport))
            ->getClient();

        $client->captureException($exception);
    }

    public function convertExceptionDataProvider(): array
    {
        return [
            [
                new \RuntimeException('foo'),
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
        ];
    }

    public function testAttachStacktrace(): void
    {
        /** @var TransportInterface&MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Event $event): bool {
                $result = $event->getStacktrace();

                return null !== $result;
            }));

        $client = ClientBuilder::create(['attach_stacktrace' => true])
            ->setTransportFactory($this->createTransportFactory($transport))
            ->getClient();

        $client->captureMessage('test');
    }

    /**
     * @see https://github.com/symfony/polyfill/blob/52332f49d18c413699d2dccf465234356f8e0b2c/src/Php70/Php70.php#L52-L61
     */
    private function clearLastError(): void
    {
        set_error_handler(static function (): bool {
            return false;
        });

        @trigger_error('');

        restore_error_handler();
    }

    private function createTransportFactory(TransportInterface $transport): TransportFactoryInterface
    {
        return new class($transport) implements TransportFactoryInterface {
            /**
             * @var TransportInterface
             */
            private $transport;

            public function __construct(TransportInterface $transport)
            {
                $this->transport = $transport;
            }

            public function create(Options $options): TransportInterface
            {
                return $this->transport;
            }
        };
    }
}
