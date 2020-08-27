<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\EventFactory;
use Sentry\ExceptionMechanism;
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
     * @backupGlobals
     */
    public function testCreateEventWithDefaultValues(): void
    {
        $options = new Options();
        $options->setServerName('testServerName');
        $options->setRelease('testRelease');
        $options->setTags(['test' => 'tag']);
        $options->setEnvironment('testEnvironment');

        $eventFactory = new EventFactory(
            $this->createMock(SerializerInterface::class),
            $this->createMock(RepresentationSerializerInterface::class),
            $options,
            'sentry.sdk.identifier',
            '1.2.3'
        );

        $event = $eventFactory->create([]);

        $this->assertSame('sentry.sdk.identifier', $event->getSdkIdentifier());
        $this->assertSame('1.2.3', $event->getSdkVersion());
        $this->assertSame($options->getServerName(), $event->getServerName());
        $this->assertSame($options->getRelease(), $event->getRelease());
        $this->assertSame($options->getTags(), $event->getTags());
        $this->assertSame($options->getEnvironment(), $event->getEnvironment());
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
            new Options(),
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
            [
                [
                    'message' => 'testMessage %foo',
                    'message_params' => ['%foo' => 'param'],
                    'message_formatted' => 'testMessage param',
                ],
                [
                    'message' => [
                        'message' => 'testMessage %foo',
                        'params' => ['%foo' => 'param'],
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
            new Options(),
            'sentry.sdk.identifier',
            '1.2.3'
        );

        $event = $eventFactory->create([]);

        $this->assertNull($event->getTransaction());
    }

    public function testCreateWithException(): void
    {
        $options = new Options();
        $previousException = new \RuntimeException('testMessage2');
        $exception = new \Exception('testMessage', 0, $previousException);
        $eventFactory = new EventFactory(
            new Serializer($options),
            $this->createMock(RepresentationSerializerInterface::class),
            $options,
            'sentry.sdk.identifier',
            '1.2.3'
        );

        $event = $eventFactory->create(['exception' => $exception]);
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
    }

    public function testCreateWithErrorException(): void
    {
        $options = new Options();
        $exception = new \ErrorException('testMessage', 0, E_USER_ERROR);
        $eventFactory = new EventFactory(
            new Serializer($options),
            $this->createMock(RepresentationSerializerInterface::class),
            $options,
            'sentry.sdk.identifier',
            '1.2.3'
        );

        $event = $eventFactory->create(['exception' => $exception]);

        $this->assertTrue(Severity::error()->isEqualTo($event->getLevel()));
    }

    public function testCreateWithStacktrace(): void
    {
        $options = new Options();
        $options->setAttachStacktrace(true);

        $eventFactory = new EventFactory(
            $this->createMock(SerializerInterface::class),
            $this->createMock(RepresentationSerializerInterface::class),
            $options,
            'sentry.sdk.identifier',
            '1.2.3'
        );

        $event = $eventFactory->createWithStacktrace([]);
        $stacktrace = $event->getStacktrace();

        $this->assertInstanceOf(Stacktrace::class, $stacktrace);

        /** @var Frame $lastFrame */
        $lastFrame = array_reverse($stacktrace->getFrames())[0];

        $this->assertSame(
            'src' . \DIRECTORY_SEPARATOR . 'EventFactory.php',
            ltrim($lastFrame->getFile(), \DIRECTORY_SEPARATOR)
        );
    }

    public function createThrowsDeprecationErrorIfLastArgumentIsNotSetToFalseDataProvider(): \Generator
    {
        yield [[true]];

        yield [[1]];

        yield [['foo']];

        yield [[new class() {
        }]];

        yield [[]];
    }
}
