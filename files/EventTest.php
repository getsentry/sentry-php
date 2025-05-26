<?php

declare(strict_types=1);

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Sentry\Event;
use Sentry\Severity;

/**
 * @group time-sensitive
 */
final class EventTest extends TestCase
{
    public function testEventIsGeneratedWithUniqueIdentifier(): void
    {
        $event1 = Event::createEvent();
        $event2 = Event::createEvent();

        $this->assertNotEquals($event1->getId(), $event2->getId());
    }

    /**
     * @dataProvider getMessageDataProvider
     */
    public function testGetMessage(array $setMessageArguments, array $expectedValue): void
    {
        $event = Event::createEvent();

        \call_user_func_array([$event, 'setMessage'], $setMessageArguments);

        $this->assertSame($expectedValue['message'], $event->getMessage());
        $this->assertSame($expectedValue['params'], $event->getMessageParams());
        $this->assertSame($expectedValue['formatted'], $event->getMessageFormatted());
    }

    public static function getMessageDataProvider(): array
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
    public function testGettersAndSetters(string $propertyName, $propertyValue): void
    {
        $getterMethod = 'get' . ucfirst($propertyName);
        $setterMethod = 'set' . ucfirst($propertyName);

        $event = Event::createEvent();
        $event->$setterMethod($propertyValue);

        $this->assertEquals($event->$getterMethod(), $propertyValue);
    }

    public static function gettersAndSettersDataProvider(): array
    {
        return [
            ['sdkIdentifier', 'sentry.sdk.test-identifier'],
            ['sdkVersion', '1.2.3'],
            ['level', Severity::info()],
            ['logger', 'ruby'],
            ['transaction', 'foo'],
            ['serverName', 'local.host'],
            ['release', '0.0.1'],
            ['modules', ['foo' => '0.0.1', 'bar' => '0.0.2']],
            ['fingerprint', ['foo', 'bar']],
            ['environment', 'foo'],
        ];
    }

    public function testSetAndRemoveTag(): void
    {
        $tagName = 'tag';
        $tagValue = 'value';

        $event = Event::createEvent();
        $event->setTag($tagName, $tagValue);

        $this->assertSame([$tagName => $tagValue], $event->getTags());

        $event->removeTag($tagName);

        $this->assertEmpty($event->getTags());
    }

    public function testSetGetSdkMetadata(): void
    {
        $event = Event::createEvent();
        $event->setSdkMetadata('foo', ['bar', 'baz']);

        $this->assertSame(['bar', 'baz'], $event->getSdkMetadata('foo'));
        $this->assertSame(['foo' => ['bar', 'baz']], $event->getSdkMetadata());
    }
}
