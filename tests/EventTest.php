<?php

namespace Raven\Tests;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidFactory;
use Ramsey\Uuid\UuidFactoryInterface;
use Raven\Breadcrumbs\Breadcrumb;
use Raven\Client;
use Raven\ClientBuilder;
use Raven\Configuration;
use Raven\Event;
use Raven\Stacktrace;

/**
 * @group time-sensitive
 */
class EventTest extends \PHPUnit_Framework_TestCase
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
     * @var UuidFactoryInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $uuidFactory;

    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * @var Client
     */
    protected $client;

    protected function setUp()
    {
        $this->uuidGeneratorInvokationCount = 0;
        $this->originalUuidFactory = Uuid::getFactory();
        $this->client = ClientBuilder::create()->getClient();
        $this->configuration = $this->client->getConfig();

        $this->uuidFactory = $this->getMockBuilder(UuidFactoryInterface::class)
            ->getMock();

        $this->uuidFactory->expects($this->any())
            ->method('uuid4')
            ->willReturnCallback(function () {
                $uuid = static::GENERATED_UUID[$this->uuidGeneratorInvokationCount++];

                return $this->originalUuidFactory->fromString($uuid);
            });

        Uuid::setFactory($this->uuidFactory);
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
        $event = new Event($this->configuration);
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

        $event = new Event($this->configuration);
        $event->setMessage('foo %s', ['bar']);

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
            $event->addBreadcrumb($breadcrumb);
        }

        $this->assertSame($breadcrumbs, $event->getBreadcrumbs());

        $data = $event->toArray();

        $this->assertArrayHasKey('breadcrumbs', $data);
        $this->assertSame($breadcrumbs, $data['breadcrumbs']);
    }

    /**
     * @dataProvider gettersAndSettersDataProvider
     */
    public function testGettersAndSetters($propertyName, $propertyValue, $serializedPropertyName)
    {
        $getterMethod = 'get' . ucfirst($propertyName);
        $setterMethod = 'set' . ucfirst($propertyName);

        $event = new Event($this->configuration);

        call_user_func([$event, $setterMethod], $propertyValue);

        $expectedValue = call_user_func([$event, $getterMethod]);

        $this->assertEquals($expectedValue, $propertyValue);

        $data = $event->toArray();

        $this->assertArrayHasKey($serializedPropertyName, $data);
        $this->assertEquals($propertyValue, $data[$serializedPropertyName]);
    }

    public function gettersAndSettersDataProvider()
    {
        return [
            ['level', 'info', 'level'],
            ['logger', 'ruby', 'logger'],
            ['culprit', '', 'culprit'],
            ['serverName', 'local.host', 'server_name'],
            ['release', '0.0.1', 'release'],
            ['modules', ['foo' => '0.0.1', 'bar' => '0.0.2'], 'modules'],
            ['extraContext', ['foo' => 'bar'], 'extra'],
            ['tagsContext', ['foo' => 'bar'], 'tags'],
            ['userContext', ['foo' => 'bar'], 'user'],
            ['serverOsContext', ['foo' => 'bar'], 'server_os'],
            ['runtimeContext', ['foo' => 'bar'], 'runtime'],
            //['checksum', 'foo', 'checksum'],
            ['fingerprint', ['foo', 'bar'], 'fingerprint'],
            ['environment', 'foo', 'environment'],
        ];
    }

    /**
     * @expectedException \Raven\Exception\InvalidArgumentException
     * @expectedExceptionMessage The $throwable argument must be an instance of either \Throwable or \Exception.
     */
    public function testCreateFromPHPThrowableThrowsOnInvalidArgument()
    {
        /** @noinspection PhpParamsInspection */
        Event::createFromPHPThrowable($this->client, new \stdClass());
    }

    public function testCreateFromPHPThrowable()
    {
        $event = Event::createFromPHPThrowable($this->client, new \Exception('foo bar'));

        $this->assertEquals('foo bar', $event->getMessage());
        $this->assertEquals(Client::LEVEL_ERROR, $event->getLevel());

        $frames = $event->getStacktrace()->getFrames();
        $lastFrame = $frames[count($frames) - 1];

        $this->assertEquals(__FILE__, $lastFrame['filename']);
        $this->assertEquals(__LINE__ - 9, $lastFrame['lineno']);
        $this->assertEquals(__METHOD__, $lastFrame['function']);
        $this->assertInstanceOf(Stacktrace::class, $event->getStacktrace());
    }

    public function testCreateFromPHPError()
    {
    }

    public function testEventJsonSerialization()
    {
        $event = new Event($this->configuration);

        $this->assertJsonStringEqualsJsonString(json_encode($event->toArray()), json_encode($event));
    }
}
