<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\Constraint\StringMatchesFormatDescription;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sentry\Client;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\ExceptionDataBag;
use Sentry\ExceptionMechanism;
use Sentry\Frame;
use Sentry\Integration\IntegrationInterface;
use Sentry\Options;
use Sentry\Serializer\RepresentationSerializerInterface;
use Sentry\Severity;
use Sentry\Stacktrace;
use Sentry\State\Scope;
use Sentry\Tests\Fixtures\code\CustomException;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
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
               ->with(new StringMatchesFormatDescription('The event [%s] will be discarded because one of the event processors returned "null".'));

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
                  ->willReturnCallback(static function (Event $event): Result {
                      return new Result(ResultStatus::success(), $event);
                  });

        $client = ClientBuilder::create()
                               ->setTransport($transport)
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
                  ->willReturnCallback(static function (Event $event): Result {
                      return new Result(ResultStatus::success(), $event);
                  });

        $client = ClientBuilder::create()
                               ->setTransport($transport)
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

    public static function captureExceptionWithEventHintDataProvider(): \Generator
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
     * @group        legacy
     *
     * @dataProvider captureEventDataProvider
     */
    public function testCaptureEvent(array $options, Event $event, Event $expectedEvent): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
                  ->method('send')
                  ->with($expectedEvent)
                  ->willReturnCallback(static function (Event $event): Result {
                      return new Result(ResultStatus::success(), $event);
                  });

        $client = ClientBuilder::create($options)
                               ->setTransport($transport)
                               ->getClient();

        $this->assertSame($event->getId(), $client->captureEvent($event));
    }

    public static function captureEventDataProvider(): \Generator
    {
        $event = Event::createEvent();
        $expectedEvent = clone $event;
        $expectedEvent->setServerName('example.com');
        $expectedEvent->setRelease('0beec7b5ea3f0fdbc95d0dd47f3c5bc275da8a33');
        $expectedEvent->setEnvironment('development');
        $expectedEvent->setTags(['context' => 'development']);

        yield 'Options set && no event properties set => use options' => [
            [
                'server_name' => 'example.com',
                'release' => '0beec7b5ea3f0fdbc95d0dd47f3c5bc275da8a33',
                'environment' => 'development',
                'tags' => ['context' => 'development'],
            ],
            $event,
            $expectedEvent,
        ];

        $event = Event::createEvent();
        $event->setServerName('foo.example.com');
        $event->setRelease('721e41770371db95eee98ca2707686226b993eda');
        $event->setEnvironment('production');
        $event->setTags(['context' => 'production']);

        $expectedEvent = clone $event;
        $expectedEvent->setTags(['context' => 'production', 'ios_version' => '14.0']);

        yield 'Options set && event properties set => event properties override options' => [
            [
                'server_name' => 'example.com',
                'release' => '0beec7b5ea3f0fdbc95d0dd47f3c5bc275da8a33',
                'environment' => 'development',
                'tags' => ['context' => 'development', 'ios_version' => '14.0'],
            ],
            $event,
            $expectedEvent,
        ];

        $event = Event::createEvent();
        $event->setServerName('example.com');

        $expectedEvent = clone $event;
        $expectedEvent->setEnvironment('production');

        yield 'Environment option set to null && no event property set => fallback to default value' => [
            ['environment' => null],
            $event,
            $expectedEvent,
        ];

        $event = Event::createEvent();
        $event->setServerName('example.com');
        $event->setLevel(Severity::warning());
        $event->setExceptions([new ExceptionDataBag(new \ErrorException())]);

        $expectedEvent = clone $event;
        $expectedEvent->setEnvironment('production');

        yield 'Error level is set && exception is instance of ErrorException => preserve the error level set by the user' => [
            [],
            $event,
            $expectedEvent,
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
                      if ($shouldAttachStacktrace && $event->getStacktrace() === null) {
                          return false;
                      }

                      if (!$shouldAttachStacktrace && $event->getStacktrace() !== null) {
                          return false;
                      }

                      return true;
                  }))
                  ->willReturnCallback(static function (Event $event): Result {
                      return new Result(ResultStatus::success(), $event);
                  });

        $client = ClientBuilder::create(['attach_stacktrace' => $attachStacktraceOption])
                               ->setTransport($transport)
                               ->getClient();

        $this->assertNotNull($client->captureEvent(Event::createEvent(), $hint));
    }

    public static function captureEventAttachesStacktraceAccordingToAttachStacktraceOptionDataProvider(): \Generator
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
                  ->willReturnCallback(static function (Event $event): Result {
                      return new Result(ResultStatus::success(), $event);
                  });

        $client = ClientBuilder::create(['attach_stacktrace' => true])
                               ->setTransport($transport)
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
                  ->willReturnCallback(static function (Event $event): Result {
                      return new Result(ResultStatus::success(), $event);
                  });

        $client = ClientBuilder::create(['dsn' => 'http://public:secret@example.com/1'])
                               ->setTransport($transport)
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
                               ->setTransport($transport)
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

    public static function processEventChecksBeforeSendOptionDataProvider(): \Generator
    {
        yield [
            Event::createEvent(),
            true,
        ];

        yield [
            Event::createTransaction(),
            false,
        ];

        yield [
            Event::createCheckIn(),
            false,
        ];

        yield [
            Event::createMetrics(),
            false,
        ];
    }

    /**
     * @dataProvider processEventChecksBeforeSendTransactionOptionDataProvider
     */
    public function testProcessEventChecksBeforeSendTransactionOption(Event $event, bool $expectedBeforeSendCall): void
    {
        $beforeSendTransactionCalled = false;
        $options = [
            'before_send_transaction' => static function () use (&$beforeSendTransactionCalled) {
                $beforeSendTransactionCalled = true;

                return null;
            },
        ];

        $client = ClientBuilder::create($options)->getClient();
        $client->captureEvent($event);

        $this->assertSame($expectedBeforeSendCall, $beforeSendTransactionCalled);
    }

    public static function processEventChecksBeforeSendTransactionOptionDataProvider(): \Generator
    {
        yield [
            Event::createEvent(),
            false,
        ];

        yield [
            Event::createTransaction(),
            true,
        ];

        yield [
            Event::createCheckIn(),
            false,
        ];

        yield [
            Event::createMetrics(),
            false,
        ];
    }

    /**
     * @dataProvider processEventChecksBeforeSendCheckInOptionDataProvider
     */
    public function testProcessEventChecksBeforeSendCheckInOption(Event $event, bool $expectedBeforeSendCall): void
    {
        $beforeSendCalled = false;
        $options = [
            'before_send_check_in' => static function () use (&$beforeSendCalled) {
                $beforeSendCalled = true;

                return null;
            },
        ];

        $client = ClientBuilder::create($options)->getClient();
        $client->captureEvent($event);

        $this->assertSame($expectedBeforeSendCall, $beforeSendCalled);
    }

    public static function processEventChecksBeforeSendCheckInOptionDataProvider(): \Generator
    {
        yield [
            Event::createEvent(),
            false,
        ];

        yield [
            Event::createTransaction(),
            false,
        ];

        yield [
            Event::createCheckIn(),
            true,
        ];

        yield [
            Event::createMetrics(),
            false,
        ];
    }

    /**
     * @dataProvider processEventChecksBeforeSendMetricsOptionDataProvider
     */
    public function testProcessEventChecksBeforeMetricsSendOption(Event $event, bool $expectedBeforeSendCall): void
    {
        $beforeSendCalled = false;
        $options = [
            'before_send_metrics' => static function () use (&$beforeSendCalled) {
                $beforeSendCalled = true;

                return null;
            },
        ];

        $client = ClientBuilder::create($options)->getClient();
        $client->captureEvent($event);

        $this->assertSame($expectedBeforeSendCall, $beforeSendCalled);
    }

    public static function processEventChecksBeforeSendMetricsOptionDataProvider(): \Generator
    {
        yield [
            Event::createEvent(),
            false,
        ];

        yield [
            Event::createTransaction(),
            false,
        ];

        yield [
            Event::createCheckIn(),
            false,
        ];
    }

    public function testProcessEventDiscardsEventWhenSampleRateOptionIsZero(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->never())
                  ->method('send')
                  ->with($this->anything());

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
               ->method('info')
               ->with(
                   new StringMatchesFormatDescription('The event [%s] will be discarded because it has been sampled.'),
                   $this->callback(static function (array $context): bool {
                       return isset($context['event']) && $context['event'] instanceof Event;
                   })
               );

        $client = ClientBuilder::create(['sample_rate' => 0])
                               ->setTransport($transport)
                               ->setLogger($logger)
                               ->getClient();

        $client->captureEvent(Event::createEvent());
    }

    public function testProcessEventCapturesEventWhenSampleRateOptionIsAboveZero(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
                  ->method('send')
                  ->with($this->anything());

        $client = ClientBuilder::create(['sample_rate' => 1])
                               ->setTransport($transport)
                               ->getClient();

        $client->captureEvent(Event::createEvent());
    }

    public function testProcessEventDiscardsEventWhenIgnoreExceptionsMatches(): void
    {
        $exception = new \Exception('Some foo error');

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
               ->method('info')
               ->with('The exception will be discarded because it matches an entry in "ignore_exceptions".');

        $options = [
            'ignore_exceptions' => [\Exception::class],
        ];

        $client = ClientBuilder::create($options)
                               ->setLogger($logger)
                               ->getClient();

        $client->captureException($exception);
    }

    public function testProcessEventDiscardsEventWhenParentHierarchyOfIgnoreExceptionsMatches(): void
    {
        $exception = new CustomException('Some foo error');

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
               ->method('info')
               ->with('The exception will be discarded because it matches an entry in "ignore_exceptions".');

        $options = [
            'ignore_exceptions' => [\RuntimeException::class],
        ];

        $client = ClientBuilder::create($options)
                               ->setLogger($logger)
                               ->getClient();

        $client->captureException($exception);
    }

    public function testProcessEventDiscardsEventWhenIgnoreTransactionsMatches(): void
    {
        $event = Event::createTransaction();
        $event->setTransaction('GET /foo');

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
               ->method('info')
               ->with(
                   new StringMatchesFormatDescription('The transaction [%s] will be discarded because it matches a entry in "ignore_transactions".'),
                   $this->callback(static function (array $context): bool {
                       return isset($context['event']) && $context['event'] instanceof Event;
                   })
               );

        $options = [
            'ignore_transactions' => ['GET /foo'],
        ];

        $client = ClientBuilder::create($options)
                               ->setLogger($logger)
                               ->getClient();

        $client->captureEvent($event);
    }

    public function testProcessEventDiscardsEventWhenBeforeSendCallbackReturnsNull(): void
    {
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
               ->method('info')
               ->with(
                   new StringMatchesFormatDescription('The event [%s] will be discarded because the "before_send" callback returned "null".'),
                   $this->callback(static function (array $context): bool {
                       return isset($context['event']) && $context['event'] instanceof Event;
                   })
               );

        $options = [
            'before_send' => static function () {
                return null;
            },
        ];

        $client = ClientBuilder::create($options)
                               ->setLogger($logger)
                               ->getClient();

        $client->captureEvent(Event::createEvent());
    }

    public function testProcessEventDiscardsEventWhenBeforeSendTransactionCallbackReturnsNull(): void
    {
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
               ->method('info')
               ->with(
                   new StringMatchesFormatDescription('The transaction [%s] will be discarded because the "before_send_transaction" callback returned "null".'),
                   $this->callback(static function (array $context): bool {
                       return isset($context['event']) && $context['event'] instanceof Event;
                   })
               );

        $options = [
            'before_send_transaction' => static function () {
                return null;
            },
        ];

        $client = ClientBuilder::create($options)
                               ->setLogger($logger)
                               ->getClient();

        $client->captureEvent(Event::createTransaction());
    }

    public function testProcessEventDiscardsEventWhenBeforeSendCheckInCallbackReturnsNull(): void
    {
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
               ->method('info')
               ->with(
                   new StringMatchesFormatDescription('The check_in [%s] will be discarded because the "before_send_check_in" callback returned "null".'),
                   $this->callback(static function (array $context): bool {
                       return isset($context['event']) && $context['event'] instanceof Event;
                   })
               );

        $options = [
            'before_send_check_in' => static function () {
                return null;
            },
        ];

        $client = ClientBuilder::create($options)
                               ->setLogger($logger)
                               ->getClient();

        $client->captureEvent(Event::createCheckIn());
    }

    public function testProcessEventDiscardsEventWhenEventProcessorReturnsNull(): void
    {
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
               ->method('info')
               ->with(
                   new StringMatchesFormatDescription('The debug event [%s] will be discarded because one of the event processors returned "null".'),
                   $this->callback(static function (array $context): bool {
                       return isset($context['event']) && $context['event'] instanceof Event;
                   })
               );

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

                      return $result !== null;
                  }))
                  ->willReturnCallback(static function (Event $event): Result {
                      return new Result(ResultStatus::success(), $event);
                  });

        $client = ClientBuilder::create(['attach_stacktrace' => true])
                               ->setTransport($transport)
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
                  ->willReturn(new Result(ResultStatus::success()));

        $client = ClientBuilder::create()
                               ->setTransport($transport)
                               ->getClient();

        $response = $client->flush(10);

        $this->assertSame(ResultStatus::success(), $response->getStatus());
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
            $this->createMock(RepresentationSerializerInterface::class)
        );

        $client->captureEvent(Event::createEvent());
    }

    public function testBuildEventWithException(): void
    {
        $options = new Options();
        $previousException = new \RuntimeException('testMessage2');
        $exception = new \Exception('testMessage', 1, $previousException);

        /** @var TransportInterface&MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
                  ->method('send')
                  ->with($this->callback(function (Event $event): bool {
                      $capturedExceptions = $event->getExceptions();

                      $this->assertCount(2, $capturedExceptions);
                      $this->assertNotNull($capturedExceptions[0]->getStacktrace());
                      $this->assertEquals(new ExceptionMechanism(ExceptionMechanism::TYPE_GENERIC, true, ['code' => 1]), $capturedExceptions[0]->getMechanism());
                      $this->assertSame(\Exception::class, $capturedExceptions[0]->getType());
                      $this->assertSame('testMessage', $capturedExceptions[0]->getValue());

                      $this->assertNotNull($capturedExceptions[1]->getStacktrace());
                      $this->assertEquals(new ExceptionMechanism(ExceptionMechanism::TYPE_GENERIC, true, ['code' => 0]), $capturedExceptions[1]->getMechanism());
                      $this->assertSame(\RuntimeException::class, $capturedExceptions[1]->getType());
                      $this->assertSame('testMessage2', $capturedExceptions[1]->getValue());

                      return true;
                  }));

        $client = new Client(
            $options,
            $transport,
            'sentry.sdk.identifier',
            '1.2.3',
            $this->createMock(RepresentationSerializerInterface::class)
        );

        $client->captureEvent(Event::createEvent(), EventHint::fromArray([
            'exception' => $exception,
        ]));
    }

    public function testBuildEventWithExceptionAndMechansim(): void
    {
        $options = new Options();
        $exception = new \Exception('testMessage');

        /** @var TransportInterface&MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
                  ->method('send')
                  ->with($this->callback(function (Event $event): bool {
                      $capturedExceptions = $event->getExceptions();

                      $this->assertCount(1, $capturedExceptions);
                      $this->assertNotNull($capturedExceptions[0]->getStacktrace());
                      $this->assertEquals(new ExceptionMechanism(ExceptionMechanism::TYPE_GENERIC, false), $capturedExceptions[0]->getMechanism());
                      $this->assertSame(\Exception::class, $capturedExceptions[0]->getType());
                      $this->assertSame('testMessage', $capturedExceptions[0]->getValue());

                      return true;
                  }));

        $client = new Client(
            $options,
            $transport,
            'sentry.sdk.identifier',
            '1.2.3',
            $this->createMock(RepresentationSerializerInterface::class)
        );

        $client->captureEvent(Event::createEvent(), EventHint::fromArray([
            'exception' => $exception,
            'mechanism' => new ExceptionMechanism(ExceptionMechanism::TYPE_GENERIC, false),
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
            $this->createMock(RepresentationSerializerInterface::class)
        );

        $client->captureEvent(Event::createEvent());
    }

    public function testBuildWithCustomStacktrace(): void
    {
        $options = new Options();

        /** @var TransportInterface&MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
                  ->method('send')
                  ->with($this->callback(function (Event $event): bool {
                      $stacktrace = $event->getStacktrace();

                      $this->assertNotNull($stacktrace);

                      /** @var Frame $lastFrame */
                      $lastFrame = array_reverse($stacktrace->getFrames())[0];

                      $this->assertSame(
                          'MyApp.php',
                          $lastFrame->getFile()
                      );

                      return true;
                  }));

        $client = new Client(
            $options,
            $transport,
            'sentry.sdk.identifier',
            '1.2.3',
            $this->createMock(RepresentationSerializerInterface::class)
        );

        $stacktrace = $client->getStacktraceBuilder()->buildFromBacktrace(debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 1), 'MyApp.php', 42);

        $event = Event::createEvent();
        $event->setStacktrace($stacktrace);

        $client->captureEvent($event);
    }

    /**
     * @dataProvider getCspReportUrlDataProvider
     */
    public function testGetCspReportUrl(array $options, ?string $expectedUrl): void
    {
        $client = new Client(
            new Options($options),
            $this->createMock(TransportInterface::class),
            'sentry.sdk.identifier',
            '1.2.3',
            $this->createMock(RepresentationSerializerInterface::class)
        );

        $this->assertSame($expectedUrl, $client->getCspReportUrl());
    }

    public static function getCspReportUrlDataProvider(): \Generator
    {
        yield [
            ['dsn' => null],
            null,
            null,
        ];

        yield [
            ['dsn' => 'https://public:secret@example.com/1'],
            'https://example.com/api/1/security/?sentry_key=public',
        ];

        yield [
            [
                'dsn' => 'https://public:secret@example.com/1',
                'release' => 'dev-release',
            ],
            'https://example.com/api/1/security/?sentry_key=public&sentry_release=dev-release',
        ];

        yield [
            [
                'dsn' => 'https://public:secret@example.com/1',
                'environment' => 'development',
            ],
            'https://example.com/api/1/security/?sentry_key=public&sentry_environment=development',
        ];

        yield [
            [
                'dsn' => 'https://public:secret@example.com/1',
                'release' => 'dev-release',
                'environment' => 'development',
            ],
            'https://example.com/api/1/security/?sentry_key=public&sentry_release=dev-release&sentry_environment=development',
        ];
    }
}
