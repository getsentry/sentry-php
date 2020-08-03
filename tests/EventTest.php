<?php

declare(strict_types=1);

namespace Sentry\Tests;

use Jean85\PrettyVersions;
use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumb;
use Sentry\Client;
use Sentry\Event;
use Sentry\Severity;
use Sentry\Stacktrace;
use Sentry\Util\PHPVersion;
use Symfony\Bridge\PhpUnit\ClockMock;

/**
 * @group time-sensitive
 */
final class EventTest extends TestCase
{
    public function testEventIsGeneratedWithUniqueIdentifier(): void
    {
        $event1 = new Event();
        $event2 = new Event();

        $this->assertNotEquals($event1->getId(false), $event2->getId(false));
    }

    /**
     * @group legacy
     *
     * @expectedDeprecation Calling the method Sentry\Event::getId() and expecting it to return a string is deprecated since version 2.4 and will stop working in 3.0.
     */
    public function testGetEventIdThrowsDeprecationErrorIfExpectingStringReturnType(): void
    {
        $event = new Event();

        $this->assertSame($event->getId(), (string) $event->getId(false));
    }

    public function testToArray(): void
    {
        ClockMock::register(Event::class);

        $event = new Event();

        $expected = [
            'event_id' => (string) $event->getId(false),
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'level' => 'error',
            'platform' => 'php',
            'sdk' => [
                'name' => Client::SDK_IDENTIFIER,
                'version' => PrettyVersions::getVersion('sentry/sentry')->getPrettyVersion(),
            ],
            'contexts' => [
                'os' => [
                    'name' => php_uname('s'),
                    'version' => php_uname('r'),
                    'build' => php_uname('v'),
                    'kernel_version' => php_uname('a'),
                ],
                'runtime' => [
                    'name' => 'php',
                    'version' => PHPVersion::parseVersion(),
                ],
            ],
        ];

        $this->assertSame($expected, $event->toArray());
    }

    public function testToArrayMergesCustomContextsWithDefaultContexts(): void
    {
        ClockMock::register(Event::class);

        $event = new Event();
        $event->setContext('foo', ['foo' => 'bar']);
        $event->setContext('bar', ['bar' => 'foo']);
        $event->setContext('runtime', ['baz' => 'baz']);

        $expected = [
            'event_id' => (string) $event->getId(false),
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'level' => 'error',
            'platform' => 'php',
            'sdk' => [
                'name' => Client::SDK_IDENTIFIER,
                'version' => PrettyVersions::getVersion('sentry/sentry')->getPrettyVersion(),
            ],
            'contexts' => [
                'os' => [
                    'name' => php_uname('s'),
                    'version' => php_uname('r'),
                    'build' => php_uname('v'),
                    'kernel_version' => php_uname('a'),
                ],
                'runtime' => [
                    'baz' => 'baz',
                ],
                'foo' => [
                    'foo' => 'bar',
                ],
                'bar' => [
                    'bar' => 'foo',
                ],
            ],
        ];

        $this->assertSame($expected, $event->toArray());
    }

    /**
     * @dataProvider toArrayWithMessageDataProvider
     */
    public function testToArrayWithMessage(array $setMessageArguments, $expectedValue): void
    {
        $event = new Event();

        \call_user_func_array([$event, 'setMessage'], $setMessageArguments);

        $data = $event->toArray();

        $this->assertArrayHasKey('message', $data);
        $this->assertSame($expectedValue, $data['message']);
    }

    public function toArrayWithMessageDataProvider(): array
    {
        return [
            [
                [
                    'foo bar',
                ],
                'foo bar',
            ],
            [
                [
                    'foo %s',
                    [
                        'bar',
                    ],
                ],
                [
                    'message' => 'foo %s',
                    'params' => [
                        'bar',
                    ],
                    'formatted' => 'foo bar',
                ],
            ],
            [
                [
                    'foo %bar',
                    [
                        '%bar' => 'baz',
                    ],
                    'foo baz',
                ],
                [
                    'message' => 'foo %bar',
                    'params' => [
                        '%bar' => 'baz',
                    ],
                    'formatted' => 'foo baz',
                ],
            ],
        ];
    }

    public function testToArrayWithBreadcrumbs(): void
    {
        $breadcrumbs = [
            new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'foo'),
            new Breadcrumb(Breadcrumb::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'bar'),
        ];

        $event = new Event();
        $event->setBreadcrumb($breadcrumbs);

        $this->assertSame($breadcrumbs, $event->getBreadcrumbs());

        $data = $event->toArray();

        $this->assertArrayHasKey('breadcrumbs', $data);
        $this->assertSame($breadcrumbs, $data['breadcrumbs']['values']);
    }

    /**
     * @dataProvider getMessageDataProvider
     */
    public function testGetMessage(array $setMessageArguments, array $expectedValue): void
    {
        $event = new Event();

        \call_user_func_array([$event, 'setMessage'], $setMessageArguments);

        $this->assertSame($expectedValue['message'], $event->getMessage());
        $this->assertSame($expectedValue['params'], $event->getMessageParams());
        $this->assertSame($expectedValue['formatted'], $event->getMessageFormatted());
    }

    public function getMessageDataProvider(): array
    {
        return [
            [
                [
                    'foo %s',
                    [
                        'bar',
                    ],
                ],
                [
                    'message' => 'foo %s',
                    'params' => [
                        'bar',
                    ],
                    'formatted' => null,
                ],
            ],
            [
                [
                    'foo %bar',
                    [
                        '%bar' => 'baz',
                    ],
                    'foo baz',
                ],
                [
                    'message' => 'foo %bar',
                    'params' => [
                        '%bar' => 'baz',
                    ],
                    'formatted' => 'foo baz',
                ],
            ],
        ];
    }

    /**
     * @dataProvider gettersAndSettersDataProvider
     */
    public function testGettersAndSetters(string $propertyName, $propertyValue, $expectedValue): void
    {
        $getterMethod = 'get' . ucfirst($propertyName);
        $setterMethod = 'set' . ucfirst($propertyName);

        $event = new Event();
        $event->$setterMethod($propertyValue);

        $this->assertEquals($event->$getterMethod(), $propertyValue);
        $this->assertArraySubset($expectedValue, $event->toArray());
    }

    public function gettersAndSettersDataProvider(): array
    {
        return [
            ['sdkIdentifier', 'sentry.sdk.test-identifier', ['sdk' => ['name' => 'sentry.sdk.test-identifier']]],
            ['sdkVersion', '1.2.3', ['sdk' => ['version' => '1.2.3']]],
            ['level', Severity::info(), ['level' => Severity::info()]],
            ['logger', 'ruby', ['logger' => 'ruby']],
            ['transaction', 'foo', ['transaction' => 'foo']],
            ['serverName', 'local.host', ['server_name' => 'local.host']],
            ['release', '0.0.1', ['release' => '0.0.1']],
            ['modules', ['foo' => '0.0.1', 'bar' => '0.0.2'], ['modules' => ['foo' => '0.0.1', 'bar' => '0.0.2']]],
            ['fingerprint', ['foo', 'bar'], ['fingerprint' => ['foo', 'bar']]],
            ['environment', 'foo', ['environment' => 'foo']],
        ];
    }

    public function testSetStacktrace(): void
    {
        $stacktrace = $this->createMock(Stacktrace::class);

        $event = new Event();
        $event->setStacktrace($stacktrace);

        $this->assertSame($stacktrace, $event->getStacktrace());

        $event->setStacktrace(null);

        $this->assertNull($event->getStacktrace());
    }

    public function testEventJsonSerialization(): void
    {
        $event = new Event();

        $encodingOfToArray = json_encode($event->toArray());
        $serializedEvent = json_encode($event);

        $this->assertNotFalse($encodingOfToArray);
        $this->assertNotFalse($serializedEvent);
        $this->assertJsonStringEqualsJsonString($encodingOfToArray, $serializedEvent);
    }
}
