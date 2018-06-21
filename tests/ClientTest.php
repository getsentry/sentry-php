<?php
/*
 * This file is part of Raven.
 *
 * (c) Sentry Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Raven\Tests;

use Http\Mock\Client as MockClient;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidFactory;
use Raven\Client;
use Raven\ClientBuilder;
use Raven\Context\Context;
use Raven\Context\RuntimeContext;
use Raven\Context\ServerOsContext;
use Raven\Context\TagsContext;
use Raven\Event;
use Raven\Middleware\MiddlewareStack;
use Raven\Processor\ProcessorInterface;
use Raven\Processor\ProcessorRegistry;
use Raven\ReprSerializer;
use Raven\Serializer;
use Raven\Tests\Fixtures\classes\CarelessException;
use Raven\Transport\TransportInterface;

class ClientTest extends TestCase
{
    public function testConstructorInitializesTransactionStack()
    {
        $_SERVER['PATH_INFO'] = '/foo';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $client = ClientBuilder::create()->getClient();
        $transactionStack = $client->getTransactionStack();

        $this->assertNotEmpty($transactionStack);
        $this->assertEquals('/foo', $transactionStack->peek());
    }

    public function testConstructorInitializesTransactionStackInCli()
    {
        $client = ClientBuilder::create()->getClient();

        $this->assertEmpty($client->getTransactionStack());
    }

    public function testGetTransactionStack()
    {
        $client = ClientBuilder::create()->getClient();

        $this->assertAttributeSame($client->getTransactionStack(), 'transactionStack', $client);
    }

    public function testAddMiddleware()
    {
        $middleware = function () {};

        /** @var MiddlewareStack|\PHPUnit_Framework_MockObject_MockObject $middlewareStack */
        $middlewareStack = $this->createMock(MiddlewareStack::class);
        $middlewareStack->expects($this->once())
            ->method('addMiddleware')
            ->with($middleware, -10);

        $client = ClientBuilder::create()->getClient();

        $reflectionProperty = new \ReflectionProperty($client, 'middlewareStack');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($client, $middlewareStack);
        $reflectionProperty->setAccessible(false);

        $client->addMiddleware($middleware, -10);
    }

    public function testRemoveMiddleware()
    {
        $middleware = function () {};

        /** @var MiddlewareStack|\PHPUnit_Framework_MockObject_MockObject $middlewareStack */
        $middlewareStack = $this->createMock(MiddlewareStack::class);
        $middlewareStack->expects($this->once())
            ->method('removeMiddleware')
            ->with($middleware);

        $client = ClientBuilder::create()->getClient();

        $reflectionProperty = new \ReflectionProperty($client, 'middlewareStack');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($client, $middlewareStack);
        $reflectionProperty->setAccessible(false);

        $client->removeMiddleware($middleware);
    }

    public function testAddProcessor()
    {
        /** @var ProcessorInterface|\PHPUnit_Framework_MockObject_MockObject $processor */
        $processor = $this->createMock(ProcessorInterface::class);

        $processorRegistry = $this->createMock(ProcessorRegistry::class);
        $processorRegistry->expects($this->once())
            ->method('addProcessor')
            ->with($processor, -10);

        $client = ClientBuilder::create()->getClient();

        $reflectionProperty = new \ReflectionProperty($client, 'processorRegistry');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($client, $processorRegistry);
        $reflectionProperty->setAccessible(false);

        $client->addProcessor($processor, -10);
    }

    public function testRemoveProcessor()
    {
        /** @var ProcessorInterface|\PHPUnit_Framework_MockObject_MockObject $processor */
        $processor = $this->createMock(ProcessorInterface::class);

        $processorRegistry = $this->createMock(ProcessorRegistry::class);
        $processorRegistry->expects($this->once())
            ->method('removeProcessor')
            ->with($processor);

        $client = ClientBuilder::create()->getClient();

        $reflectionProperty = new \ReflectionProperty($client, 'processorRegistry');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($client, $processorRegistry);
        $reflectionProperty->setAccessible(false);

        $client->removeProcessor($processor);
    }

    public function testCaptureMessage()
    {
        /** @var Client|\PHPUnit_Framework_MockObject_MockObject $client */
        $client = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['captureMessage'])
            ->getMock();

        $client->expects($this->once())
            ->method('capture')
            ->with([
                'message' => 'foo',
                'message_params' => ['bar'],
                'foo' => 'bar',
            ]);

        $client->captureMessage('foo', ['bar'], ['foo' => 'bar']);
    }

    public function testCaptureException()
    {
        $exception = new \Exception();

        /** @var Client|\PHPUnit_Framework_MockObject_MockObject $client */
        $client = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['captureException'])
            ->getMock();

        $client->expects($this->once())
            ->method('capture')
            ->with([
                'exception' => $exception,
                'foo' => 'bar',
            ]);

        $client->captureException(new \Exception(), ['foo' => 'bar']);
    }

    public function testCaptureLastError()
    {
        /** @var Client|\PHPUnit_Framework_MockObject_MockObject $client */
        $client = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['captureLastError'])
            ->getMock();

        $client->expects($this->once())
            ->method('captureException')
            ->with($this->logicalAnd(
                $this->isInstanceOf(\ErrorException::class),
                $this->attributeEqualTo('message', 'foo'),
                $this->attributeEqualTo('code', 0),
                $this->attributeEqualTo('severity', E_USER_NOTICE),
                $this->attributeEqualTo('file', __FILE__),
                $this->attributeEqualTo('line', __LINE__ + 3)
            ));

        @trigger_error('foo', E_USER_NOTICE);

        $client->captureLastError();

        $this->clearLastError();
    }

    public function testCaptureLastErrorDoesNothingWhenThereIsNoError()
    {
        /** @var Client|\PHPUnit_Framework_MockObject_MockObject $client */
        $client = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['captureLastError'])
            ->getMock();

        $client->expects($this->never())
            ->method('captureException');

        $this->clearLastError();

        $client->captureLastError();
    }

    public function testCapture()
    {
        /** @var TransportInterface|\PHPUnit_Framework_MockObject_MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
            ->method('send');

        $client = ClientBuilder::create(['server' => 'http://public:secret@example.com/1'])
            ->setTransport($transport)
            ->getClient();

        $inputData = [
            'culprit' => 'foo bar',
            'level' => Client::LEVEL_DEBUG,
            'logger' => 'foo',
            'tags_context' => ['foo', 'bar'],
            'extra_context' => ['foo' => 'bar'],
            'user_context' => ['bar' => 'foo'],
        ];

        $eventId = $client->capture($inputData);

        $event = $client->getLastEvent();

        $this->assertEquals(str_replace('-', '', $event->getId()->toString()), $eventId);
        $this->assertEquals($inputData['culprit'], $event->getCulprit());
        $this->assertEquals($inputData['level'], $event->getLevel());
        $this->assertEquals($inputData['logger'], $event->getLogger());
        $this->assertEquals($inputData['tags_context'], $event->getTagsContext());
        $this->assertEquals($inputData['extra_context'], $event->getExtraContext());
        $this->assertEquals($inputData['user_context'], $event->getUserContext());
    }

    public function testGetLastEvent()
    {
        $lastEvent = null;

        $client = ClientBuilder::create()->getClient();
        $client->addMiddleware(function (Event $event) use (&$lastEvent) {
            $lastEvent = $event;

            return $event;
        });

        $client->capture(['message' => 'foo']);

        $this->assertSame($lastEvent, $client->getLastEvent());
    }

    /**
     * @group legacy
     *
     * @expectedDeprecation The Raven\Client::getLastEventId() method is deprecated since version 2.0. Use getLastEvent() instead.
     */
    public function testGetLastEventId()
    {
        /** @var UuidFactory|\PHPUnit_Framework_MockObject_MockObject $uuidFactory */
        $uuidFactory = $this->createMock(UuidFactory::class);
        $uuidFactory->expects($this->once())
            ->method('uuid4')
            ->willReturn(Uuid::fromString('ddbd643a-5190-4cce-a6ce-3098506f9d33'));

        Uuid::setFactory($uuidFactory);

        $client = ClientBuilder::create()->getClient();

        $client->capture(['message' => 'test']);

        Uuid::setFactory(new UuidFactory());

        $this->assertEquals('ddbd643a51904ccea6ce3098506f9d33', $client->getLastEventId());
    }

    public function testGetUserContext()
    {
        $client = ClientBuilder::create()->getClient();

        $this->assertInstanceOf(Context::class, $client->getUserContext());
    }

    public function testGetTagsContext()
    {
        $client = ClientBuilder::create()->getClient();

        $this->assertInstanceOf(TagsContext::class, $client->getTagsContext());
    }

    public function testGetExtraContext()
    {
        $client = ClientBuilder::create()->getClient();

        $this->assertInstanceOf(Context::class, $client->getExtraContext());
    }

    public function testGetRuntimeContext()
    {
        $client = ClientBuilder::create()->getClient();

        $this->assertInstanceOf(RuntimeContext::class, $client->getRuntimeContext());
    }

    public function testGetServerOsContext()
    {
        $client = ClientBuilder::create()->getClient();

        $this->assertInstanceOf(ServerOsContext::class, $client->getServerOsContext());
    }

    public function testAppPathLinux()
    {
        $client = ClientBuilder::create(['project_root' => '/foo/bar'])->getClient();

        $this->assertEquals('/foo/bar/', $client->getConfig()->getProjectRoot());

        $client->getConfig()->setProjectRoot('/foo/baz/');

        $this->assertEquals('/foo/baz/', $client->getConfig()->getProjectRoot());
    }

    public function testAppPathWindows()
    {
        $client = ClientBuilder::create(['project_root' => 'C:\\foo\\bar\\'])->getClient();

        $this->assertEquals('C:\\foo\\bar\\', $client->getConfig()->getProjectRoot());
    }

    private function assertMixedValueAndArray($expected_value, $actual_value)
    {
        if (null === $expected_value) {
            $this->assertNull($actual_value);
        } elseif (true === $expected_value) {
            $this->assertTrue($actual_value);
        } elseif (false === $expected_value) {
            $this->assertFalse($actual_value);
        } elseif (is_string($expected_value) or is_int($expected_value) or is_float($expected_value)) {
            $this->assertEquals($expected_value, $actual_value);
        } elseif (is_array($expected_value)) {
            $this->assertInternalType('array', $actual_value);
            $this->assertEquals(count($expected_value), count($actual_value));
            foreach ($expected_value as $key => $value) {
                $this->assertArrayHasKey($key, $actual_value);
                $this->assertMixedValueAndArray($value, $actual_value[$key]);
            }
        } elseif (is_callable($expected_value) or is_object($expected_value)) {
            $this->assertEquals(spl_object_hash($expected_value), spl_object_hash($actual_value));
        }
    }

    /**
     * @covers \Raven\Client::translateSeverity
     * @covers \Raven\Client::registerSeverityMap
     */
    public function testTranslateSeverity()
    {
        $reflection = new \ReflectionProperty(Client::class, 'severityMap');
        $reflection->setAccessible(true);
        $client = ClientBuilder::create()->getClient();

        $predefined = [E_ERROR, E_WARNING, E_PARSE, E_NOTICE, E_CORE_ERROR, E_CORE_WARNING,
                       E_COMPILE_ERROR, E_COMPILE_WARNING, E_USER_ERROR, E_USER_WARNING,
                       E_USER_NOTICE, E_STRICT, E_RECOVERABLE_ERROR, ];
        $predefined[] = E_DEPRECATED;
        $predefined[] = E_USER_DEPRECATED;
        $predefined_values = ['debug', 'info', 'warning', 'warning', 'error', 'fatal'];

        // step 1
        foreach ($predefined as &$key) {
            $this->assertContains($client->translateSeverity($key), $predefined_values);
        }
        $this->assertEquals('error', $client->translateSeverity(123456));
        // step 2
        $client->registerSeverityMap([]);
        $this->assertMixedValueAndArray([], $reflection->getValue($client));
        foreach ($predefined as &$key) {
            $this->assertContains($client->translateSeverity($key), $predefined_values);
        }
        $this->assertEquals('error', $client->translateSeverity(123456));
        $this->assertEquals('error', $client->translateSeverity(123456));
        // step 3
        $client->registerSeverityMap([123456 => 'foo']);
        $this->assertMixedValueAndArray([123456 => 'foo'], $reflection->getValue($client));
        foreach ($predefined as &$key) {
            $this->assertContains($client->translateSeverity($key), $predefined_values);
        }
        $this->assertEquals('foo', $client->translateSeverity(123456));
        $this->assertEquals('error', $client->translateSeverity(123457));
        // step 4
        $client->registerSeverityMap([E_USER_ERROR => 'bar']);
        $this->assertEquals('bar', $client->translateSeverity(E_USER_ERROR));
        $this->assertEquals('error', $client->translateSeverity(123456));
        $this->assertEquals('error', $client->translateSeverity(123457));
        // step 5
        $client->registerSeverityMap([E_USER_ERROR => 'bar', 123456 => 'foo']);
        $this->assertEquals('bar', $client->translateSeverity(E_USER_ERROR));
        $this->assertEquals('foo', $client->translateSeverity(123456));
        $this->assertEquals('error', $client->translateSeverity(123457));
    }

    public function testSendChecksShouldCaptureOption()
    {
        $shouldCaptureCalled = false;

        $client = ClientBuilder::create([
            'server' => 'http://public:secret@example.com/1',
            'should_capture' => function () use (&$shouldCaptureCalled) {
                $shouldCaptureCalled = true;

                return false;
            },
        ])->getClient();

        $client->capture([]);

        $this->assertTrue($shouldCaptureCalled);
    }

    /**
     * @dataProvider sampleRateAbsoluteDataProvider
     */
    public function testSampleRateAbsolute($options)
    {
        $httpClient = new MockClient();

        $client = ClientBuilder::create($options)
            ->setHttpClient($httpClient)
            ->getClient();

        for ($i = 0; $i < 10; ++$i) {
            $client->captureMessage('foobar');
        }

        switch ($options['sample_rate']) {
            case 0:
                $this->assertEmpty($httpClient->getRequests());
                break;
            case 1:
                $this->assertNotEmpty($httpClient->getRequests());
                break;
        }
    }

    public function sampleRateAbsoluteDataProvider()
    {
        return [
            [
                [
                    'server' => 'http://public:secret@example.com/1',
                    'sample_rate' => 0,
                ],
            ],
            [
                [
                    'server' => 'http://public:secret@example.com/1',
                    'sample_rate' => 1,
                ],
            ],
        ];
    }

    public function testSetAllObjectSerialize()
    {
        $client = ClientBuilder::create()->getClient();

        $client->setAllObjectSerialize(true);

        $this->assertTrue($client->getSerializer()->getAllObjectSerialize());
        $this->assertTrue($client->getRepresentationSerializer()->getAllObjectSerialize());

        $client->setAllObjectSerialize(false);

        $this->assertFalse($client->getSerializer()->getAllObjectSerialize());
        $this->assertFalse($client->getRepresentationSerializer()->getAllObjectSerialize());
    }

    public function testClearBreadcrumb()
    {
        $client = ClientBuilder::create()->getClient();
        $client->leaveBreadcrumb(
            new \Raven\Breadcrumbs\Breadcrumb(
                'warning', \Raven\Breadcrumbs\Breadcrumb::TYPE_ERROR, 'error_reporting', 'message', [
                    'code' => 127,
                    'line' => 10,
                    'file' => '/tmp/delme.php',
                ]
            )
        );
        $reflection = new \ReflectionProperty($client, 'breadcrumbRecorder');
        $reflection->setAccessible(true);

        $this->assertNotEmpty(iterator_to_array($reflection->getValue($client)));

        $client->clearBreadcrumbs();

        $this->assertEmpty(iterator_to_array($reflection->getValue($client)));
    }

    public function testSetSerializer()
    {
        $client = ClientBuilder::create()->getClient();
        $serializer = $this->prophesize(Serializer::class)->reveal();

        $client->setSerializer($serializer);

        $this->assertSame($serializer, $client->getSerializer());
    }

    public function testSetReprSerializer()
    {
        $client = ClientBuilder::create()->getClient();
        $serializer = $this->prophesize(ReprSerializer::class)->reveal();

        $client->setRepresentationSerializer($serializer);

        $this->assertSame($serializer, $client->getRepresentationSerializer());
    }

    public function testHandlingExceptionThrowingAnException()
    {
        $client = ClientBuilder::create()->getClient();
        $client->captureException($this->createCarelessExceptionWithStacktrace());
        $event = $client->getLastEvent();
        // Make sure the exception is of the careless exception and not the exception thrown inside
        // the __set method of that exception caused by setting the event_id on the exception instance
        $this->assertSame(CarelessException::class, $event->getException()['values'][0]['type']);
    }

    private function createCarelessExceptionWithStacktrace()
    {
        try {
            throw new CarelessException('Foo bar');
        } catch (\Exception $ex) {
            return $ex;
        }
    }

    /**
     * @see https://github.com/symfony/polyfill/blob/52332f49d18c413699d2dccf465234356f8e0b2c/src/Php70/Php70.php#L52-L61
     */
    private function clearLastError()
    {
        $handler = function () { return false; };

        set_error_handler($handler);
        @trigger_error('');
        restore_error_handler();
    }
}
