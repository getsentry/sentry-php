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

use Http\Client\HttpAsyncClient;
use Http\Message\RequestFactory;
use Http\Mock\Client as MockClient;
use Http\Promise\Promise;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidFactory;
use Raven\Breadcrumbs\ErrorHandler;
use Raven\Client;
use Raven\ClientBuilder;
use Raven\Configuration;
use Raven\Event;
use Raven\Processor\SanitizeDataProcessor;

function simple_function($a = null, $b = null, $c = null)
{
    throw new \RuntimeException('This simple function should fail before reaching this line!');
}

function invalid_encoding()
{
    $fp = fopen(__DIR__ . '/data/binary', 'r');
    simple_function(fread($fp, 64));
    fclose($fp);
}

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

    public function send(&$data)
    {
        if (!$this->config->shouldCapture($data)) {
            return;
        }

        $this->__sent_events[] = $data;
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

class Dummy_Raven_Client_No_Http extends Dummy_Raven_Client
{
    /**
     * @return bool
     */
    public static function isHttpRequest()
    {
        return false;
    }
}

class Dummy_Raven_Client_With_Sync_Override extends \Raven\Client
{
    private static $_test_data = null;

    public static function get_test_data()
    {
        if (null === self::$_test_data) {
            self::$_test_data = '';
            for ($i = 0; $i < 128; ++$i) {
                self::$_test_data .= chr(mt_rand(ord('a'), ord('z')));
            }
        }

        return self::$_test_data;
    }

    public static function test_filename()
    {
        return sys_get_temp_dir() . '/clientraven.tmp';
    }
}

class ClientTest extends TestCase
{
    protected static $_folder = null;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        self::$_folder = sys_get_temp_dir() . '/sentry_server_' . microtime(true);
        mkdir(self::$_folder);
    }

    public static function tearDownAfterClass()
    {
        exec(sprintf('rm -rf %s', escapeshellarg(self::$_folder)));
    }

    public function tearDown()
    {
        parent::tearDown();
        if (file_exists(Dummy_Raven_Client_With_Sync_Override::test_filename())) {
            unlink(Dummy_Raven_Client_With_Sync_Override::test_filename());
        }
    }

    private function create_exception()
    {
        try {
            throw new \Exception('Foo bar');
        } catch (\Exception $ex) {
            return $ex;
        }
    }

    public function testDestructor()
    {
        $waitCalled = false;

        /** @var ResponseInterface|\PHPUnit_Framework_MockObject_MockObject $response */
        $response = $this->getMockBuilder(ResponseInterface::class)
            ->getMock();

        $promise = new PromiseMock($response);
        $promise->then(function (ResponseInterface $response) use (&$waitCalled) {
            $waitCalled = true;

            return $response;
        });

        /** @var HttpAsyncClient|\PHPUnit_Framework_MockObject_MockObject $httpClient */
        $httpClient = $this->getMockBuilder(HttpAsyncClient::class)
            ->getMock();

        $httpClient->expects($this->once())
            ->method('sendAsyncRequest')
            ->willReturn($promise);

        $client = ClientBuilder::create(['server' => 'http://public:secret@example.com/1'])
            ->setHttpClient($httpClient)
            ->getClient();

        $data = ['foo'];

        $this->assertAttributeEmpty('pendingRequests', $client);

        $client->send($data);

        $this->assertAttributeNotEmpty('pendingRequests', $client);
        $this->assertFalse($waitCalled);

        // The destructor should never be called explicitly because it simply
        // does nothing in PHP, but it's the only way to assert that the
        // $waitCalled variable is true because PHPUnit maintains references
        // to all mocks instances
        $client->__destruct();

        $this->assertTrue($waitCalled);
    }

    public function testSeedMiddleware()
    {
        $client = ClientBuilder::create()->getClient();

        $firstMiddleware = $this->getObjectAttribute($client, 'middlewareTip');
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

    public function testCaptureExceptionHandlesOptionsAsSecondArg()
    {
        $client = ClientBuilder::create()->getClient();
        $client->storeErrorsForBulkSend = true;

        $client->captureException($this->create_exception(), ['culprit' => 'test']);

        $this->assertCount(1, $client->pendingEvents);
        $this->assertEquals('test', $client->pendingEvents[0]['culprit']);
    }

    public function testDoesRegisterProcessors()
    {
        $client = ClientBuilder::create(['processors' => [SanitizeDataProcessor::class]])->getClient();

        $this->assertInstanceOf(SanitizeDataProcessor::class, $this->getObjectAttribute($client, 'processors')[0]);
    }

    public function testProcessDoesCallProcessors()
    {
        $data = ['key' => 'value'];

        $processor = $this->getMockBuilder('Processor')
            ->setMethods(['process'])
            ->getMock();

        $processor->expects($this->once())
            ->method('process')
            ->with($data);

        $client = ClientBuilder::create()->getClient();

        $client->setProcessors([$processor]);
        $client->process($data);
    }

    public function testCaptureLastError()
    {
        if (function_exists('error_clear_last')) {
            error_clear_last();
        }

        $client = ClientBuilder::create()->getClient();
        $client->storeErrorsForBulkSend = true;

        $this->assertNull($client->captureLastError());
        $this->assertEmpty($client->pendingEvents);

        /* @var $undefined */
        /* @noinspection PhpExpressionResultUnusedInspection */
        @$undefined;

        $client->captureLastError();

        $this->assertCount(1, $client->pendingEvents);
        $this->assertEquals('Undefined variable: undefined', $client->pendingEvents[0]['exception']['values'][0]['value']);
    }

    public function testGetLastEventID()
    {
        /** @var UuidFactory|\PHPUnit_Framework_MockObject_MockObject $uuidFactory */
        $uuidFactory = $this->createMock(UuidFactory::class);
        $uuidFactory->expects($this->once())
            ->method('uuid4')
            ->willReturn(Uuid::fromString('ddbd643a-5190-4cce-a6ce-3098506f9d33'));

        Uuid::setFactory($uuidFactory);

        $client = ClientBuilder::create()->getClient();
        $client->storeErrorsForBulkSend = true;

        $client->capture(['message' => 'test']);

        $this->assertEquals('ddbd643a51904ccea6ce3098506f9d33', $client->getLastEventID());

        Uuid::setFactory(new UuidFactory());
    }

    public function testCustomTransport()
    {
        $events = [];

        $client = ClientBuilder::create([
            'server' => 'https://public:secret@sentry.example.com/1',
            'install_default_breadcrumb_handlers' => false,
        ])->getClient();

        $client->getConfig()->setTransport(function ($client, $data) use (&$events) {
            $events[] = $data;
        });

        $client->capture(['message' => 'test', 'event_id' => 'abc']);

        $this->assertCount(1, $events);
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

    /**
     * @dataProvider sendWithEncodingDataProvider
     */
    public function testSendWithEncoding($options, $expectedRequest)
    {
        $httpClient = new MockClient();

        $client = ClientBuilder::create($options)
            ->setHttpClient($httpClient)
            ->getClient();

        $data = ['foo bar'];

        $client->send($data);

        $requests = $httpClient->getRequests();

        $this->assertCount(1, $requests);

        $stream = $requests[0]->getBody();
        $stream->rewind();

        $this->assertEquals($expectedRequest['body'], (string) $stream);
        $this->assertArraySubset($expectedRequest['headers'], $requests[0]->getHeaders());
    }

    public function sendWithEncodingDataProvider()
    {
        return [
            [
                [
                    'server' => 'http://public:secret@example.com/1',
                    'encoding' => 'json',
                ],
                [
                    'body' => '["foo bar"]',
                    'headers' => [
                        'Content-Type' => ['application/json'],
                    ],
                ],
            ],
            [
                [
                    'server' => 'http://public:secret@example.com/1',
                    'encoding' => 'gzip',
                ],
                [
                    'body' => 'eJyLVkrLz1dISixSigUAFYQDlg==',
                    'headers' => [
                        'Content-Type' => ['application/octet-stream'],
                    ],
                ],
            ],
        ];
    }

    public function testSendCallback()
    {
        $config = new Configuration([
            'should_capture' => function ($data) {
                $this->assertEquals('test', $data['message']);

                return false;
            },
        ]);

        /** @var HttpAsyncClient|\PHPUnit_Framework_MockObject_MockObject $httpClient */
        $httpClient = $this->getMockBuilder(HttpAsyncClient::class)
            ->getMock();

        /** @var RequestFactory|\PHPUnit_Framework_MockObject_MockObject $requestFactory */
        $requestFactory = $this->getMockBuilder(RequestFactory::class)
            ->getMock();

        $client = new Dummy_Raven_Client($config, $httpClient, $requestFactory);

        $client->captureMessage('test');

        $this->assertEmpty($client->getSentEvents());

        $config->setShouldCapture(function ($data) {
            $this->assertEquals('test', $data['message']);

            return true;
        });

        $client->captureMessage('test');

        $this->assertCount(1, $client->getSentEvents());

        $config->setShouldCapture(function (&$data) {
            unset($data['message']);

            return true;
        });

        $client->captureMessage('test');

        $this->assertCount(2, $client->getSentEvents());
        $this->assertArrayNotHasKey('message', $client->getSentEvents()[1]);
    }

    public function testSanitizeExtra()
    {
        $client = ClientBuilder::create()->getClient();
        $data = ['extra' => [
            'context' => [
                'line' => 1216,
                'stack' => [
                    1, [2], 3,
                ],
            ],
        ]];
        $client->sanitize($data);

        $this->assertEquals(['extra' => [
            'context' => [
                'line' => 1216,
                'stack' => [
                    1, 'Array of length 1', 3,
                ],
            ],
        ]], $data);
    }

    public function testSanitizeObjects()
    {
        $client = ClientBuilder::create(['serialize_all_object' => true])->getClient();
        $clone = ClientBuilder::create()->getClient();
        $data = [
            'extra' => [
                'object' => $clone,
            ],
        ];

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

        $client->sanitize($data);
        ksort($data['extra']['object']);
        foreach ($data['extra']['object'] as $key => &$value) {
            if (is_array($value)) {
                ksort($value);
            }
        }

        $this->assertEquals(['extra' => ['object' => $expected]], $data);
    }

    public function testSanitizeTags()
    {
        $client = ClientBuilder::create()->getClient();
        $data = ['tags' => [
            'foo' => 'bar',
            'baz' => ['biz'],
        ]];
        $client->sanitize($data);

        $this->assertEquals(['tags' => [
            'foo' => 'bar',
            'baz' => 'Array',
        ]], $data);
    }

    public function testSanitizeUser()
    {
        $client = ClientBuilder::create()->getClient();
        $data = ['user' => [
            'email' => 'foo@example.com',
        ]];
        $client->sanitize($data);

        $this->assertEquals(['user' => [
            'email' => 'foo@example.com',
        ]], $data);
    }

    public function testSanitizeRequest()
    {
        $client = ClientBuilder::create()->getClient();
        $data = ['request' => [
            'context' => [
                'line' => 1216,
                'stack' => [
                    1, [2], 3,
                ],
            ],
        ]];
        $client->sanitize($data);

        $this->assertEquals(['request' => [
            'context' => [
                'line' => 1216,
                'stack' => [
                    1, 'Array of length 1', 3,
                ],
            ],
        ]], $data);
    }

    public function testSanitizeContexts()
    {
        $client = ClientBuilder::create()->getClient();
        $data = ['contexts' => [
            'context' => [
                'line' => 1216,
                'stack' => [
                    1, [
                        'foo' => 'bar',
                        'level4' => [['level5', 'level5 a'], 2],
                    ], 3,
                ],
            ],
        ]];
        $client->sanitize($data);

        $this->assertEquals(['contexts' => [
            'context' => [
                'line' => 1216,
                'stack' => [
                    1, [
                        'foo' => 'bar',
                        'level4' => ['Array of length 2', 2],
                    ], 3,
                ],
            ],
        ]], $data);
    }

    /**
     * @covers \Raven\Client::getLastError
     * @covers \Raven\Client::getLastEventId
     * @covers \Raven\Client::getShutdownFunctionHasBeenSet
     */
    public function testGettersAndSetters()
    {
        $client = ClientBuilder::create()->getClient();

        $data = [
            ['lastError', null, null],
            ['lastError', null, 'value'],
            ['lastError', null, mt_rand(100, 999)],
            ['lastEventId', null, mt_rand(100, 999)],
            ['lastEventId', null, 'value'],
            ['shutdownFunctionHasBeenSet', null, true],
            ['shutdownFunctionHasBeenSet', null, false],
        ];
        foreach ($data as &$datum) {
            $this->subTestGettersAndSettersDatum($client, $datum);
        }
    }

    private function subTestGettersAndSettersDatum(\Raven\Client $client, $datum)
    {
        if (3 == count($datum)) {
            list($property_name, $function_name, $value_in) = $datum;
            $value_out = $value_in;
        } else {
            list($property_name, $function_name, $value_in, $value_out) = $datum;
        }
        if (null === $function_name) {
            $function_name = str_replace('_', '', $property_name);
        }

        $method_get_name = 'get' . $function_name;
        $method_set_name = 'set' . $function_name;
        $property = new \ReflectionProperty('\\Raven\\Client', $property_name);
        $property->setAccessible(true);

        if (method_exists($client, $method_set_name)) {
            $setter_output = $client->$method_set_name($value_in);
            if (null !== $setter_output and is_object($setter_output)) {
                // chaining call test
                $this->assertEquals(spl_object_hash($client), spl_object_hash($setter_output));
            }
            $actual_value = $property->getValue($client);
            $this->assertMixedValueAndArray($value_out, $actual_value);
        }

        if (method_exists($client, $method_get_name)) {
            $property->setValue($client, $value_out);
            $reflection = new \ReflectionMethod('\\Raven\Client', $method_get_name);
            if ($reflection->isPublic()) {
                $actual_value = $client->$method_get_name();
                $this->assertMixedValueAndArray($value_out, $actual_value);
            }
        }
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

    public function testOnShutdown()
    {
        $httpClient = new MockClient();

        $client = ClientBuilder::create(['server' => 'http://public:secret@example.com/1'])
            ->setHttpClient($httpClient)
            ->getClient();

        $this->assertEquals(0, count($client->pendingEvents));
        $client->pendingEvents[] = ['foo' => 'bar'];
        $client->sendUnsentErrors();
        $this->assertCount(1, $httpClient->getRequests());
        $this->assertEquals(0, count($client->pendingEvents));

        $client->storeErrorsForBulkSend = true;
        $client->captureMessage('foobar');
        $this->assertEquals(1, count($client->pendingEvents));

        // step 3
        $client->onShutdown();
        $this->assertCount(2, $httpClient->getRequests());
        $this->assertEquals(0, count($client->pendingEvents));
    }

    public function testSendChecksShouldCaptureOption()
    {
        /** @var HttpAsyncClient|\PHPUnit_Framework_MockObject_MockObject $httpClient */
        $httpClient = $this->getMockBuilder(HttpAsyncClient::class)
            ->getMock();

        /** @var RequestFactory|\PHPUnit_Framework_MockObject_MockObject $requestFactory */
        $requestFactory = $this->getMockBuilder(RequestFactory::class)
            ->getMock();

        $httpClient->expects($this->never())
            ->method('sendAsyncRequest');

        $config = new Configuration([
            'server' => 'http://public:secret@example.com/1',
            'install_default_breadcrumb_handlers' => false,
            'should_capture' => function () use (&$shouldCaptureCalled) {
                $shouldCaptureCalled = true;

                return false;
            },
        ]);

        $client = new Client($config, $httpClient, $requestFactory);

        $data = ['foo' => 'bar'];

        $client->send($data);

        $this->assertTrue($shouldCaptureCalled);
    }

    public function testSendFailsWhenNoServerIsConfigured()
    {
        /** @var HttpAsyncClient|\PHPUnit_Framework_MockObject_MockObject $httpClient */
        $httpClient = $this->getMockBuilder(HttpAsyncClient::class)
            ->getMock();

        $httpClient->expects($this->never())
            ->method('sendAsyncRequest');

        /** @var RequestFactory|\PHPUnit_Framework_MockObject_MockObject $requestFactory */
        $requestFactory = $this->getMockBuilder(RequestFactory::class)
            ->getMock();

        $client = new Client(new Configuration(), $httpClient, $requestFactory);
        $data = ['foo' => 'bar'];

        $client->send($data);
    }

    public function test__construct_handlers()
    {
        /** @var HttpAsyncClient|\PHPUnit_Framework_MockObject_MockObject $httpClient */
        $httpClient = $this->getMockBuilder(HttpAsyncClient::class)
            ->getMock();

        /** @var RequestFactory|\PHPUnit_Framework_MockObject_MockObject $requestFactory */
        $requestFactory = $this->getMockBuilder(RequestFactory::class)
            ->getMock();

        foreach ([true, false] as $u1) {
            foreach ([true, false] as $u2) {
                $client = new Dummy_Raven_Client(
                    new Configuration([
                        'install_default_breadcrumb_handlers' => $u1,
                        'install_shutdown_handler' => $u2,
                    ]),
                    $httpClient,
                    $requestFactory
                );

                $this->assertEquals($u1, $client->dummy_breadcrumbs_handlers_has_set);
                $this->assertEquals($u2, $client->dummy_shutdown_handlers_has_set);
            }
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

class PromiseMock implements Promise
{
    private $result;

    private $state;

    private $onFullfilledCallbacks = [];

    private $onRejectedCallbacks = [];

    public function __construct($result, $state = self::FULFILLED)
    {
        $this->result = $result;
        $this->state = $state;
    }

    public function then(callable $onFulfilled = null, callable $onRejected = null)
    {
        if (null !== $onFulfilled) {
            $this->onFullfilledCallbacks[] = $onFulfilled;
        }

        if (null !== $onRejected) {
            $this->onRejectedCallbacks[] = $onRejected;
        }

        return $this;
    }

    public function getState()
    {
        return $this->state;
    }

    public function wait($unwrap = true)
    {
        switch ($this->state) {
            case self::FULFILLED:
                foreach ($this->onFullfilledCallbacks as $onFullfilledCallback) {
                    $onFullfilledCallback($this->result);
                }

                break;
            case self::REJECTED:
                foreach ($this->onRejectedCallbacks as $onRejectedCallback) {
                    $onRejectedCallback($this->result);
                }

                break;
        }

        if ($unwrap) {
            return $this->result;
        }

        return null;
    }
}
