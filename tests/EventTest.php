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
    public function testGettersAndSetters(string $propertyName, $propertyValue): void
    {
        $getterMethod = 'get' . ucfirst($propertyName);
        $setterMethod = 'set' . ucfirst($propertyName);

        $event = Event::createEvent();
        $event->$setterMethod($propertyValue);

        $this->assertEquals($event->$getterMethod(), $propertyValue);
    }

    public function gettersAndSettersDataProvider(): array
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
}
