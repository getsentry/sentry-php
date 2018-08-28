<?php

namespace Raven\Tests;

use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidFactory;
use Ramsey\Uuid\UuidFactoryInterface;
use Raven\Breadcrumbs\Breadcrumb;
use Raven\Client;
use Raven\ClientBuilder;
use Raven\ClientInterface;
use Raven\Configuration;
use Raven\Event;

/**
 * @group time-sensitive
 */
class EventTest extends TestCase
{
    const GENERATED_UUID = [
        '500a339f-3ab2-450b-96de-e542adf36ba7',
        '4c981dd6-ad49-46be-9f16-c3b80fd25f05',
    ];

    protected $uuidGeneratorInvokationCount;

    /**
     * @var UuidFactoryInterface
     */
    protected $originalUuidFactory;

    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * @var ClientInterface
     */
    protected $client;

    protected function setUp()
    {
        $this->uuidGeneratorInvokationCount = 0;
        $this->originalUuidFactory = new UuidFactory();
        $this->client = ClientBuilder::create()->getClient();
        $this->configuration = $this->client->getConfig();

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
        $event1 = new Event($this->configuration);
        $event2 = new Event($this->configuration);

        $this->assertEquals(static::GENERATED_UUID[0], $event1->getId()->toString());
        $this->assertEquals(static::GENERATED_UUID[1], $event2->getId()->toString());
    }

    public function testToArray()
    {
        $this->configuration->setRelease('1.2.3-dev');

        $expected = [
            'event_id' => str_replace('-', '', static::GENERATED_UUID[0]),
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'level' => 'error',
            'platform' => 'php',
            'sdk' => [
                'name' => 'sentry-php',
                'version' => Client::VERSION,
            ],
            'server_name' => $this->configuration->getServerName(),
            'release' => $this->configuration->getRelease(),
            'environment' => $this->configuration->getCurrentEnvironment(),
        ];

        $event = new Event($this->configuration);

        $this->assertEquals($expected, $event->toArray());
    }

    public function testToArrayWithMessage()
    {
        $event = Event::create($this->configuration)
            ->withMessage('foo bar');

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

        $event = Event::create($this->configuration)
            ->withMessage('foo %s', ['bar']);

        $data = $event->toArray();

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals($expected, $data['message']);
    }

    public function testToArrayWithBreadcrumbs()
    {
        $breadcrumbs = [
            new Breadcrumb(Client::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'foo'),
            new Breadcrumb(Client::LEVEL_ERROR, Breadcrumb::TYPE_ERROR, 'bar'),
        ];

        $event = new Event($this->configuration);

        foreach ($breadcrumbs as $breadcrumb) {
            $event = $event->withBreadcrumb($breadcrumb);
        }

        $this->assertSame($breadcrumbs, $event->getBreadcrumbs());

        $data = $event->toArray();

        $this->assertArrayHasKey('breadcrumbs', $data);
        $this->assertSame($breadcrumbs, $data['breadcrumbs']);
    }

    /**
     * @dataProvider gettersAndSettersDataProvider
     */
    public function testGettersAndSetters($propertyName, $propertyValue, $expectedValue)
    {
        $getterMethod = 'get' . ucfirst($propertyName);
        $setterMethod = 'with' . ucfirst($propertyName);

        $event = new Event($this->configuration);
        $newEvent = \call_user_func([$event, $setterMethod], \call_user_func([$event, $getterMethod]));

        $this->assertSame($event, $newEvent);

        $newEvent = \call_user_func([$event, $setterMethod], $propertyValue);

        $this->assertNotSame($event, $newEvent);

        $value = \call_user_func([$event, $getterMethod]);
        $newValue = \call_user_func([$newEvent, $getterMethod]);

        $this->assertNotSame($value, $newValue);
        $this->assertSame($newValue, $propertyValue);

        $data = $newEvent->toArray();

        $this->assertArraySubset($expectedValue, $data);
    }

    public function gettersAndSettersDataProvider()
    {
        return [
            ['level', 'info', ['level' => 'info']],
            ['logger', 'ruby', ['logger' => 'ruby']],
            ['culprit', 'foo', ['culprit' => 'foo']],
            ['serverName', 'local.host', ['server_name' => 'local.host']],
            ['release', '0.0.1', ['release' => '0.0.1']],
            ['modules', ['foo' => '0.0.1', 'bar' => '0.0.2'], ['modules' => ['foo' => '0.0.1', 'bar' => '0.0.2']]],
            ['extraContext', ['foo' => 'bar'], ['extra' => ['foo' => 'bar']]],
            ['tagsContext', ['bar' => 'foo'], ['tags' => ['bar' => 'foo']]],
            ['userContext', ['bar' => 'baz'], ['user' => ['bar' => 'baz']]],
            ['serverOsContext', ['foobar' => 'barfoo'], ['contexts' => ['os' => ['foobar' => 'barfoo']]]],
            ['runtimeContext', ['barfoo' => 'foobar'], ['contexts' => ['runtime' => ['barfoo' => 'foobar']]]],
            ['fingerprint', ['foo', 'bar'], ['fingerprint' => ['foo', 'bar']]],
            ['environment', 'foo', ['environment' => 'foo']],
        ];
    }

    public function testEventJsonSerialization()
    {
        $event = new Event($this->configuration);

        $this->assertJsonStringEqualsJsonString(json_encode($event->toArray()), json_encode($event));
    }
}
