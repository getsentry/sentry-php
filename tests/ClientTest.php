<?php

declare(strict_types=1);

namespace Sentry\Tests;

use Http\Discovery\MessageFactoryDiscovery;
use Http\Mock\Client as MockClient;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumb;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\EventFactory;
use Sentry\Options;
use Sentry\Serializer\RepresentationSerializerInterface;
use Sentry\Serializer\Serializer;
use Sentry\Serializer\SerializerInterface;
use Sentry\Severity;
use Sentry\Stacktrace;
use Sentry\Transport\HttpTransport;
use Sentry\Transport\TransportInterface;
use function Clue\StreamFilter\fun;
use function Sentry\addBreadcrumb;

class ClientTest extends TestCase
{
    public function testCaptureMessage(): void
    {
        /** @var TransportInterface|MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Event $event): bool {
                $this->assertSame('foo', $event->getMessage());
                $this->assertEquals(Severity::fatal(), $event->getLevel());

                return true;
            }));

        $client = ClientBuilder::create()
            ->setTransport($transport)
            ->getClient();

        $client->captureMessage('foo', Severity::fatal());
    }

    public function testCaptureException(): void
    {
        $exception = new \Exception('Some foo error');

        /** @var TransportInterface|MockObject $transport */
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
            ->setTransport($transport)
            ->getClient();

        $client->captureException($exception);
    }

    /**
     * @dataProvider captureExceptionDoesNothingIfExcludedExceptionsOptionMatchesDataProvider
     */
    public function testCaptureExceptionDoesNothingIfExcludedExceptionsOptionMatches(
        bool $shouldCapture,
        string $excluded,
        \Throwable $thrown
    ): void {
        $transport = $this->createMock(TransportInterface::class);

        $transport->expects($shouldCapture ? $this->once() : $this->never())
            ->method('send')
            ->with($this->callback(function (Event $event): bool {
                $this->assertNotEmpty($event->getExceptions());

                return true;
            }));

        $client = ClientBuilder::create(['excluded_exceptions' => [$excluded]])
            ->setTransport($transport)
            ->getClient();

        $client->captureException($thrown);
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

    public function testCapture(): void
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

    public function testCaptureLastError(): void
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

    public function testCaptureLastErrorDoesNothingWhenThereIsNoError(): void
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

    /**
     * @requires OSFAMILY Linux
     */
    public function testAppPathLinux(): void
    {
        $client = ClientBuilder::create(['project_root' => '/foo/bar'])->getClient();

        $this->assertEquals('/foo/bar', $client->getOptions()->getProjectRoot());

        $client->getOptions()->setProjectRoot('/foo/baz/');

        $this->assertEquals('/foo/baz/', $client->getOptions()->getProjectRoot());
    }

    public function testAppPathWindows(): void
    {
        $client = ClientBuilder::create(['project_root' => 'C:\\foo\\bar\\'])->getClient();

        $this->assertEquals('C:\\foo\\bar\\', $client->getOptions()->getProjectRoot());
    }

    public function testSendChecksBeforeSendOption(): void
    {
        $beforeSendCalled = false;

        /** @var TransportInterface|MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->never())
            ->method('send');

        $options = new Options(['dsn' => 'http://public:secret@example.com/1']);
        $options->setBeforeSendCallback(function () use (&$beforeSendCalled) {
            $beforeSendCalled = true;

            return null;
        });

        $client = (new ClientBuilder($options))
            ->setTransport($transport)
            ->getClient();

        $client->captureEvent([]);

        $this->assertTrue($beforeSendCalled);
    }

    /**
     * @dataProvider sampleRateAbsoluteDataProvider
     */
    public function testSampleRateAbsolute(float $sampleRate): void
    {
        $httpClient = new MockClient();
        $options = new Options(['dsn' => 'http://public:secret@example.com/1']);
        $options->setSampleRate($sampleRate);

        $client = (new ClientBuilder($options))
            ->setTransport(new HttpTransport($options, $httpClient, MessageFactoryDiscovery::find(), false))
            ->getClient();

        for ($i = 0; $i < 10; ++$i) {
            $client->captureMessage('foobar');
        }

        switch ($sampleRate) {
            case 0:
                $this->assertEmpty($httpClient->getRequests());
                break;
            case 1:
                $this->assertNotEmpty($httpClient->getRequests());
                break;
        }
    }

    public function sampleRateAbsoluteDataProvider(): array
    {
        return [
            'sample rate 0' => [0],
            'sample rate 1' => [1],
        ];
    }

    /**
     * @dataProvider convertExceptionDataProvider
     */
    public function testConvertException(\Exception $exception, array $expectedResult): void
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

        $client = ClientBuilder::create()
            ->setTransport($transport)
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

    public function testConvertExceptionThrownInLatin1File(): void
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

        $serializer = new Serializer(new Options());
        $serializer->setMbDetectOrder('ISO-8859-1, ASCII, UTF-8');

        $client = ClientBuilder::create()
            ->setTransport($transport)
            ->setSerializer($serializer)
            ->getClient();

        $client->captureException(require_once __DIR__ . '/Fixtures/code/Latin1File.php');
    }

    public function testAttachStacktrace(): void
    {
        /** @var TransportInterface|MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Event $event): bool {
                $result = $event->getStacktrace();

                $this->assertInstanceOf(Stacktrace::class, $result);

                $frames = $result->getFrames();

                $this->assertNotEmpty($frames);
                $this->assertTrue($frames[0]->isInApp());
                $this->assertFalse($frames[\count($frames) - 1]->isInApp());

                return true;
            }));

        $client = ClientBuilder::create(['attach_stacktrace' => true])
            ->setTransport($transport)
            ->getClient();

        $client->captureMessage('test');
    }

    public function testAttachStacktraceShouldNotWorkWithCaptureEvent(): void
    {
        /** @var TransportInterface|MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Event $event): bool {
                $this->assertNull($event->getStacktrace());

                return true;
            }));

        $client = ClientBuilder::create(['attach_stacktrace' => true])
            ->setTransport($transport)
            ->getClient();

        $client->captureEvent([]);
    }

    /**
     * @see https://github.com/getsentry/sentry-php/issues/828
     */
    public function testCaptureWithInvalidEncoding(): void
    {
        $transport = new class implements TransportInterface {
            public function send(Event $event): ?string
            {
                $serializer = new Serializer(new Options(), 3000);
                json_encode($serializer->serialize($event), JSON_PRETTY_PRINT);
                Assert::assertSame(JSON_ERROR_NONE, json_last_error(), 'JSON ENCODE ERROR: ' . json_last_error_msg());

                return null;
            }
        };

        $client = (new ClientBuilder())
            ->setTransport($transport)
            ->getClient();
        $brokenString = "\x42\x65\x61\x75\x6d\x6f\x6e\x74\x2d\x65\x6e\x2d\x76\xe9\x72\x6f\x6e";

        $client->captureMessage($brokenString);

        try {
            $brokenString();
        } catch (\Throwable $exception) {
            $client->captureException($exception);
        }
    }

    /**
     * @see https://github.com/symfony/polyfill/blob/52332f49d18c413699d2dccf465234356f8e0b2c/src/Php70/Php70.php#L52-L61
     */
    private function clearLastError(): void
    {
        $handler = static function () {
            return false;
        };

        set_error_handler($handler);
        @trigger_error('');
        restore_error_handler();
    }

    private function createEventFactory(): EventFactory
    {
        return new EventFactory(
            $this->createMock(SerializerInterface::class),
            $this->createMock(RepresentationSerializerInterface::class),
            new Options(),
            'sentry.sdk.identifier',
            '1.2.3'
        );
    }
}

function invalid_encoding()
{
}

function simple_function($a = null, $b = null, $c = null)
{
    throw new \RuntimeException('This simple function should fail before reaching this line!');
}
