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
use Raven\Breadcrumbs\ErrorHandler;
use Raven\Client;
use Raven\ClientBuilder;
use Raven\Configuration;
use Raven\Event;
use Raven\Transport\TransportInterface;

// XXX: Is there a better way to stub the client?
class Dummy_Raven_Client extends \Raven\Client
{
    private $__sent_events = [];
    public $dummy_breadcrumbs_handlers_has_set = false;
    public $dummy_shutdown_handlers_has_set = false;

    public function getSentEvents()
    {
        return $this->__sent_events;
    }

    public function send(Event $event)
    {
        if (!$this->config->shouldCapture($event)) {
            return;
        }

        $this->__sent_events[] = $event;
    }

    public static function isHttpRequest()
    {
        return true;
    }

    // short circuit breadcrumbs
    public function registerDefaultBreadcrumbHandlers()
    {
        $this->dummy_breadcrumbs_handlers_has_set = true;
    }

    public function registerShutdownFunction()
    {
        $this->dummy_shutdown_handlers_has_set = true;
    }
}

class ClientTest extends TestCase
{
    public function testMiddlewareStackIsSeeded()
    {
        $client = ClientBuilder::create()->getClient();

        $firstMiddleware = $this->getObjectAttribute($client, 'middlewareStackTip');
        $lastMiddleware = null;

        $client->addMiddleware(function (Event $event, callable $next) use (&$lastMiddleware) {
            $lastMiddleware = $next;

            return $event;
        });

        $client->capture([]);

        $this->assertSame($firstMiddleware, $lastMiddleware);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Middleware can't be added once the stack is dequeuing
     */
    public function testAddMiddlewareThrowsWhileStackIsRunning()
    {
        $client = ClientBuilder::create()->getClient();

        $client->addMiddleware(function (Event $event) use ($client) {
            $client->addMiddleware(function () {
                // Do nothing, it's just a middleware added to trigger the exception
            });

            return $event;
        });

        $client->capture([]);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Middleware must return an instance of the "Raven\Event" class.
     */
    public function testMiddlewareThrowsWhenBadValueIsReturned()
    {
        $client = ClientBuilder::create()->getClient();

        $client->addMiddleware(function () {
            // Do nothing, it's just a middleware added to trigger the exception
        });

        $client->capture([]);
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

        error_clear_last();
    }

    public function testCaptureLastErrorDoesNothingWhenThereIsNoError()
    {
        /** @var Client|\PHPUnit_Framework_MockObject_MockObject $client */
        $client = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['captureException'])
            ->getMock();

        $client->expects($this->never())
            ->method('capture');

        error_clear_last();

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

        $this->assertEquals('ddbd643a51904ccea6ce3098506f9d33', $client->getLastEventID());
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

    /**
     * @expectedException \Raven\Exception
     * @expectedExceptionMessage Raven\Client->install() must only be called once
     */
    public function testCannotInstallTwice()
    {
        $client = ClientBuilder::create()->getClient();

        $client->install();
        $client->install();
    }

    public function testSanitizeExtra()
    {
        $client = ClientBuilder::create()->getClient();

        $event = new Event($client->getConfig());
        $event = $event->withExtraContext([
            'foo' => [
                'line' => 1216,
                'stack' => [
                    1, [2], 3,
                ],
            ],
        ]);

        $event = $client->sanitize($event);

        $this->assertEquals([
            'foo' => [
                'line' => 1216,
                'stack' => [
                    1, 'Array of length 1', 3,
                ],
            ],
        ], $event->getExtraContext());
    }

    public function testSanitizeObjects()
    {
        $client = ClientBuilder::create(['serialize_all_object' => true])->getClient();
        $clone = ClientBuilder::create()->getClient();

        $event = new Event($client->getConfig());
        $event = $event->withExtraContext([
            'object' => $clone,
        ]);

        $reflection = new \ReflectionClass($clone);
        $expected = [];
        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $value = $property->getValue($clone);
            if (is_array($value)) {
                $property->setValue($clone, []);
                $expected[$property->getName()] = [];
                continue;
            }
            if (!is_object($value)) {
                $expected[$property->getName()] = $value;
                continue;
            }

            $new_value = [];
            $reflection2 = new \ReflectionClass($value);
            foreach ($reflection2->getProperties(\ReflectionProperty::IS_PUBLIC) as $property2) {
                $sub_value = $property2->getValue($value);
                if (is_array($sub_value)) {
                    $new_value[$property2->getName()] = 'Array of length ' . count($sub_value);
                    continue;
                }
                if (is_object($sub_value)) {
                    $sub_value = null;
                    $property2->setValue($value, null);
                }
                $new_value[$property2->getName()] = $sub_value;
            }

            ksort($new_value);
            $expected[$property->getName()] = $new_value;
            unset($reflection2, $property2, $sub_value, $new_value);
        }
        unset($reflection, $property, $value, $reflection, $clone);
        ksort($expected);

        $event = $client->sanitize($event);
        /*ksort($data['extra']['object']);
        foreach ($data['extra']['object'] as $key => &$value) {
            if (is_array($value)) {
                ksort($value);
            }
        }*/

        $this->assertEquals(['object' => $expected], $event->getExtraContext());
    }

    public function testSanitizeTags()
    {
        $client = ClientBuilder::create()->getClient();

        $event = new Event($client->getConfig());
        $event = $event->withTagsContext([
            'foo' => 'bar',
            'baz' => ['biz'],
        ]);

        $event = $client->sanitize($event);

        $this->assertEquals([
            'foo' => 'bar',
            'baz' => 'Array',
        ], $event->getTagsContext());
    }

    public function testSanitizeUser()
    {
        $client = ClientBuilder::create()->getClient();

        $event = new Event($client->getConfig());
        $event = $event->withUserContext([
            'email' => 'foo@example.com',
        ]);

        $client->sanitize($event);

        $this->assertEquals(['email' => 'foo@example.com'], $event->getUserContext());
    }

    public function testSanitizeRequest()
    {
        $client = ClientBuilder::create()->getClient();

        $event = new Event($client->getConfig());
        $event = $event->withRequest([
            'context' => [
                'line' => 1216,
                'stack' => [
                    1, [2], 3,
                ],
            ],
        ]);

        $event = $client->sanitize($event);

        $this->assertArraySubset([
            'context' => [
                'line' => 1216,
                'stack' => [
                    1, 'Array of length 1', 3,
                ],
            ],
        ], $event->getRequest());
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

    public function testRegisterDefaultBreadcrumbHandlers()
    {
        if (isset($_ENV['HHVM']) and (1 == $_ENV['HHVM'])) {
            $this->markTestSkipped('HHVM stacktrace behaviour');

            return;
        }

        $previous = set_error_handler([$this, 'stabClosureErrorHandler'], E_USER_NOTICE);

        ClientBuilder::create()->getClient();

        $this->_closure_called = false;

        trigger_error('foobar', E_USER_NOTICE);
        set_error_handler($previous, E_ALL);

        $this->assertTrue($this->_closure_called);

        if (isset($this->_debug_backtrace[1]['function']) && ($this->_debug_backtrace[1]['function'] == 'call_user_func') && version_compare(PHP_VERSION, '7.0', '>=')) {
            $offset = 2;
        } elseif (version_compare(PHP_VERSION, '7.0', '>=')) {
            $offset = 1;
        } else {
            $offset = 2;
        }

        $this->assertEquals(ErrorHandler::class, $this->_debug_backtrace[$offset]['class']);
    }

    private $_closure_called = false;

    public function stabClosureVoid()
    {
        $this->_closure_called = true;
    }

    public function stabClosureNull()
    {
        $this->_closure_called = true;

        return null;
    }

    public function stabClosureFalse()
    {
        $this->_closure_called = true;

        return false;
    }

    private $_debug_backtrace = [];

    public function stabClosureErrorHandler($code, $message, $file = '', $line = 0, $context = [])
    {
        $this->_closure_called = true;
        $this->_debug_backtrace = debug_backtrace();

        return true;
    }

    public function testSendChecksShouldCaptureOption()
    {
        $shouldCaptureCalled = false;

        $client = ClientBuilder::create([
            'server' => 'http://public:secret@example.com/1',
            'install_default_breadcrumb_handlers' => false,
            'should_capture' => function () use (&$shouldCaptureCalled) {
                $shouldCaptureCalled = true;

                return false;
            },
        ])->getClient();

        $client->capture([]);

        $this->assertTrue($shouldCaptureCalled);
    }

    public function test__construct_handlers()
    {
        /** @var TransportInterface|\PHPUnit_Framework_MockObject_MockObject $transport */
        $transport = $this->createMock(TransportInterface::class);

        foreach ([true, false] as $u1) {
            $client = new Dummy_Raven_Client(
                new Configuration([
                    'install_default_breadcrumb_handlers' => $u1,
                ]),
                $transport
            );

            $this->assertEquals($u1, $client->dummy_breadcrumbs_handlers_has_set);
        }
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
        $this->assertTrue($client->getReprSerializer()->getAllObjectSerialize());

        $client->setAllObjectSerialize(false);

        $this->assertFalse($client->getSerializer()->getAllObjectSerialize());
        $this->assertFalse($client->getReprSerializer()->getAllObjectSerialize());
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
        $reflection = new \ReflectionProperty($client, 'recorder');
        $reflection->setAccessible(true);
        $this->assertNotEmpty(iterator_to_array($reflection->getValue($client)));

        $client->clearBreadcrumbs();
        $this->assertEmpty(iterator_to_array($reflection->getValue($client)));
    }
}
