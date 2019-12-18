<?php

declare(strict_types=1);

namespace Sentry\Tests;

use Jean85\PrettyVersions;
use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumb;
use Sentry\Client;
use Sentry\ClientBuilder;
use Sentry\ClientInterface;
use Sentry\Context\Context;
use Sentry\Context\RuntimeContext;
use Sentry\Context\ServerOsContext;
use Sentry\Context\TagsContext;
use Sentry\Event;
use Sentry\Options;
use Sentry\Severity;
use Sentry\Util\PHPVersion;

/**
 * @group time-sensitive
 */
final class EventTest extends TestCase
{
    /**
     * @var Options
     */
    protected $options;

    /**
     * @var ClientInterface
     */
    protected $client;

    protected function setUp(): void
    {
        $this->client = ClientBuilder::create()->getClient();
        $this->options = $this->client->getOptions();
    }

    public function testEventIsGeneratedWithUniqueIdentifier(): void
    {
        $event1 = new Event();
        $event2 = new Event();

        $this->assertRegExp('/^[a-z0-9]{32}$/', $event1->getId());
        $this->assertRegExp('/^[a-z0-9]{32}$/', $event2->getId());
        $this->assertNotEquals($event1->getId(), $event2->getId());
    }

    public function testToArray(): void
    {
        $event = new Event();

        $expected = [
            'event_id' => $event->getId(),
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

        $this->assertEquals($expected, $event->toArray());
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

    public function testGetServerOsContext(): void
    {
        $event = new Event();

        $this->assertInstanceOf(ServerOsContext::class, $event->getServerOsContext());
    }

    public function testGetRuntimeContext(): void
    {
        $event = new Event();

        $this->assertInstanceOf(RuntimeContext::class, $event->getRuntimeContext());
    }

    public function testGetUserContext(): void
    {
        $event = new Event();

        $this->assertInstanceOf(Context::class, $event->getUserContext());
    }

    public function testGetExtraContext(): void
    {
        $event = new Event();

        $this->assertInstanceOf(Context::class, $event->getExtraContext());
    }

    public function getTagsContext(): void
    {
        $event = new Event();

        $this->assertInstanceOf(TagsContext::class, $event->getTagsContext());
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
