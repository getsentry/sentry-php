<?php

declare(strict_types=1);

namespace Sentry\Tests;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use PHPUnit\Framework\MockObject\Matcher\Invocation;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sentry\Client;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\ExceptionMechanism;
use Sentry\Frame;
use Sentry\Integration\IntegrationInterface;
use Sentry\Options;
use Sentry\Response;
use Sentry\ResponseStatus;
use Sentry\Serializer\RepresentationSerializerInterface;
use Sentry\Serializer\Serializer;
use Sentry\Serializer\SerializerInterface;
use Sentry\Severity;
use Sentry\Stacktrace;
use Sentry\State\Scope;
use Sentry\Transport\TransportFactoryInterface;
use Sentry\Transport\TransportInterface;

final class ClientTest extends TestCase
{
    public function testConstructorSetupsIntegrations(): void
    {
        $integrationCalled = false;

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('debug');

        $logger->expects($this->once())
            ->method('info')
            ->with('The event will be discarded because one of the event processors returned "null".');

        $integration = new class($integrationCalled) implements IntegrationInterface {
            private $integrationCalled;

            public function __construct(bool &$integrationCalled)
            {
                $this->integrationCalled = &$integrationCalled;
            }

            public function setupOnce(): void
            {
                Scope::addGlobalEventProcessor(function (): ?Event {
                    $this->integrationCalled = true;

                    return null;
                });
            }
        };

        $client = new Client(
            new Options([
                'default_integrations' => false,
                'integrations' => [$integration],
            ]),
            $this->createMock(TransportInterface::class),
            null,
            null,
            null,
            null,
            $logger
        );

        $client->captureEvent([], new Scope());

        $this->assertTrue($integrationCalled);
    }

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
            }))
            ->willReturnCallback(static function (Event $event): FulfilledPromise {
                return new FulfilledPromise(new Response(ResponseStatus::success(), $event));
            });

        $client = ClientBuilder::create()
            ->setTransportFactory($this->createTransportFactory($transport))
            ->getClient();

        $this->assertNotNull($client->captureMessage('foo', Severity::fatal()));
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

                $this->assertSame(\get_class($exception), $exceptionData->getType());
                $this->assertSame($exception->getMessage(), $exceptionData->getValue());

                return true;
            }))
            ->willReturnCallback(static function (Event $event): FulfilledPromise {
                return new FulfilledPromise(new Response(ResponseStatus::success(), $event));
            });

        $client = ClientBuilder::create()
            ->setTransportFactory($this->createTransportFactory($transport))
            ->getClient();

        $this->assertNotNull($client->captureException($exception));
    }

    public function testCaptureEvent(): void
    {
        /** @var TransportInterface&MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
            ->method('send')
            ->willReturnCallback(static function (Event $event): FulfilledPromise {
                return new FulfilledPromise(new Response(ResponseStatus::success(), $event));
            });

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

        $this->assertNotNull($client->captureEvent($inputData));
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
            ->willReturnCallback(static function (Event $event): FulfilledPromise {
                return new FulfilledPromise(new Response(ResponseStatus::success(), $event));
            });

        $client = ClientBuilder::create(['attach_stacktrace' => $attachStacktraceOption])
            ->setTransportFactory($this->createTransportFactory($transport))
            ->getClient();

        $this->assertNotNull($client->captureEvent($payload));
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
        $stacktrace = new Stacktrace([
            new Frame(__METHOD__, __FILE__, __LINE__),
        ]);

        /** @var TransportInterface&MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
            ->method('send')
            ->with($this->callback(static function (Event $event) use ($stacktrace): bool {
                return $stacktrace === $event->getStacktrace();
            }))
            ->willReturnCallback(static function (Event $event): FulfilledPromise {
                return new FulfilledPromise(new Response(ResponseStatus::success(), $event));
            });

        $client = ClientBuilder::create(['attach_stacktrace' => true])
            ->setTransportFactory($this->createTransportFactory($transport))
            ->getClient();

        $this->assertNotNull($client->captureEvent(['stacktrace' => $stacktrace]));
    }

    public function testCaptureLastError(): void
    {
        /** @var TransportInterface&MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Event $event): bool {
                $exception = $event->getExceptions()[0];

                $this->assertEquals('ErrorException', $exception->getType());
                $this->assertEquals('foo', $exception->getValue());

                return true;
            }))
            ->willReturnCallback(static function (Event $event): FulfilledPromise {
                return new FulfilledPromise(new Response(ResponseStatus::success(), $event));
            });

        $client = ClientBuilder::create(['dsn' => 'http://public:secret@example.com/1'])
            ->setTransportFactory($this->createTransportFactory($transport))
            ->getClient();

        @trigger_error('foo', E_USER_NOTICE);

        $this->assertNotNull($client->captureLastError());

        $this->clearLastError();
    }

    public function testCaptureLastErrorDoesNothingWhenThereIsNoError(): void
    {
        /** @var TransportInterface&MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->never())
            ->method('send')
            ->with($this->anything())
            ->willReturn(null);

        $client = ClientBuilder::create(['dsn' => 'http://public:secret@example.com/1'])
            ->setTransportFactory($this->createTransportFactory($transport))
            ->getClient();

        $this->clearLastError();

        $this->assertNull($client->captureLastError());
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
            ->method('send')
            ->with($this->anything())
            ->willReturn(null);

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
            ->with('The event will be discarded because the "before_send" callback returned "null".', $this->callback(static function (array $context): bool {
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
            ->with('The event will be discarded because one of the event processors returned "null".', $this->callback(static function (array $context): bool {
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

    public function testAttachStacktrace(): void
    {
        /** @var TransportInterface&MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Event $event): bool {
                $result = $event->getStacktrace();

                return null !== $result;
            }))
            ->willReturnCallback(static function (Event $event): FulfilledPromise {
                return new FulfilledPromise(new Response(ResponseStatus::success(), $event));
            });

        $client = ClientBuilder::create(['attach_stacktrace' => true])
            ->setTransportFactory($this->createTransportFactory($transport))
            ->getClient();

        $this->assertNotNull($client->captureMessage('test'));
    }

    public function testFlush(): void
    {
        /** @var TransportInterface&MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
            ->method('close')
            ->with(10)
            ->willReturn(new FulfilledPromise(true));

        $client = ClientBuilder::create()
            ->setTransportFactory($this->createTransportFactory($transport))
            ->getClient();

        $promise = $client->flush(10);

        $this->assertSame(PromiseInterface::FULFILLED, $promise->getState());
        $this->assertTrue($promise->wait());
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

    /**
     * @backupGlobals
     */
    public function testBuildEventWithDefaultValues(): void
    {
        $options = new Options();
        $options->setServerName('testServerName');
        $options->setRelease('testRelease');
        $options->setTags(['test' => 'tag']);
        $options->setEnvironment('testEnvironment');

        /** @var TransportInterface&MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Event $event) use ($options): bool {
                $this->assertSame('sentry.sdk.identifier', $event->getSdkIdentifier());
                $this->assertSame('1.2.3', $event->getSdkVersion());
                $this->assertSame($options->getServerName(), $event->getServerName());
                $this->assertSame($options->getRelease(), $event->getRelease());
                $this->assertSame($options->getTags(), $event->getTags());
                $this->assertSame($options->getEnvironment(), $event->getEnvironment());
                $this->assertNull($event->getStacktrace());

                return true;
            }));

        $client = new Client(
            $options,
            $transport,
            'sentry.sdk.identifier',
            '1.2.3',
            $this->createMock(SerializerInterface::class),
            $this->createMock(RepresentationSerializerInterface::class),
        );

        $client->captureEvent([]);
    }

    /**
     * @dataProvider buildWithPayloadDataProvider
     */
    public function testBuildWithPayload(array $payload, ?string $expectedLogger, ?string $expectedMessage, array $expectedMessageParams, ?string $expectedFormattedMessage): void
    {
        /** @var TransportInterface&MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Event $event) use ($expectedLogger, $expectedMessage, $expectedFormattedMessage, $expectedMessageParams): bool {
                $this->assertSame($expectedLogger, $event->getLogger());
                $this->assertSame($expectedMessage, $event->getMessage());
                $this->assertSame($expectedMessageParams, $event->getMessageParams());
                $this->assertSame($expectedFormattedMessage, $event->getMessageFormatted());

                return true;
            }));

        $client = new Client(
            new Options(),
            $transport,
            'sentry.sdk.identifier',
            '1.2.3',
            $this->createMock(SerializerInterface::class),
            $this->createMock(RepresentationSerializerInterface::class),
        );

        $client->captureEvent($payload);
    }

    public function buildWithPayloadDataProvider(): iterable
    {
        yield [
            ['logger' => 'app.php'],
            'app.php',
            null,
            [],
            null,
        ];

        yield [
            ['message' => 'My raw message with interpreted strings like this'],
            null,
            'My raw message with interpreted strings like this',
            [],
            null,
        ];

        yield [
            [
                'message' => 'My raw message with interpreted strings like that',
                'message_params' => ['this'],
                'message_formatted' => 'My raw message with interpreted strings like %s',
            ],
            null,
            'My raw message with interpreted strings like that',
            ['this'],
            'My raw message with interpreted strings like %s',
        ];
    }

    public function testBuildEventInCLIDoesntSetTransaction(): void
    {
        /** @var TransportInterface&MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Event $event): bool {
                $this->assertNull($event->getTransaction());

                return true;
            }));

        $client = new Client(
            new Options(),
            $transport,
            'sentry.sdk.identifier',
            '1.2.3',
            $this->createMock(SerializerInterface::class),
            $this->createMock(RepresentationSerializerInterface::class),
        );

        $client->captureEvent([]);
    }

    public function testBuildEventWithException(): void
    {
        $options = new Options();
        $previousException = new \RuntimeException('testMessage2');
        $exception = new \Exception('testMessage', 0, $previousException);

        /** @var TransportInterface&MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Event $event): bool {
                $capturedExceptions = $event->getExceptions();

                $this->assertCount(2, $capturedExceptions);
                $this->assertNotNull($capturedExceptions[0]->getStacktrace());
                $this->assertEquals(new ExceptionMechanism(ExceptionMechanism::TYPE_GENERIC, true), $capturedExceptions[0]->getMechanism());
                $this->assertSame(\Exception::class, $capturedExceptions[0]->getType());
                $this->assertSame('testMessage', $capturedExceptions[0]->getValue());

                $this->assertNotNull($capturedExceptions[1]->getStacktrace());
                $this->assertEquals(new ExceptionMechanism(ExceptionMechanism::TYPE_GENERIC, true), $capturedExceptions[1]->getMechanism());
                $this->assertSame(\RuntimeException::class, $capturedExceptions[1]->getType());
                $this->assertSame('testMessage2', $capturedExceptions[1]->getValue());

                return true;
            }));

        $client = new Client(
            $options,
            $transport,
            'sentry.sdk.identifier',
            '1.2.3',
            new Serializer($options),
            $this->createMock(RepresentationSerializerInterface::class),
        );

        $client->captureEvent(['exception' => $exception]);
    }

    public function testBuildWithErrorException(): void
    {
        $options = new Options();
        $exception = new \ErrorException('testMessage', 0, E_USER_ERROR);
        /** @var TransportInterface&MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Event $event): bool {
                $this->assertTrue(Severity::error()->isEqualTo($event->getLevel()));

                return true;
            }));

        $client = new Client(
            $options,
            $transport,
            'sentry.sdk.identifier',
            '1.2.3',
            new Serializer($options),
            $this->createMock(RepresentationSerializerInterface::class),
        );

        $client->captureEvent(['exception' => $exception]);
    }

    public function testBuildWithStacktrace(): void
    {
        $options = new Options();
        $options->setAttachStacktrace(true);

        /** @var TransportInterface&MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Event $event): bool {
                $stacktrace = $event->getStacktrace();

                $this->assertInstanceOf(Stacktrace::class, $stacktrace);

                /** @var Frame $lastFrame */
                $lastFrame = array_reverse($stacktrace->getFrames())[0];

                $this->assertSame(
                    'Client.php',
                    basename($lastFrame->getFile())
                );

                return true;
            }));

        $client = new Client(
            $options,
            $transport,
            'sentry.sdk.identifier',
            '1.2.3',
            new Serializer($options),
            $this->createMock(RepresentationSerializerInterface::class),
        );

        $client->captureEvent([]);
    }
}
