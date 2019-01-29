<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\EventFactory;
use Sentry\Frame;
use Sentry\Options;
use Sentry\Serializer\RepresentationSerializerInterface;
use Sentry\Serializer\Serializer;
use Sentry\Serializer\SerializerInterface;
use Sentry\Severity;
use Sentry\Stacktrace;

class EventFactoryTest extends TestCase
{
    /**
     * @var Options
     */
    private $options;

    protected function setUp(): void
    {
        $this->options = new Options();
    }

    public function testCreateEventWithDefaultValues(): void
    {
        $this->options->setServerName('testServerName');
        $this->options->setRelease('testRelease');
        $this->options->setTags(['test' => 'tag']);
        $this->options->setEnvironment('testEnvironment');

        $_SERVER['PATH_INFO'] = 'testPathInfo';

        $eventFactory = new EventFactory(
            $this->createMock(SerializerInterface::class),
            $this->createMock(RepresentationSerializerInterface::class),
            $this->options,
            'sentry.sdk.identifier',
            '1.2.3'
        );

        $event = $eventFactory->create([]);

        $this->assertSame('sentry.sdk.identifier', $event->getSdkIdentifier());
        $this->assertSame('1.2.3', $event->getSdkVersion());
        $this->assertSame($this->options->getServerName(), $event->getServerName());
        $this->assertSame($this->options->getRelease(), $event->getRelease());
        $this->assertSame($this->options->getTags(), $event->getTagsContext()->toArray());
        $this->assertSame($this->options->getEnvironment(), $event->getEnvironment());
        $this->assertSame('testPathInfo', $event->getTransaction());
        $this->assertNull($event->getStacktrace());
    }

    /**
     * @dataProvider createWithPayloadDataProvider
     */
    public function testCreateWithPayload(array $payload, array $expectedSubset): void
    {
        $eventFactory = new EventFactory(
            $this->createMock(SerializerInterface::class),
            $this->createMock(RepresentationSerializerInterface::class),
            $this->options,
            'sentry.sdk.identifier',
            '1.2.3'
        );

        $event = $eventFactory->create($payload);

        $this->assertArraySubset($expectedSubset, $event->toArray());
    }

    public function createWithPayloadDataProvider()
    {
        return [
            [
                ['transaction' => 'testTransaction'],
                ['transaction' => 'testTransaction'],
            ],
            [
                ['logger' => 'testLogger'],
                ['logger' => 'testLogger'],
            ],
            [
                ['message' => 'testMessage'],
                ['message' => 'testMessage'],
            ],
            [
                [
                    'message' => 'testMessage %s',
                    'message_params' => ['param'],
                ],
                [
                    'message' => [
                        'message' => 'testMessage %s',
                        'params' => ['param'],
                        'formatted' => 'testMessage param',
                    ],
                ],
            ],
        ];
    }

    public function testCreateEventInCLIDoesntSetTransaction(): void
    {
        $eventFactory = new EventFactory(
            $this->createMock(SerializerInterface::class),
            $this->createMock(RepresentationSerializerInterface::class),
            $this->options,
            'sentry.sdk.identifier',
            '1.2.3'
        );

        $event = $eventFactory->create([]);

        $this->assertNull($event->getTransaction());
    }

    public function testCreateWithException(): void
    {
        $previousException = new \RuntimeException('testMessage2');
        $exception = new \Exception('testMessage', 0, $previousException);
        $eventFactory = new EventFactory(
            new Serializer($this->options),
            $this->createMock(RepresentationSerializerInterface::class),
            $this->options,
            'sentry.sdk.identifier',
            '1.2.3'
        );

        $event = $eventFactory->create(['exception' => $exception]);
        $expectedData = [
            [
                'type' => \Exception::class,
                'value' => 'testMessage',
            ],
            [
                'type' => \RuntimeException::class,
                'value' => 'testMessage2',
            ],
        ];

        $this->assertArraySubset($expectedData, $event->getExceptions());

        foreach ($event->getExceptions() as $exceptionData) {
            $this->assertInstanceOf(Stacktrace::class, $exceptionData['stacktrace']);
        }
    }

    public function testCreateWithErrorException(): void
    {
        $exception = new \ErrorException('testMessage', 0, E_USER_ERROR);
        $eventFactory = new EventFactory(
            new Serializer($this->options),
            $this->createMock(RepresentationSerializerInterface::class),
            $this->options,
            'sentry.sdk.identifier',
            '1.2.3'
        );

        $event = $eventFactory->create(['exception' => $exception]);

        $this->assertTrue(Severity::error()->isEqualTo($event->getLevel()));
    }

    public function testCreateWithStacktrace(): void
    {
        $this->options = $this->options;
        $this->options->setAttachStacktrace(true);

        $eventFactory = new EventFactory(
            $this->createMock(SerializerInterface::class),
            $this->createMock(RepresentationSerializerInterface::class),
            $this->options,
            'sentry.sdk.identifier',
            '1.2.3'
        );

        $event = $eventFactory->createWithStacktrace([]);
        $stacktrace = $event->getStacktrace();

        $this->assertInstanceOf(Stacktrace::class, $stacktrace);

        /** @var Frame $lastFrame */
        $lastFrame = array_reverse($stacktrace->getFrames())[0];

        $this->assertSame('src/EventFactory.php', $lastFrame->getFile());
    }
}
