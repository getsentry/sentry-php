<?php

declare(strict_types=1);

namespace Sentry\Tests;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Rule\InvocationOrder;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sentry\Client;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\EventHint;
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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;

final class ClientTest extends TestCase
{
    use ExpectDeprecationTrait;

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

        $client->captureEvent(Event::createEvent(), null, new Scope());

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

    public function testCaptureMessageWithEventHint(): void
    {
        $hint = new EventHint();
        $hint->extra = ['foo' => 'bar'];

        $beforeSendCallbackCalled = false;
        $options = new Options([
            'before_send' => function (Event $event, ?EventHint $hintArg) use ($hint, &$beforeSendCallbackCalled) {
                $this->assertSame($hint, $hintArg);

                $beforeSendCallbackCalled = true;

                return null;
            },
        ]);

        $client = new Client($options, $this->createMock(TransportInterface::class));
        $client->captureMessage('foo', null, null, $hint);

        $this->assertTrue($beforeSendCallbackCalled);
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

    /**
     * @dataProvider captureExceptionWithEventHintDataProvider
     */
    public function testCaptureExceptionWithEventHint(EventHint $hint): void
    {
        $beforeSendCallbackCalled = false;
        $exception = $hint->exception ?? new \Exception();

        $options = new Options([
            'before_send' => function (Event $event, ?EventHint $hintArg) use ($exception, $hint, &$beforeSendCallbackCalled) {
                $this->assertSame($hint, $hintArg);
                $this->assertSame($exception, $hintArg->exception);

                $beforeSendCallbackCalled = true;

                return null;
            },
        ]);

        $client = new Client($options, $this->createMock(TransportInterface::class));
        $client->captureException($exception, null, $hint);

        $this->assertTrue($beforeSendCallbackCalled);
    }

    public function captureExceptionWithEventHintDataProvider(): \Generator
    {
        yield [
            EventHint::fromArray([
                'extra' => ['foo' => 'bar'],
            ]),
        ];

        yield [
            EventHint::fromArray([
                'exception' => new \Exception('foo'),
            ]),
        ];
    }

    /**
     * @group legacy
     *
     * @dataProvider captureEventDataProvider
     */
    public function testCaptureEvent(array $options, Event $event, Event $expectedEvent): void
    {
        if (isset($options['tags'])) {
            $this->expectDeprecation('The option "tags" is deprecated since version 3.2 and will be removed in 4.0. Either set the tags on the scope or on the event.');
        }

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
            ->method('send')
            ->willReturnCallback(function (Event $event) use ($expectedEvent): FulfilledPromise {
                $this->assertEquals($expectedEvent, $event);

                return new FulfilledPromise(new Response(ResponseStatus::success(), $event));
            });

        $client = ClientBuilder::create($options)
            ->setTransportFactory($this->createTransportFactory($transport))
            ->getClient();

        $this->assertSame($event->getId(), $client->captureEvent($event));
    }

    public function captureEventDataProvider(): \Generator
    {
        $event = Event::createEvent();

        yield 'Options set && no event properties set => use options' => [
            [
                'server_name' => 'example.com',
                'release' => '0beec7b5ea3f0fdbc95d0dd47f3c5bc275da8a33',
                'environment' => 'development',
                'tags' => ['context' => 'development'],
            ],
            $event,
            $event,
        ];

        $event = Event::createEvent();
        $event->setServerName('foo.example.com');
        $event->setRelease('721e41770371db95eee98ca2707686226b993eda');
        $event->setEnvironment('production');
        $event->setTags(['context' => 'production']);

        yield 'Options set && event properties set => event properties override options' => [
            [
                'server_name' => 'example.com',
                'release' => '0beec7b5ea3f0fdbc95d0dd47f3c5bc275da8a33',
                'environment' => 'development',
                'tags' => ['context' => 'development', 'ios_version' => '14.0'],
            ],
            $event,
            $event,
        ];

        $event = Event::createEvent();

        yield 'Environment option set to null && no event property set => fallback to default value' => [
            ['environment' => null],
            $event,
            $event,
        ];
    }

    public function testCaptureEventWithEventHint(): void
    {
        $hint = new EventHint();
        $hint->extra = ['foo' => 'bar'];

        $beforeSendCallbackCalled = false;
        $options = new Options([
            'before_send' => function (Event $event, ?EventHint $hintArg) use ($hint, &$beforeSendCallbackCalled) {
                $this->assertSame($hint, $hintArg);

                $beforeSendCallbackCalled = true;

                return null;
            },
        ]);

        $client = new Client($options, $this->createMock(TransportInterface::class));
        $client->captureEvent(Event::createEvent(), $hint);

        $this->assertTrue($beforeSendCallbackCalled);
    }

    /**
     * @dataProvider captureEventAttachesStacktraceAccordingToAttachStacktraceOptionDataProvider
     */
    public function testCaptureEventAttachesStacktraceAccordingToAttachStacktraceOption(bool $attachStacktraceOption, ?EventHint $hint, bool $shouldAttachStacktrace): void
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

        $this->assertNotNull($client->captureEvent(Event::createEvent(), $hint));
    }

    public function captureEventAttachesStacktraceAccordingToAttachStacktraceOptionDataProvider(): \Generator
    {
        yield 'Stacktrace attached when attach_stacktrace = true and both payload.exception and payload.stacktrace are unset' => [
            true,
            null,
            true,
        ];

        yield 'No stacktrace attached when attach_stacktrace = false' => [
            false,
            null,
            false,
        ];

        yield 'No stacktrace attached when attach_stacktrace = true and payload.exception is set' => [
            true,
            EventHint::fromArray([
                'exception' => new \Exception(),
            ]),
            false,
        ];

        yield 'No stacktrace attached when attach_stacktrace = false and payload.exception is set' => [
            true,
            EventHint::fromArray([
                'exception' => new \Exception(),
            ]),
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

        $this->assertNotNull($client->captureEvent(Event::createEvent(), EventHint::fromArray([
            'stacktrace' => $stacktrace,
        ])));
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

        @trigger_error('foo', \E_USER_NOTICE);

        $this->assertNotNull($client->captureLastError());

        error_clear_last();
    }

    public function testCaptureLastErrorWithEventHint(): void
    {
        $hint = new EventHint();
        $hint->extra = ['foo' => 'bar'];

        $beforeSendCallbackCalled = false;
        $options = new Options([
            'before_send' => function (Event $event, ?EventHint $hintArg) use ($hint, &$beforeSendCallbackCalled) {
                $this->assertSame($hint, $hintArg);

                $beforeSendCallbackCalled = true;

                return null;
            },
        ]);

        $client = new Client($options, $this->createMock(TransportInterface::class));

        @trigger_error('foo', \E_USER_NOTICE);

        $client->captureLastError(null, $hint);

        error_clear_last();

        $this->assertTrue($beforeSendCallbackCalled);
    }

    public function testCaptureLastErrorDoesNothingWhenThereIsNoError(): void
    {
        /** @var TransportInterface&MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->never())
            ->method('send')
            ->with($this->anything());

        $client = ClientBuilder::create(['dsn' => 'http://public:secret@example.com/1'])
            ->setTransportFactory($this->createTransportFactory($transport))
            ->getClient();

        error_clear_last();

        $this->assertNull($client->captureLastError());
    }

    /**
     * @dataProvider processEventChecksBeforeSendOptionDataProvider
     */
    public function testProcessEventChecksBeforeSendOption(Event $event, bool $expectedBeforeSendCall): void
    {
        $beforeSendCalled = false;
        $options = [
            'before_send' => static function () use (&$beforeSendCalled) {
                $beforeSendCalled = true;

                return null;
            },
        ];

        $client = ClientBuilder::create($options)->getClient();
        $client->captureEvent($event);

        $this->assertSame($expectedBeforeSendCall, $beforeSendCalled);
    }

    public function processEventChecksBeforeSendOptionDataProvider(): \Generator
    {
        yield [
            Event::createEvent(),
            true,
        ];

        yield [
            Event::createTransaction(),
            false,
        ];
    }

    /**
     * @dataProvider processEventDiscardsEventWhenItIsSampledDueToSampleRateOptionDataProvider
     */
    public function testProcessEventDiscardsEventWhenItIsSampledDueToSampleRateOption(float $sampleRate, InvocationOrder $transportCallInvocationMatcher, InvocationOrder $loggerCallInvocationMatcher): void
    {
        /** @var TransportInterface&MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($transportCallInvocationMatcher)
            ->method('send')
            ->with($this->anything());

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
            $this->createMock(RepresentationSerializerInterface::class)
        );

        $client->captureEvent(Event::createEvent());
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
            $this->createMock(RepresentationSerializerInterface::class)
        );

        $client->captureEvent(Event::createEvent(), EventHint::fromArray([
            'exception' => $exception,
        ]));
    }

    public function testBuildWithErrorException(): void
    {
        $options = new Options();
        $exception = new \ErrorException('testMessage', 0, \E_USER_ERROR);
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
            $this->createMock(RepresentationSerializerInterface::class)
        );

        $client->captureEvent(Event::createEvent(), EventHint::fromArray([
            'exception' => $exception,
        ]));
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
            $this->createMock(RepresentationSerializerInterface::class)
        );

        $client->captureEvent(Event::createEvent());
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
