<?php

namespace Sentry\Tests;

use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidFactory;
use Ramsey\Uuid\UuidFactoryInterface;
use Sentry\Breadcrumbs\Breadcrumb;
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
class EventTest extends TestCase
{
    const GENERATED_UUID = [
        '500a339f3ab2450b96dee542adf36ba7',
        '4c981dd6ad4946be9f16c3b80fd25f05',
    ];

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

    protected function setUp()
    {
        $this->uuidGeneratorInvokationCount = 0;
        $this->originalUuidFactory = new UuidFactory();
        $this->client = ClientBuilder::create()->getClient();
        $this->options = $this->client->getOptions();

        /** @var UuidFactoryInterface|\PHPUnit_Framework_MockObject_MockObject $uuidFactory */
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

    protected function tearDown()
    {
        Uuid::setFactory($this->originalUuidFactory);
    }

    public function testEventIsGeneratedWithUniqueIdentifier()
    {
        $event1 = new Event();
        $event2 = new Event();

        $this->assertEquals(static::GENERATED_UUID[0], $event1->getId());
        $this->assertEquals(static::GENERATED_UUID[1], $event2->getId());
    }

    public function testToArray()
    {
        $this->options->setRelease('1.2.3-dev');

        $expected = [
            'event_id' => str_replace('-', '', static::GENERATED_UUID[0]),
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'level' => 'error',
            'platform' => 'php',
            'sdk' => [
                'name' => 'sentry-php',
                'version' => Client::VERSION,
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

    public function testToArrayWithMessage()
    {
        $event = new Event();
        $event->setMessage('foo bar');

        $data = $event->toArray();

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('foo bar', $data['message']);
    }

    public function testToArrayWithMessageWithParams()
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

    public function testToArrayWithBreadcrumbs()
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
        $this->assertSame($breadcrumbs, $data['breadcrumbs']);
    }

    public function testGetServerOsContext()
    {
        $event = new Event();

        $this->assertInstanceOf(ServerOsContext::class, $event->getServerOsContext());
    }

    public function testGetRuntimeContext()
    {
        $event = new Event();

        $this->assertInstanceOf(RuntimeContext::class, $event->getRuntimeContext());
    }

    public function testGetUserContext()
    {
        $event = new Event();

        $this->assertInstanceOf(Context::class, $event->getUserContext());
    }

    public function testGetExtraContext()
    {
        $event = new Event();

        $this->assertInstanceOf(Context::class, $event->getExtraContext());
    }

    public function getTagsContext()
    {
        $event = new Event();

        $this->assertInstanceOf(TagsContext::class, $event->getTagsContext());
    }

    /**
     * @dataProvider gettersAndSettersDataProvider
     */
    public function testGettersAndSetters($propertyName, $propertyValue, $expectedValue)
    {
        $getterMethod = 'get' . ucfirst($propertyName);
        $setterMethod = 'set' . ucfirst($propertyName);

        $event = new Event();
        $event->$setterMethod($propertyValue);

        $this->assertEquals($event->$getterMethod(), $propertyValue);
        $this->assertArraySubset($expectedValue, $event->toArray());
    }

    public function gettersAndSettersDataProvider()
    {
        return [
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

    public function testEventJsonSerialization()
    {
        $event = new Event();

        $encodingOfToArray = json_encode($event->toArray());
        $serializedEvent = json_encode($event);

        $this->assertNotFalse($encodingOfToArray);
        $this->assertNotFalse($serializedEvent);
        $this->assertJsonStringEqualsJsonString($encodingOfToArray, $serializedEvent);
    }
}
