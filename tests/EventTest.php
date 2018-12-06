<?php

declare(strict_types=1);

namespace Sentry\Tests;

use Jean85\PrettyVersions;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidFactory;
use Ramsey\Uuid\UuidFactoryInterface;
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
    private const GENERATED_UUID = [
        '4d310518-9e9d-463c-8161-bd46416f7817',
        '431a2537-d1de-49da-80b6-b7861954c9cf',
    ];

    /**
     * @var int
     */
    protected $uuidGeneratorInvokationCount;

    /**
     * @var UuidFactoryInterface
     */
    protected $originalUuidFactory;

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
        $this->uuidGeneratorInvokationCount = 0;
        $this->originalUuidFactory = new UuidFactory();
        $this->client = ClientBuilder::create()->getClient();
        $this->options = $this->client->getOptions();

        /** @var UuidFactoryInterface|MockObject $uuidFactory */
        $uuidFactory = $this->getMockBuilder(UuidFactoryInterface::class)
            ->getMock();

        $uuidFactory->expects($this->any())
            ->method('uuid4')
            ->willReturnCallback(function () {
                $uuid = static::GENERATED_UUID[$this->uuidGeneratorInvokationCount++];

                return $this->originalUuidFactory->fromString($uuid);
            });

        Uuid::setFactory($uuidFactory);
    }

    protected function tearDown(): void
    {
        Uuid::setFactory($this->originalUuidFactory);
    }

    public function testEventIsGeneratedWithUniqueIdentifier(): void
    {
        $event1 = new Event();
        $event2 = new Event();

        $this->assertEquals(str_replace('-', '', static::GENERATED_UUID[0]), $event1->getId());
        $this->assertEquals(str_replace('-', '', static::GENERATED_UUID[1]), $event2->getId());
    }

    public function testToArray(): void
    {
        $this->options->setRelease('1.2.3-dev');
        $sentryPrettyVersion = PrettyVersions::getVersion('sentry/sentry')->getPrettyVersion();

        $expected = [
            'event_id' => str_replace('-', '', static::GENERATED_UUID[0]),
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'level' => 'error',
            'platform' => 'php',
            'sdk' => [
                'name' => Client::SDK_IDENTIFIER,
                'version' => $sentryPrettyVersion,
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

        $event = new Event();

        $this->assertEquals($expected, $event->toArray());
    }

    public function testToArrayWithMessage(): void
    {
        $event = new Event();
        $event->setMessage('foo bar');

        $data = $event->toArray();

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('foo bar', $data['message']);
    }

    public function testToArrayWithMessageWithParams(): void
    {
        $expected = [
            'message' => 'foo %s',
            'params' => ['bar'],
            'formatted' => 'foo bar',
        ];

        $event = new Event();
        $event->setMessage('foo %s', ['bar']);

        $data = $event->toArray();

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals($expected, $data['message']);
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
