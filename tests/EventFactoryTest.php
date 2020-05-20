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
        $this->assertSame($options->getTags(), $event->getTagsContext()->toArray());
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

        $event = $eventFactory->create(['exception' => $exception], false);
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
        $options = new Options();
        $exception = new \ErrorException('testMessage', 0, E_USER_ERROR);
        $eventFactory = new EventFactory(
            new Serializer($options),
            $this->createMock(RepresentationSerializerInterface::class),
            $options,
            'sentry.sdk.identifier',
            '1.2.3'
        );

        $event = $eventFactory->create(['exception' => $exception], false);

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

        $event = $eventFactory->createWithStacktrace([], false);
        $stacktrace = $event->getStacktrace();

        $this->assertInstanceOf(Stacktrace::class, $stacktrace);

        /** @var Frame $lastFrame */
        $lastFrame = array_reverse($stacktrace->getFrames())[0];

        $this->assertSame(
            'src' . \DIRECTORY_SEPARATOR . 'EventFactory.php',
            ltrim($lastFrame->getFile(), \DIRECTORY_SEPARATOR)
        );
    }

    /**
     * @group legacy
     *
     * @dataProvider createThrowsDeprecationErrorIfLastArgumentIsNotSetToFalseDataProvider
     *
     * @expectedDeprecation Relying on the "Sentry\Stacktrace" class to contexify the frames of the stacktrace is deprecated since version 2.4 and will stop working in 3.0. Set the $shouldReadSourceCodeExcerpts parameter to "false" and use the "Sentry\Integration\FrameContextifierIntegration" integration instead.
     */
    public function testCreateThrowsDeprecationErrorIfLastArgumentIsNotSetToFalse(array ...$constructorArguments): void
    {
        $options = new Options();
        $eventFactory = new EventFactory(
            new Serializer($options),
            $this->createMock(RepresentationSerializerInterface::class),
            $options,
            'sentry.sdk.identifier',
            '1.2.3',
            ...$constructorArguments
        );

        $eventFactory->create(['exception' => new \Exception()]);
    }

    /**
     * @group legacy
     *
     * @dataProvider createThrowsDeprecationErrorIfLastArgumentIsNotSetToFalseDataProvider
     *
     * @expectedDeprecation Relying on the "Sentry\Stacktrace" class to contexify the frames of the stacktrace is deprecated since version 2.4 and will stop working in 3.0. Set the $shouldReadSourceCodeExcerpts parameter to "false" and use the "Sentry\Integration\FrameContextifierIntegration" integration instead.
     */
    public function testCreateWithStacktraceThrowsDeprecationErrorIfLastArgumentIsNotSetToFalse(array ...$constructorArguments): void
    {
        $options = new Options();
        $eventFactory = new EventFactory(
            new Serializer($options),
            $this->createMock(RepresentationSerializerInterface::class),
            $options,
            'sentry.sdk.identifier',
            '1.2.3',
            ...$constructorArguments
        );

        $eventFactory->createWithStacktrace([]);
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
