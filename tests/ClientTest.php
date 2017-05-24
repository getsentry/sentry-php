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

use Raven\Client;
use Raven\Serializer;

function simple_function($a = null, $b = null, $c = null)
{
    throw new \RuntimeException('This simple function should fail before reaching this line!');
}

function invalid_encoding()
{
    $fp = fopen(__DIR__ . '/../../data/binary', 'r');
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
        if (is_callable($this->send_callback) && call_user_func_array($this->send_callback, [&$data]) === false) {
            // if send_callback returns falsely, end native send
            return;
        }
        $this->__sent_events[] = $data;
    }

    public static function is_http_request()
    {
        return true;
    }

    public static function get_auth_header($timestamp, $client, $api_key, $secret_key)
    {
        return parent::get_auth_header($timestamp, $client, $api_key, $secret_key);
    }

    public function get_http_data()
    {
        return parent::get_http_data();
    }

    public function get_user_data()
    {
        return parent::get_user_data();
    }

    public function buildCurlCommand($url, $data, $headers)
    {
        return parent::buildCurlCommand($url, $data, $headers);
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

    /**
     * Expose the current url method to test it
     *
     * @return string
     */
    public function test_get_current_url()
    {
        return $this->get_current_url();
    }

    public function encode(&$data)
    {
        return parent::encode($data);
    }

    public function process(&$data)
    {
        parent::process($data);
    }
}

class Dummy_Raven_Client_With_Overrided_Direct_Send extends \Raven\Client
{
    public $_send_http_asynchronous_curl_exec_called = false;
    public $_send_http_synchronous = false;
    public $_set_url;
    public $_set_data;
    public $_set_headers;
    public static $_close_curl_resource_called = false;

    public function send_http_asynchronous_curl_exec($url, $data, $headers)
    {
        $this->_send_http_asynchronous_curl_exec_called = true;
        $this->_set_url = $url;
        $this->_set_data = $data;
        $this->_set_headers = $headers;
    }

    public function send_http_synchronous($url, $data, $headers)
    {
        $this->_send_http_synchronous = true;
        $this->_set_url = $url;
        $this->_set_data = $data;
        $this->_set_headers = $headers;
    }

    public function get_curl_options()
    {
        $options = parent::get_curl_options();

        return $options;
    }

    public function get_curl_handler()
    {
        return $this->_curl_handler;
    }

    public function set_curl_handler(\Raven\CurlHandler $value)
    {
        $this->_curl_handler = $value;
    }

    public function close_curl_resource()
    {
        parent::close_curl_resource();
        self::$_close_curl_resource_called = true;
    }
}

class Dummy_Raven_Client_No_Http extends Dummy_Raven_Client
{
    /**
     * @return bool
     */
    public static function is_http_request()
    {
        return false;
    }
}

class Dummy_Raven_Client_With_Sync_Override extends \Raven\Client
{
    private static $_test_data = null;

    public static function get_test_data()
    {
        if (is_null(self::$_test_data)) {
            self::$_test_data = '';
            for ($i = 0; $i < 128; $i++) {
                self::$_test_data .= chr(mt_rand(ord('a'), ord('z')));
            }
        }

        return self::$_test_data;
    }

    public static function test_filename()
    {
        return sys_get_temp_dir().'/clientraven.tmp';
    }

    protected function buildCurlCommand($url, $data, $headers)
    {
        return 'echo '.escapeshellarg(self::get_test_data()).' > '.self::test_filename();
    }
}

class Dummy_Raven_CurlHandler extends \Raven\CurlHandler
{
    public $_set_url;
    public $_set_data;
    public $_set_headers;
    public $_enqueue_called = false;
    public $_join_called = false;

    public function __construct($options = [], $join_timeout = 5)
    {
        parent::__construct($options, $join_timeout);
    }

    public function enqueue($url, $data = null, $headers = [])
    {
        $this->_enqueue_called = true;
        $this->_set_url = $url;
        $this->_set_data = $data;
        $this->_set_headers = $headers;

        return 0;
    }

    public function join($timeout = null)
    {
        $this->_join_called = true;
    }
}

class Raven_Tests_ClientTest extends \PHPUnit_Framework_TestCase
{
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

    private function create_chained_exception()
    {
        try {
            throw new \Exception('Foo bar');
        } catch (\Exception $ex) {
            try {
                throw new \Exception('Child exc', 0, $ex);
            } catch (\Exception $ex2) {
                return $ex2;
            }
        }
    }

    public function testParseDSNHttp()
    {
        $result = \Raven\Client::ParseDSN('http://public:secret@example.com/1');

        $this->assertEquals(1, $result['project']);
        $this->assertEquals('http://example.com/api/1/store/', $result['server']);
        $this->assertEquals('public', $result['public_key']);
        $this->assertEquals('secret', $result['secret_key']);
    }

    public function testParseDSNHttps()
    {
        $result = \Raven\Client::ParseDSN('https://public:secret@example.com/1');

        $this->assertEquals(1, $result['project']);
        $this->assertEquals('https://example.com/api/1/store/', $result['server']);
        $this->assertEquals('public', $result['public_key']);
        $this->assertEquals('secret', $result['secret_key']);
    }

    public function testParseDSNPath()
    {
        $result = \Raven\Client::ParseDSN('http://public:secret@example.com/app/1');

        $this->assertEquals(1, $result['project']);
        $this->assertEquals('http://example.com/app/api/1/store/', $result['server']);
        $this->assertEquals('public', $result['public_key']);
        $this->assertEquals('secret', $result['secret_key']);
    }

    public function testParseDSNPort()
    {
        $result = \Raven\Client::ParseDSN('http://public:secret@example.com:9000/app/1');

        $this->assertEquals(1, $result['project']);
        $this->assertEquals('http://example.com:9000/app/api/1/store/', $result['server']);
        $this->assertEquals('public', $result['public_key']);
        $this->assertEquals('secret', $result['secret_key']);
    }

    public function testParseDSNInvalidScheme()
    {
        try {
            \Raven\Client::ParseDSN('gopher://public:secret@/1');
            $this->fail();
        } catch (\Exception $e) {
            return;
        }
    }

    public function testParseDSNMissingNetloc()
    {
        try {
            \Raven\Client::ParseDSN('http://public:secret@/1');
            $this->fail();
        } catch (\Exception $e) {
            return;
        }
    }

    public function testParseDSNMissingProject()
    {
        try {
            \Raven\Client::ParseDSN('http://public:secret@example.com');
            $this->fail();
        } catch (\Exception $e) {
            return;
        }
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testParseDSNMissingPublicKey()
    {
        \Raven\Client::ParseDSN('http://:secret@example.com/1');
    }
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testParseDSNMissingSecretKey()
    {
        \Raven\Client::ParseDSN('http://public@example.com/1');
    }

    /**
     * @covers \Raven\Client::__construct
     */
    public function testDsnFirstArgument()
    {
        $client = new Dummy_Raven_Client('http://public:secret@example.com/1');

        $this->assertAttributeEquals(1, 'project', $client);
        $this->assertAttributeEquals('http://example.com/api/1/store/', 'server', $client);
        $this->assertAttributeEquals('public', 'public_key', $client);
        $this->assertAttributeEquals('secret', 'secret_key', $client);
    }

    /**
     * @covers \Raven\Client::__construct
     */
    public function testDsnFirstArgumentWithOptions()
    {
        $client = new Dummy_Raven_Client('http://public:secret@example.com/1', [
            'site' => 'foo',
        ]);

        $this->assertAttributeEquals(1, 'project', $client);
        $this->assertAttributeEquals('http://example.com/api/1/store/', 'server', $client);
        $this->assertAttributeEquals('public', 'public_key', $client);
        $this->assertAttributeEquals('secret', 'secret_key', $client);
        $this->assertAttributeEquals('foo', 'site', $client);
    }

    /**
     * @covers \Raven\Client::__construct
     */
    public function testOptionsFirstArgument()
    {
        $client = new Dummy_Raven_Client([
            'server' => 'http://example.com/api/1/store/',
            'project' => 1,
        ]);

        $this->assertAttributeEquals('http://example.com/api/1/store/', 'server', $client);
    }

    /**
     * @covers \Raven\Client::__construct
     */
    public function testDsnInOptionsFirstArg()
    {
        $client = new Dummy_Raven_Client([
            'dsn' => 'http://public:secret@example.com/1',
        ]);

        $this->assertAttributeEquals(1, 'project', $client);
        $this->assertAttributeEquals('http://example.com/api/1/store/', 'server', $client);
        $this->assertAttributeEquals('public', 'public_key', $client);
        $this->assertAttributeEquals('secret', 'secret_key', $client);
    }

    /**
     * @covers \Raven\Client::__construct
     */
    public function testDsnInOptionsSecondArg()
    {
        $client = new Dummy_Raven_Client(null, [
            'dsn' => 'http://public:secret@example.com/1',
        ]);

        $this->assertAttributeEquals(1, 'project', $client);
        $this->assertAttributeEquals('http://example.com/api/1/store/', 'server', $client);
        $this->assertAttributeEquals('public', 'public_key', $client);
        $this->assertAttributeEquals('secret', 'secret_key', $client);
    }

    /**
     * @covers \Raven\Client::__construct
     */
    public function testOptionsFirstArgumentWithOptions()
    {
        $client = new Dummy_Raven_Client([
            'server' => 'http://example.com/api/1/store/',
            'project' => 1,
        ], [
            'site' => 'foo',
        ]);

        $this->assertAttributeEquals('http://example.com/api/1/store/', 'server', $client);
        $this->assertAttributeEquals('foo', 'site', $client);
    }

    /**
     * @covers \Raven\Client::captureMessage
     */
    public function testOptionsExtraData()
    {
        $client = new Dummy_Raven_Client(['extra' => ['foo' => 'bar']]);

        $client->captureMessage('Test Message %s', ['foo']);
        $events = $client->getSentEvents();
        $this->assertEquals(1, count($events));
        $event = array_pop($events);
        $this->assertEquals('bar', $event['extra']['foo']);
    }

    /**
     * @covers \Raven\Client::captureMessage
     */
    public function testOptionsExtraDataWithNull()
    {
        $client = new Dummy_Raven_Client(['extra' => ['foo' => 'bar']]);

        $client->captureMessage('Test Message %s', ['foo'], null);
        $events = $client->getSentEvents();
        $this->assertEquals(1, count($events));
        $event = array_pop($events);
        $this->assertEquals('bar', $event['extra']['foo']);
    }

    /**
     * @covers \Raven\Client::captureMessage
     */
    public function testEmptyExtraData()
    {
        $client = new Dummy_Raven_Client(['extra' => []]);

        $client->captureMessage('Test Message %s', ['foo']);
        $events = $client->getSentEvents();
        $this->assertEquals(1, count($events));
        $event = array_pop($events);
        $this->assertEquals(array_key_exists('extra', $event), false);
    }

    /**
     * @covers \Raven\Client::captureMessage
     */
    public function testCaptureMessageDoesHandleUninterpolatedMessage()
    {
        $client = new Dummy_Raven_Client();

        $client->captureMessage('Test Message %s');
        $events = $client->getSentEvents();
        $this->assertEquals(1, count($events));
        $event = array_pop($events);
        $this->assertEquals('Test Message %s', $event['message']);
    }

    /**
     * @covers \Raven\Client::captureMessage
     */
    public function testCaptureMessageDoesHandleInterpolatedMessage()
    {
        $client = new Dummy_Raven_Client();

        $client->captureMessage('Test Message %s', ['foo']);
        $events = $client->getSentEvents();
        $this->assertEquals(1, count($events));
        $event = array_pop($events);
        $this->assertEquals('Test Message foo', $event['message']);
    }

    /**
     * @covers \Raven\Client::captureMessage
     */
    public function testCaptureMessageDoesHandleInterpolatedMessageWithRelease()
    {
        $client = new Dummy_Raven_Client();
        $client->setRelease(20160909144742);

        $this->assertEquals(20160909144742, $client->getRelease());

        $client->captureMessage('Test Message %s', ['foo']);
        $events = $client->getSentEvents();
        $this->assertEquals(1, count($events));
        $event = array_pop($events);
        $this->assertEquals(20160909144742, $event['release']);
        $this->assertEquals('Test Message foo', $event['message']);
    }

    /**
     * @covers \Raven\Client::captureMessage
     */
    public function testCaptureMessageSetsInterface()
    {
        $client = new Dummy_Raven_Client();

        $client->captureMessage('Test Message %s', ['foo']);
        $events = $client->getSentEvents();
        $this->assertEquals(1, count($events));
        $event = array_pop($events);
        $this->assertEquals([
            'message' => 'Test Message %s',
            'params' => ['foo'],
            'formatted' => 'Test Message foo',
        ], $event['sentry.interfaces.Message']);
        $this->assertEquals('Test Message foo', $event['message']);
    }

    /**
     * @covers \Raven\Client::captureMessage
     */
    public function testCaptureMessageHandlesOptionsAsThirdArg()
    {
        $client = new Dummy_Raven_Client();

        $client->captureMessage('Test Message %s', ['foo'], [
            'level' => Dummy_Raven_Client::WARNING,
            'extra' => ['foo' => 'bar']
        ]);
        $events = $client->getSentEvents();
        $this->assertEquals(1, count($events));
        $event = array_pop($events);
        $this->assertEquals(Dummy_Raven_Client::WARNING, $event['level']);
        $this->assertEquals('bar', $event['extra']['foo']);
        $this->assertEquals('Test Message foo', $event['message']);
    }

    /**
     * @covers \Raven\Client::captureMessage
     */
    public function testCaptureMessageHandlesLevelAsThirdArg()
    {
        $client = new Dummy_Raven_Client();

        $client->captureMessage('Test Message %s', ['foo'], Dummy_Raven_Client::WARNING);
        $events = $client->getSentEvents();
        $this->assertEquals(1, count($events));
        $event = array_pop($events);
        $this->assertEquals(Dummy_Raven_Client::WARNING, $event['level']);
        $this->assertEquals('Test Message foo', $event['message']);
    }

    /**
     * @covers \Raven\Client::captureException
     */
    public function testCaptureExceptionSetsInterfaces()
    {
        # TODO: it'd be nice if we could mock the stacktrace extraction function here
        $client = new Dummy_Raven_Client();
        $ex = $this->create_exception();
        $client->captureException($ex);

        $events = $client->getSentEvents();
        $this->assertEquals(1, count($events));
        $event = array_pop($events);

        $exc = $event['exception'];
        $this->assertEquals(1, count($exc['values']));
        $this->assertEquals('Foo bar', $exc['values'][0]['value']);
        $this->assertEquals('Exception', $exc['values'][0]['type']);

        $this->assertFalse(empty($exc['values'][0]['stacktrace']['frames']));
        $frames = $exc['values'][0]['stacktrace']['frames'];
        $frame = $frames[count($frames) - 1];
        $this->assertTrue($frame['lineno'] > 0);
        $this->assertEquals('Raven\Tests\Raven_Tests_ClientTest::create_exception', $frame['function']);
        $this->assertFalse(isset($frame['vars']));
        $this->assertEquals('            throw new \Exception(\'Foo bar\');', $frame['context_line']);
        $this->assertFalse(empty($frame['pre_context']));
        $this->assertFalse(empty($frame['post_context']));
    }

    /**
     * @covers \Raven\Client::captureException
     */
    public function testCaptureExceptionChainedException()
    {
        # TODO: it'd be nice if we could mock the stacktrace extraction function here
        $client = new Dummy_Raven_Client();
        $ex = $this->create_chained_exception();
        $client->captureException($ex);

        $events = $client->getSentEvents();
        $this->assertEquals(1, count($events));
        $event = array_pop($events);

        $exc = $event['exception'];
        $this->assertEquals(2, count($exc['values']));
        $this->assertEquals('Foo bar', $exc['values'][0]['value']);
        $this->assertEquals('Child exc', $exc['values'][1]['value']);
    }

    /**
     * @covers \Raven\Client::captureException
     */
    public function testCaptureExceptionDifferentLevelsInChainedExceptionsBug()
    {
        $client = new Dummy_Raven_Client();
        $e1 = new \ErrorException('First', 0, E_DEPRECATED);
        $e2 = new \ErrorException('Second', 0, E_NOTICE, __FILE__, __LINE__, $e1);
        $e3 = new \ErrorException('Third', 0, E_ERROR, __FILE__, __LINE__, $e2);

        $client->captureException($e1);
        $client->captureException($e2);
        $client->captureException($e3);
        $events = $client->getSentEvents();

        $event = array_pop($events);
        $this->assertEquals(Dummy_Raven_Client::ERROR, $event['level']);

        $event = array_pop($events);
        $this->assertEquals(Dummy_Raven_Client::INFO, $event['level']);

        $event = array_pop($events);
        $this->assertEquals(Dummy_Raven_Client::WARNING, $event['level']);
    }

    /**
     * @covers \Raven\Client::captureException
     */
    public function testCaptureExceptionHandlesOptionsAsSecondArg()
    {
        $client = new Dummy_Raven_Client();
        $ex = $this->create_exception();
        $client->captureException($ex, ['culprit' => 'test']);
        $events = $client->getSentEvents();
        $this->assertEquals(1, count($events));
        $event = array_pop($events);
        $this->assertEquals('test', $event['culprit']);
    }

    /**
     * @covers \Raven\Client::captureException
     */
    public function testCaptureExceptionHandlesExcludeOption()
    {
        $client = new Dummy_Raven_Client([
            'exclude' => ['Exception'],
        ]);
        $ex = $this->create_exception();
        $client->captureException($ex, 'test');
        $events = $client->getSentEvents();
        $this->assertEquals(0, count($events));
    }

    public function testCaptureExceptionInvalidUTF8()
    {
        $client = new Dummy_Raven_Client();
        try {
            invalid_encoding();
        } catch (\Exception $ex) {
            $client->captureException($ex);
        }
        $events = $client->getSentEvents();
        $this->assertEquals(1, count($events));

        // if this fails to encode it returns false
        $message = $client->encode($events[0]);
        $this->assertNotFalse($message, $client->getLastError());
    }

    /**
     * @covers \Raven\Client::__construct
     */
    public function testDoesRegisterProcessors()
    {
        $client = new Dummy_Raven_Client([
            'processors' => ['\\Raven\\Processor\\SanitizeDataProcessor'],
        ]);

        $processors = new \ReflectionProperty($client, 'processors');
        $processors->setAccessible(true);

        $this->assertAttributeCount(1, 'processors', $client);
        $this->assertInstanceOf('\\Raven\\Processor\\SanitizeDataProcessor', $processors->getValue($client)[0]);
    }

    public function testProcessDoesCallProcessors()
    {
        $data = ["key"=>"value"];

        $processor = $this->getMockBuilder('Processor')
                          ->setMethods(['process'])
                          ->getMock();
        $processor->expects($this->once())
               ->method('process')
               ->with($data);

        $client = new Dummy_Raven_Client();
        $processors = new \ReflectionProperty($client, 'processors');
        $processors->setAccessible(true);
        $processors->setValue($client, array_merge($processors->getValue($client), [$processor]));
        $client->process($data);
    }

    /**
     * @covers \Raven\Client::__construct
     * @covers \Raven\Client::getDefaultProcessors
     */
    public function testDefaultProcessorsAreUsed()
    {
        $client = new Dummy_Raven_Client();
        $defaults = Dummy_Raven_Client::getDefaultProcessors();

        $this->assertAttributeCount(count($defaults), 'processors', $client);
    }

    /**
     * @covers \Raven\Client::getDefaultProcessors
     */
    public function testDefaultProcessorsContainSanitizeDataProcessor()
    {
        $this->assertContains('\\Raven\\Processor\\SanitizeDataProcessor', Dummy_Raven_Client::getDefaultProcessors());
    }

    /**
     * @covers \Raven\Client::__construct
     * @covers \Raven\Client::get_default_data
     */
    public function testGetDefaultData()
    {
        $client = new Dummy_Raven_Client();
        $transaction = new \ReflectionProperty($client, 'transaction');
        $transaction->setAccessible(true);
        $transaction->getValue($client)->push('test');
        $project = new \ReflectionProperty($client, 'project');
        $project->setAccessible(true);
        $name = new \ReflectionProperty($client, 'name');
        $name->setAccessible(true);
        $site = new \ReflectionProperty($client, 'site');
        $site->setAccessible(true);
        $tags = new \ReflectionProperty($client, 'tags');
        $tags->setAccessible(true);
        $logger = new \ReflectionProperty($client, 'logger');
        $logger->setAccessible(true);
        $expected = [
            'platform' => 'php',
            'project' => $project->getValue($client),
            'server_name' => $name->getValue($client),
            'site' => $site->getValue($client),
            'logger' => $logger->getValue($client),
            'tags' => $tags->getValue($client),
            'sdk' => [
                'name' => 'sentry-php',
                'version' => $client::VERSION,
            ],
            'culprit' => 'test',
        ];
        $this->assertEquals($expected, $client->get_default_data());
    }

    /**
     * @backupGlobals
     * @covers \Raven\Client::get_http_data
     */
    public function testGetHttpData()
    {
        $_SERVER = [
            'REDIRECT_STATUS'     => '200',
            'CONTENT_TYPE'        => 'text/xml',
            'CONTENT_LENGTH'      => '99',
            'HTTP_HOST'           => 'getsentry.com',
            'HTTP_ACCEPT'         => 'text/html',
            'HTTP_ACCEPT_CHARSET' => 'utf-8',
            'HTTP_COOKIE'         => 'cupcake: strawberry',
            'SERVER_PORT'         => '443',
            'SERVER_PROTOCOL'     => 'HTTP/1.1',
            'REQUEST_METHOD'      => 'PATCH',
            'QUERY_STRING'        => 'q=bitch&l=en',
            'REQUEST_URI'         => '/welcome/',
            'SCRIPT_NAME'         => '/index.php',
        ];
        $_POST = [
            'stamp' => '1c',
        ];
        $_COOKIE = [
            'donut' => 'chocolat',
        ];

        $expected = [
            'request' => [
                'method' => 'PATCH',
                'url' => 'https://getsentry.com/welcome/',
                'query_string' => 'q=bitch&l=en',
                'data' => [
                    'stamp'           => '1c',
                ],
                'cookies' => [
                    'donut'           => 'chocolat',
                ],
                'headers' => [
                    'Host'            => 'getsentry.com',
                    'Accept'          => 'text/html',
                    'Accept-Charset'  => 'utf-8',
                    'Cookie'          => 'cupcake: strawberry',
                    'Content-Type'    => 'text/xml',
                    'Content-Length'  => '99',
                ],
            ]
        ];

        $client = new Dummy_Raven_Client();
        $this->assertEquals($expected, $client->get_http_data());
    }

    /**
     * @covers \Raven\Client::user_context
     * @covers \Raven\Client::get_user_data
     */
    public function testGetUserDataWithSetUser()
    {
        $client = new Dummy_Raven_Client();

        $id = 'unique_id';
        $email = 'foo@example.com';
        $client->user_context(['id' => $id, 'email' => $email, 'username' => 'my_user', ]);

        $expected = [
            'user' => [
                'id' => $id,
                'username' => 'my_user',
                'email' => $email,
            ]
        ];

        $this->assertEquals($expected, $client->get_user_data());
    }

    /**
     * @covers \Raven\Client::get_user_data
     */
    public function testGetUserDataWithNoUser()
    {
        $client = new Dummy_Raven_Client();

        $expected = [
            'user' => [
                'id' => session_id(),
            ]
        ];
        $this->assertEquals($expected, $client->get_user_data());
    }

    /**
     * @covers \Raven\Client::get_auth_header
     */
    public function testGet_Auth_Header()
    {
        $client = new Dummy_Raven_Client();

        $clientstring = 'sentry-php/test';
        $timestamp = '1234341324.340000';

        $expected = "Sentry sentry_timestamp={$timestamp}, sentry_client={$clientstring}, " .
                    "sentry_version=" . Dummy_Raven_Client::PROTOCOL . ", " .
                    "sentry_key=publickey, sentry_secret=secretkey";

        $this->assertEquals($expected, $client->get_auth_header($timestamp, 'sentry-php/test', 'publickey', 'secretkey'));
    }

    /**
     * @covers \Raven\Client::getAuthHeader
     */
    public function testGetAuthHeader()
    {
        $client = new Dummy_Raven_Client();
        $ts1 = microtime(true);
        $getAuthHeader = new \ReflectionMethod($client, 'getAuthHeader');
        $getAuthHeader->setAccessible(true);
        $header = $getAuthHeader->invoke($client);
        $ts2 = microtime(true);
        $this->assertEquals(1, preg_match('/sentry_timestamp=([0-9.]+)/', $header, $a));
        $this->assertRegExp('/^[0-9]+(\\.[0-9]+)?$/', $a[1]);
        $this->assertGreaterThanOrEqual($ts1, (double)$a[1]);
        $this->assertLessThanOrEqual($ts2, (double)$a[1]);
    }

    /**
     * @covers \Raven\Client::captureMessage
     */
    public function testCaptureMessageWithUserContext()
    {
        $client = new Dummy_Raven_Client();

        $client->user_context(['email' => 'foo@example.com']);

        $client->captureMessage('test');
        $events = $client->getSentEvents();
        $this->assertEquals(1, count($events));
        $event = array_pop($events);
        $this->assertEquals([
            'email' => 'foo@example.com',
        ], $event['user']);
    }

    /**
     * @covers \Raven\Client::captureMessage
     */
    public function testCaptureMessageWithUnserializableUserData()
    {
        $client = new Dummy_Raven_Client();

        $client->user_context([
            'email' => 'foo@example.com',
            'data' => [
                'error' => new \Exception('test'),
            ]
        ]);

        $client->captureMessage('test');
        $events = $client->getSentEvents();
        // we're just asserting that this goes off without a hitch
        $this->assertEquals(1, count($events));
        array_pop($events);
    }

    /**
     * @covers \Raven\Client::captureMessage
     * @covers \Raven\Client::tags_context
     */
    public function testCaptureMessageWithTagsContext()
    {
        $client = new Dummy_Raven_Client();

        $client->tags_context(['foo' => 'bar']);
        $client->tags_context(['biz' => 'boz']);
        $client->tags_context(['biz' => 'baz']);

        $client->captureMessage('test');
        $events = $client->getSentEvents();
        $this->assertEquals(1, count($events));
        $event = array_pop($events);
        $this->assertEquals([
            'foo' => 'bar',
            'biz' => 'baz',
        ], $event['tags']);
    }

    /**
     * @covers \Raven\Client::captureMessage
     * @covers \Raven\Client::extra_context
     */
    public function testCaptureMessageWithExtraContext()
    {
        $client = new Dummy_Raven_Client();

        $client->extra_context(['foo' => 'bar']);
        $client->extra_context(['biz' => 'boz']);
        $client->extra_context(['biz' => 'baz']);

        $client->captureMessage('test');
        $events = $client->getSentEvents();
        $this->assertEquals(1, count($events));
        $event = array_pop($events);
        $this->assertEquals([
            'foo' => 'bar',
            'biz' => 'baz',
        ], $event['extra']);
    }

    /**
     * @covers \Raven\Client::captureException
     */
    public function testCaptureExceptionContainingLatin1()
    {
        // If somebody has a non-utf8 codebase, she/he should add the encoding to the detection order
        $options = [
            'mb_detect_order' => [
                'ISO-8859-1', 'ASCII', 'UTF-8'
            ]
        ];

        $client = new Dummy_Raven_Client($options);

        // we need a non-utf8 string here.
        // nobody writes non-utf8 in exceptions, but it is the easiest way to test.
        // in real live non-utf8 may be somewhere in the exception's stacktrace
        $utf8String = 'äöü';
        $latin1String = utf8_decode($utf8String);
        $client->captureException(new \Exception($latin1String));

        $events = $client->getSentEvents();
        $event = array_pop($events);

        $this->assertEquals($utf8String, $event['exception']['values'][0]['value']);
    }


    public function testCaptureExceptionInLatin1File()
    {
        // If somebody has a non-utf8 codebase, she/he should add the encoding to the detection order
        $options = [
            'mb_detect_order' => [
                'ISO-8859-1', 'ASCII', 'UTF-8'
            ]
        ];

        $client = new Dummy_Raven_Client($options);

        require_once(__DIR__ . '/Fixtures/code/Latin1File.php');

        $events = $client->getSentEvents();
        $event = array_pop($events);

        $stackTrace = array_pop($event['exception']['values'][0]['stacktrace']['frames']);

        $utf8String = "// äöü";
        $found = false;
        foreach ($stackTrace['pre_context'] as $line) {
            if ($line == $utf8String) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found);
    }

    /**
     * @covers \Raven\Client::captureLastError
     */
    public function testCaptureLastError()
    {
        if (function_exists('error_clear_last')) {
            error_clear_last();
        }
        $client = new Dummy_Raven_Client();
        $this->assertNull($client->captureLastError());
        $this->assertEquals(0, count($client->getSentEvents()));

        /** @var $undefined */
        /** @noinspection PhpExpressionResultUnusedInspection */
        @$undefined;

        $client->captureLastError();
        $events = $client->getSentEvents();
        $this->assertEquals(1, count($events));
        $event = array_pop($events);
        $this->assertEquals('Undefined variable: undefined', $event['exception']['values'][0]['value']);
    }

    /**
     * @covers \Raven\Client::getLastEventID
     */
    public function testGetLastEventID()
    {
        $client = new Dummy_Raven_Client();
        $client->capture(['message' => 'test', 'event_id' => 'abc']);
        $this->assertEquals('abc', $client->getLastEventID());
    }

    /**
     * @covers \Raven\Client::setTransport
     */
    public function testCustomTransport()
    {
        $events = [];

        // transport test requires default client
        $client = new \Raven\Client('https://public:secret@sentry.example.com/1', [
            'install_default_breadcrumb_handlers' => false,
        ]);
        $client->setTransport(function ($client, $data) use (&$events) {
            $events[] = $data;
        });
        $client->capture(['message' => 'test', 'event_id' => 'abc']);
        $this->assertEquals(1, count($events));
    }

    /**
     * @covers \Raven\Client::setAppPath
     */
    public function testAppPathLinux()
    {
        $client = new Dummy_Raven_Client();
        $client->setAppPath('/foo/bar');

        $this->assertEquals('/foo/bar/', $client->getAppPath());

        $client->setAppPath('/foo/baz/');

        $this->assertEquals('/foo/baz/', $client->getAppPath());
    }

    /**
     * @covers \Raven\Client::setAppPath
     */
    public function testAppPathWindows()
    {
        $client = new Dummy_Raven_Client();
        $client->setAppPath('C:\\foo\\bar\\');

        $this->assertEquals('C:\\foo\\bar\\', $client->getAppPath());
    }

    /**
     * @expectedException \Raven\Exception
     * @expectedExceptionMessage Raven_Client->install() must only be called once
     */
    public function testCannotInstallTwice()
    {
        $client = new Dummy_Raven_Client('https://public:secret@sentry.example.com/1');
        $client->install();
        $client->install();
    }

    public function cb1($data)
    {
        $this->assertEquals('test', $data['message']);
        return false;
    }

    public function cb2($data)
    {
        $this->assertEquals('test', $data['message']);
        return true;
    }

    public function cb3(&$data)
    {
        unset($data['message']);
        return true;
    }

    /**
     * @covers \Raven\Client::send
     */
    public function testSendCallback()
    {
        $client = new Dummy_Raven_Client(['send_callback' => [$this, 'cb1']]);
        $client->captureMessage('test');
        $events = $client->getSentEvents();
        $this->assertEquals(0, count($events));

        $client = new Dummy_Raven_Client(['send_callback' => [$this, 'cb2']]);
        $client->captureMessage('test');
        $events = $client->getSentEvents();
        $this->assertEquals(1, count($events));

        $client = new Dummy_Raven_Client(['send_callback' => [$this, 'cb3']]);
        $client->captureMessage('test');
        $events = $client->getSentEvents();
        $this->assertEquals(1, count($events));
        $this->assertTrue(empty($events[0]['message']));
    }

    /**
     * @covers \Raven\Client::sanitize
     */
    public function testSanitizeExtra()
    {
        $client = new Dummy_Raven_Client();
        $data = ['extra' => [
            'context' => [
                'line' => 1216,
                'stack' => [
                    1, [2], 3
                ],
            ],
        ]];
        $client->sanitize($data);

        $this->assertEquals(['extra' => [
            'context' => [
                'line' => 1216,
                'stack' => [
                    1, 'Array of length 1', 3
                ],
            ],
        ]], $data);
    }

    /**
     * @covers \Raven\Client::sanitize
     */
    public function testSanitizeObjects()
    {
        $client = new Dummy_Raven_Client(
            null, [
                'serialize_all_object' => true,
            ]
        );
        $clone = new Dummy_Raven_Client();
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
                    $new_value[$property2->getName()] = 'Array of length '.count($sub_value);
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

    /**
     * @covers \Raven\Client::sanitize
     */
    public function testSanitizeTags()
    {
        $client = new Dummy_Raven_Client();
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

    /**
     * @covers \Raven\Client::sanitize
     */
    public function testSanitizeUser()
    {
        $client = new Dummy_Raven_Client();
        $data = ['user' => [
            'email' => 'foo@example.com',
        ]];
        $client->sanitize($data);

        $this->assertEquals(['user' => [
            'email' => 'foo@example.com',
        ]], $data);
    }

    /**
     * @covers \Raven\Client::sanitize
     */
    public function testSanitizeRequest()
    {
        $client = new Dummy_Raven_Client();
        $data = ['request' => [
            'context' => [
                'line' => 1216,
                'stack' => [
                    1, [2], 3
                ],
            ],
        ]];
        $client->sanitize($data);

        $this->assertEquals(['request' => [
            'context' => [
                'line' => 1216,
                'stack' => [
                    1, 'Array of length 1', 3
                ],
            ],
        ]], $data);
    }

    /**
     * @covers \Raven\Client::sanitize
     */
    public function testSanitizeContexts()
    {
        $client = new Dummy_Raven_Client();
        $data = ['contexts' => [
            'context' => [
                'line' => 1216,
                'stack' => [
                    1, [
                        'foo' => 'bar',
                        'level4' => [['level5', 'level5 a'], 2],
                    ], 3
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
                    ], 3
                ],
            ],
        ]], $data);
    }

    /**
     * @covers \Raven\Client::buildCurlCommand
     */
    public function testBuildCurlCommandEscapesInput()
    {
        $data = '{"foo": "\'; ls;"}';
        $client = new Dummy_Raven_Client();
        $result = $client->buildCurlCommand('http://foo.com', $data, []);
        $this->assertEquals('curl -X POST -d \'{"foo": "\'\\\'\'; ls;"}\' \'http://foo.com\' -m 5 > /dev/null 2>&1 &', $result);

        $result = $client->buildCurlCommand('http://foo.com', $data, ['key' => 'value']);
        $this->assertEquals('curl -X POST -H \'key: value\' -d \'{"foo": "\'\\\'\'; ls;"}\' \'http://foo.com\' -m 5 > /dev/null 2>&1 &', $result);

        $verify_ssl = new \ReflectionProperty($client, 'verify_ssl');
        $verify_ssl->setAccessible(true);
        $verify_ssl->setValue($client, false);
        $result = $client->buildCurlCommand('http://foo.com', $data, []);
        $this->assertEquals('curl -X POST -d \'{"foo": "\'\\\'\'; ls;"}\' \'http://foo.com\' -m 5 -k > /dev/null 2>&1 &', $result);

        $result = $client->buildCurlCommand('http://foo.com', $data, ['key' => 'value']);
        $this->assertEquals('curl -X POST -H \'key: value\' -d \'{"foo": "\'\\\'\'; ls;"}\' \'http://foo.com\' -m 5 -k > /dev/null 2>&1 &', $result);
    }

    /**
     * @covers \Raven\Client::user_context
     */
    public function testUserContextWithoutMerge()
    {
        $client = new Dummy_Raven_Client();
        $client->user_context(['foo' => 'bar'], false);
        $client->user_context(['baz' => 'bar'], false);
        $context = new \ReflectionProperty($client, 'context');
        $context->setAccessible(true);
        $this->assertEquals(['baz' => 'bar'], $context->getValue($client)->user);
    }

    /**
     * @covers \Raven\Client::user_context
     */
    public function testUserContextWithMerge()
    {
        $client = new Dummy_Raven_Client();
        $client->user_context(['foo' => 'bar'], true);
        $client->user_context(['baz' => 'bar'], true);
        $context = new \ReflectionProperty($client, 'context');
        $context->setAccessible(true);
        $this->assertEquals(['foo' => 'bar', 'baz' => 'bar'], $context->getValue($client)->user);
    }

    /**
     * @covers \Raven\Client::user_context
     */
    public function testUserContextWithMergeAndNull()
    {
        $client = new Dummy_Raven_Client();
        $client->user_context(['foo' => 'bar'], true);
        $client->user_context(null, true);
        $context = new \ReflectionProperty($client, 'context');
        $context->setAccessible(true);
        $this->assertEquals(['foo' => 'bar'], $context->getValue($client)->user);
    }

    /**
     * Set the server array to the test values, check the current url
     *
     * @dataProvider currentUrlProvider
     * @param array $serverVars
     * @param array $options
     * @param string $expected - the url expected
     * @param string $message - fail message
     * @covers \Raven\Client::get_current_url
     * @covers \Raven\Client::isHttps
     */
    public function testCurrentUrl($serverVars, $options, $expected, $message)
    {
        $_SERVER = $serverVars;

        $client = new Dummy_Raven_Client($options);
        $result = $client->test_get_current_url();

        $this->assertSame($expected, $result, $message);
    }

    /**
     * Arrays of:
     *  $_SERVER data
     *  config
     *  expected url
     *  Fail message
     *
     * @return array
     */
    public function currentUrlProvider()
    {
        return [
            [
                [],
                [],
                null,
                'No url expected for empty REQUEST_URI'
            ],
            [
                [
                    'REQUEST_URI' => '/',
                    'HTTP_HOST' => 'example.com',
                ],
                [],
                'http://example.com/',
                'The url is expected to be http with the request uri'
            ],
            [
                [
                    'REQUEST_URI' => '/',
                    'HTTP_HOST' => 'example.com',
                    'HTTPS' => 'on'
                ],
                [],
                'https://example.com/',
                'The url is expected to be https because of HTTPS on'
            ],
            [
                [
                    'REQUEST_URI' => '/',
                    'HTTP_HOST' => 'example.com',
                    'SERVER_PORT' => '443'
                ],
                [],
                'https://example.com/',
                'The url is expected to be https because of the server port'
            ],
            [
                [
                    'REQUEST_URI' => '/',
                    'HTTP_HOST' => 'example.com',
                    'X-FORWARDED-PROTO' => 'https'
                ],
                [],
                'http://example.com/',
                'The url is expected to be http because the X-Forwarded header is ignored'
            ],
            [
                [
                    'REQUEST_URI' => '/',
                    'HTTP_HOST' => 'example.com',
                    'X-FORWARDED-PROTO' => 'https'
                ],
                ['trust_x_forwarded_proto' => true],
                'https://example.com/',
                'The url is expected to be https because the X-Forwarded header is trusted'
            ]
        ];
    }

    /**
     * @covers \Raven\Client::uuid4()
     */
    public function testUuid4()
    {
        $method = new \ReflectionMethod('\\Raven\\Client', 'uuid4');
        $method->setAccessible(true);
        for ($i = 0; $i < 1000; $i++) {
            $this->assertRegExp('/^[0-9a-z-]+$/', $method->invoke(null));
        }
    }

    /**
     * @covers \Raven\Client::getEnvironment
     * @covers \Raven\Client::setEnvironment
     * @covers \Raven\Client::getRelease
     * @covers \Raven\Client::setRelease
     * @covers \Raven\Client::getAppPath
     * @covers \Raven\Client::setAppPath
     * @covers \Raven\Client::getExcludedAppPaths
     * @covers \Raven\Client::setExcludedAppPaths
     * @covers \Raven\Client::getPrefixes
     * @covers \Raven\Client::setPrefixes
     * @covers \Raven\Client::getSendCallback
     * @covers \Raven\Client::setSendCallback
     * @covers \Raven\Client::getTransport
     * @covers \Raven\Client::setTransport
     * @covers \Raven\Client::getServerEndpoint
     * @covers \Raven\Client::getLastError
     * @covers \Raven\Client::getLastEventID
     * @covers \Raven\Client::getExtra_data
     * @covers \Raven\Client::setProcessors
     * @covers \Raven\Client::getLastSentryError
     * @covers \Raven\Client::getShutdownFunctionHasBeenSet
     */
    public function testGettersAndSetters()
    {
        $client = new Dummy_Raven_Client();
        $property_method__convert_path = new \ReflectionMethod('\\Raven\\Client', '_convertPath');
        $property_method__convert_path->setAccessible(true);
        $callable = [$this, 'stabClosureVoid'];

        $data = [
            ['environment', null, 'value', ],
            ['environment', null, null, ],
            ['release', null, 'value', ],
            ['release', null, null, ],
            ['app_path', null, 'value', $property_method__convert_path->invoke($client, 'value')],
            ['app_path', null, null,],
            ['app_path', null, false, null,],
            ['excluded_app_paths', null, ['value'],
             [$property_method__convert_path->invoke($client, 'value')]],
            ['excluded_app_paths', null, [], null],
            ['excluded_app_paths', null, null],
            ['prefixes', null, ['value'], [$property_method__convert_path->invoke($client, 'value')]],
            ['prefixes', null, []],
            ['send_callback', null, $callable],
            ['send_callback', null, null],
            ['transport', null, $callable],
            ['transport', null, null],
            ['server', 'ServerEndpoint', 'http://example.com/'],
            ['server', 'ServerEndpoint', 'http://example.org/'],
            ['_lasterror', null, null,],
            ['_lasterror', null, 'value',],
            ['_lasterror', null, mt_rand(100, 999),],
            ['_last_sentry_error', null, (object)['error' => 'test',],],
            ['_last_event_id', null, mt_rand(100, 999),],
            ['_last_event_id', null, 'value',],
            ['extra_data', null, ['key' => 'value'],],
            ['processors', 'processors', [],],
            ['processors', 'processors', ['key' => 'value'],],
            ['_shutdown_function_has_been_set', null, true],
            ['_shutdown_function_has_been_set', null, false],
        ];
        foreach ($data as &$datum) {
            $this->subTestGettersAndSettersDatum($client, $datum);
        }
        foreach ($data as &$datum) {
            $client = new Dummy_Raven_Client();
            $this->subTestGettersAndSettersDatum($client, $datum);
        }
    }

    private function subTestGettersAndSettersDatum(\Raven\Client $client, $datum)
    {
        if (count($datum) == 3) {
            list($property_name, $function_name, $value_in) = $datum;
            $value_out = $value_in;
        } else {
            list($property_name, $function_name, $value_in, $value_out) = $datum;
        }
        if (is_null($function_name)) {
            $function_name = str_replace('_', '', $property_name);
        }

        $method_get_name = 'get'.$function_name;
        $method_set_name = 'set'.$function_name;
        $property = new \ReflectionProperty('\\Raven\\Client', $property_name);
        $property->setAccessible(true);

        if (method_exists($client, $method_set_name)) {
            $setter_output = $client->$method_set_name($value_in);
            if (!is_null($setter_output) and is_object($setter_output)) {
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
        if (is_null($expected_value)) {
            $this->assertNull($actual_value);
        } elseif ($expected_value === true) {
            $this->assertTrue($actual_value);
        } elseif ($expected_value === false) {
            $this->assertFalse($actual_value);
        } elseif (is_string($expected_value) or is_integer($expected_value) or is_double($expected_value)) {
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
     * @covers \Raven\Client::_convertPath
     */
    public function test_convertPath()
    {
        $property = new \ReflectionMethod('\\Raven\Client', '_convertPath');
        $property->setAccessible(true);

        $this->assertEquals('/foo/bar/', $property->invoke(null, '/foo/bar'));
        $this->assertEquals('/foo/bar/', $property->invoke(null, '/foo/bar/'));
        $this->assertEquals('foo/bar', $property->invoke(null, 'foo/bar'));
        $this->assertEquals('foo/bar/', $property->invoke(null, 'foo/bar/'));
        $this->assertEquals(dirname(__DIR__).'/', $property->invoke(null, __DIR__.'/../'));
        $this->assertEquals(dirname(dirname(__DIR__)).'/', $property->invoke(null, __DIR__.'/../../'));
    }

    /**
     * @covers \Raven\Client::getDefaultProcessors
     */
    public function testGetDefaultProcessors()
    {
        foreach (\Raven\Client::getDefaultProcessors() as $class_name) {
            $this->assertInternalType('string', $class_name);
            $this->assertTrue(class_exists($class_name));
            $reflection = new \ReflectionClass($class_name);
            $this->assertTrue($reflection->isSubclassOf('\\Raven\\Processor'));
            $this->assertFalse($reflection->isAbstract());
        }
    }

    /**
     * @covers \Raven\Client::get_default_ca_cert
     */
    public function testGet_default_ca_cert()
    {
        $reflection = new \ReflectionMethod('\\Raven\Client', 'get_default_ca_cert');
        $reflection->setAccessible(true);
        $this->assertFileExists($reflection->invoke(null));
    }

    /**
     * @covers \Raven\Client::translateSeverity
     * @covers \Raven\Client::registerSeverityMap
     */
    public function testTranslateSeverity()
    {
        $reflection = new \ReflectionProperty('\\Raven\Client', 'severity_map');
        $reflection->setAccessible(true);
        $client = new Dummy_Raven_Client();

        $predefined = [E_ERROR, E_WARNING, E_PARSE, E_NOTICE, E_CORE_ERROR, E_CORE_WARNING,
                       E_COMPILE_ERROR, E_COMPILE_WARNING, E_USER_ERROR, E_USER_WARNING,
                       E_USER_NOTICE, E_STRICT, E_RECOVERABLE_ERROR, ];
        $predefined[] = E_DEPRECATED;
        $predefined[] = E_USER_DEPRECATED;
        $predefined_values = ['debug', 'info', 'warning', 'warning', 'error', 'fatal', ];

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
        $client->registerSeverityMap([123456 => 'foo', ]);
        $this->assertMixedValueAndArray([123456 => 'foo'], $reflection->getValue($client));
        foreach ($predefined as &$key) {
            $this->assertContains($client->translateSeverity($key), $predefined_values);
        }
        $this->assertEquals('foo', $client->translateSeverity(123456));
        $this->assertEquals('error', $client->translateSeverity(123457));
        // step 4
        $client->registerSeverityMap([E_USER_ERROR => 'bar', ]);
        $this->assertEquals('bar', $client->translateSeverity(E_USER_ERROR));
        $this->assertEquals('error', $client->translateSeverity(123456));
        $this->assertEquals('error', $client->translateSeverity(123457));
        // step 5
        $client->registerSeverityMap([E_USER_ERROR => 'bar', 123456 => 'foo', ]);
        $this->assertEquals('bar', $client->translateSeverity(E_USER_ERROR));
        $this->assertEquals('foo', $client->translateSeverity(123456));
        $this->assertEquals('error', $client->translateSeverity(123457));
    }

    /**
     * @covers \Raven\Client::getUserAgent
     */
    public function testGetUserAgent()
    {
        $this->assertRegExp('|^[0-9a-z./_-]+$|i', \Raven\Client::getUserAgent());
    }

    public function testCaptureExceptionWithLogger()
    {
        $client = new Dummy_Raven_Client();
        $client->captureException(new \Exception(), null, 'foobar');

        $events = $client->getSentEvents();
        $this->assertEquals(1, count($events));
        $event = array_pop($events);
        $this->assertEquals('foobar', $event['logger']);
    }

    /**
     * @covers \Raven\Client::__construct
     * @covers \Raven\Client::send
     * @covers \Raven\Client::send_remote
     * @covers \Raven\Client::send_http
     */
    public function testCurl_method()
    {
        // step 1
        $client = new Dummy_Raven_Client_With_Overrided_Direct_Send(
            'http://public:secret@example.com/1', [
                'curl_method' => 'foobar',
                'install_default_breadcrumb_handlers' => false,
            ]
        );
        $client->captureMessage('foobar');
        $this->assertTrue($client->_send_http_synchronous);
        $this->assertFalse($client->_send_http_asynchronous_curl_exec_called);

        // step 2
        $client = new Dummy_Raven_Client_With_Overrided_Direct_Send(
            'http://public:secret@example.com/1', [
                'curl_method' => 'exec',
                'install_default_breadcrumb_handlers' => false,
            ]
        );
        $client->captureMessage('foobar');
        $this->assertFalse($client->_send_http_synchronous);
        $this->assertTrue($client->_send_http_asynchronous_curl_exec_called);
    }

    /**
     * @covers \Raven\Client::__construct
     * @covers \Raven\Client::send
     * @covers \Raven\Client::send_remote
     * @covers \Raven\Client::send_http
     */
    public function testCurl_method_async()
    {
        // step 1
        $client = new Dummy_Raven_Client_With_Overrided_Direct_Send(
            'http://public:secret@example.com/1', [
                'curl_method' => 'async',
                'install_default_breadcrumb_handlers' => false,
            ]
        );
        $object = $client->get_curl_handler();
        $this->assertInternalType('object', $object);
        $this->assertEquals('Raven\\CurlHandler', get_class($object));

        $reflection = new \ReflectionProperty('Raven\\CurlHandler', 'options');
        $reflection->setAccessible(true);
        $this->assertEquals($reflection->getValue($object), $client->get_curl_options());

        // step 2
        $ch = new Dummy_Raven_CurlHandler();
        $client->set_curl_handler($ch);
        $client->captureMessage('foobar');
        $this->assertFalse($client->_send_http_synchronous);
        $this->assertFalse($client->_send_http_asynchronous_curl_exec_called);
        $this->assertTrue($ch->_enqueue_called);
    }

    /**
     * @backupGlobals
     * @covers \Raven\Client::__construct
     */
    public function testConstructWithServerDSN()
    {
        $_SERVER['SENTRY_DSN'] = 'http://public:secret@example.com/1';
        $client = new Dummy_Raven_Client();
        $this->assertAttributeEquals(1, 'project', $client);
        $this->assertAttributeEquals('http://example.com/api/1/store/', 'server', $client);
        $this->assertAttributeEquals('public', 'public_key', $client);
        $this->assertAttributeEquals('secret', 'secret_key', $client);
    }

    /**
     * @backupGlobals
     * @covers \Raven\Client::_server_variable
     */
    public function test_server_variable()
    {
        $method = new \ReflectionMethod('\\Raven\Client', '_server_variable');
        $method->setAccessible(true);
        foreach ($_SERVER as $key => $value) {
            $actual = $method->invoke(null, $key);
            $this->assertNotNull($actual);
            $this->assertEquals($value, $actual);
        }
        foreach (['foo', 'bar', 'foobar', '123456', 'SomeLongNonExistedKey'] as $key => $value) {
            if (!isset($_SERVER[$key])) {
                $actual = $method->invoke(null, $key);
                $this->assertNotNull($actual);
                $this->assertEquals('', $actual);
            }
            unset($_SERVER[$key]);
            $actual = $method->invoke(null, $key);
            $this->assertNotNull($actual);
            $this->assertEquals('', $actual);
        }
    }

    public function testEncode()
    {
        $client = new Dummy_Raven_Client();
        $data = ['some' => (object)['value' => 'data'], 'foo' => ['bar', null, 123], false];
        $json_stringify = json_encode($data);
        $value = $client->encode($data);
        $this->assertNotFalse($value);
        $this->assertRegExp('_^[a-zA-Z0-9/=]+$_', $value, '\\Raven\\Client::encode returned malformed data');
        $decoded = base64_decode($value);
        $this->assertInternalType('string', $decoded, 'Can not use base64 decode on the encoded blob');
        if (function_exists("gzcompress")) {
            $decoded = gzuncompress($decoded);
            $this->assertEquals($json_stringify, $decoded, 'Can not decompress compressed blob');
        } else {
            $this->assertEquals($json_stringify, $decoded);
        }
    }

    /**
     * @covers \Raven\Client::__construct
     * @covers \Raven\Client::registerDefaultBreadcrumbHandlers
     */
    public function testRegisterDefaultBreadcrumbHandlers()
    {
        if (isset($_ENV['HHVM']) and ($_ENV['HHVM'] == 1)) {
            $this->markTestSkipped('HHVM stacktrace behaviour');
            return;
        }
        $previous = set_error_handler([$this, 'stabClosureErrorHandler'], E_USER_NOTICE);
        new \Raven\Client(null, []);
        $this->_closure_called = false;
        trigger_error('foobar', E_USER_NOTICE);
        $u = $this->_closure_called;
        $debug_backtrace = $this->_debug_backtrace;
        set_error_handler($previous, E_ALL);
        $this->assertTrue($u);
        if (isset($debug_backtrace[1]['function']) and ($debug_backtrace[1]['function'] == 'call_user_func')
            and version_compare(PHP_VERSION, '7.0', '>=')
        ) {
            $offset = 2;
        } elseif (version_compare(PHP_VERSION, '7.0', '>=')) {
            $offset = 1;
        } else {
            $offset = 2;
        }
        $this->assertEquals('Raven\\Breadcrumbs\\ErrorHandler', $debug_backtrace[$offset]['class']);
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

    /**
     * @covers \Raven\Client::onShutdown
     * @covers \Raven\Client::sendUnsentErrors
     * @covers \Raven\Client::capture
     */
    public function testOnShutdown()
    {
        // step 1
        $client = new Dummy_Raven_Client_With_Overrided_Direct_Send(
            'http://public:secret@example.com/1', [
                'curl_method' => 'foobar',
                'install_default_breadcrumb_handlers' => false,
            ]
        );
        $_pending_events = new \ReflectionProperty($client, '_pending_events');
        $_pending_events->setAccessible(true);
        $this->assertAttributeCount(0, '_pending_events', $client);
        $_pending_events->setValue($client, array_merge($_pending_events->getValue($client), [['foo' => 'bar']]));
        $client->sendUnsentErrors();
        $this->assertTrue($client->_send_http_synchronous);
        $this->assertFalse($client->_send_http_asynchronous_curl_exec_called);
        $this->assertAttributeCount(0, '_pending_events', $client);

        // step 2
        $client->_send_http_synchronous = false;
        $client->_send_http_asynchronous_curl_exec_called = false;

        $client->store_errors_for_bulk_send = true;
        $client->captureMessage('foobar');
        $this->assertAttributeCount(1, '_pending_events', $client);
        $this->assertFalse($client->_send_http_synchronous or $client->_send_http_asynchronous_curl_exec_called);
        $client->_send_http_synchronous = false;
        $client->_send_http_asynchronous_curl_exec_called = false;

        // step 3
        $client->onShutdown();
        $this->assertTrue($client->_send_http_synchronous);
        $this->assertFalse($client->_send_http_asynchronous_curl_exec_called);
        $this->assertAttributeCount(0, '_pending_events', $client);

        // step 1
        $client = null;
        $client = new Dummy_Raven_Client_With_Overrided_Direct_Send(
            'http://public:secret@example.com/1', [
                'curl_method' => 'async',
                'install_default_breadcrumb_handlers' => false,
            ]
        );
        $ch = new Dummy_Raven_CurlHandler();
        $client->set_curl_handler($ch);
        $client->captureMessage('foobar');
        $client->onShutdown();
        $client = null;
        $this->assertTrue($ch->_join_called);
    }

    /**
     * @covers \Raven\Client::send
     */
    public function testNonWorkingSendSendCallback()
    {
        // step 1
        $client = new Dummy_Raven_Client_With_Overrided_Direct_Send(
            'http://public:secret@example.com/1', [
                'curl_method' => 'foobar',
                'install_default_breadcrumb_handlers' => false,
            ]
        );
        $this->_closure_called = false;
        $client->setSendCallback([$this, 'stabClosureNull']);
        $this->assertFalse($this->_closure_called);
        $data = ['foo' => 'bar'];
        $client->send($data);
        $this->assertTrue($this->_closure_called);
        $this->assertTrue($client->_send_http_synchronous or $client->_send_http_asynchronous_curl_exec_called);
        // step 2
        $this->_closure_called = false;
        $client->_send_http_synchronous = false;
        $client->_send_http_asynchronous_curl_exec_called = false;
        $client->setSendCallback([$this, 'stabClosureFalse']);
        $this->assertFalse($this->_closure_called);
        $data = ['foo' => 'bar'];
        $client->send($data);
        $this->assertTrue($this->_closure_called);
        $this->assertFalse($client->_send_http_synchronous or $client->_send_http_asynchronous_curl_exec_called);
    }

    /**
     * @covers \Raven\Client::send
     */
    public function testNonWorkingSendDSNEmpty()
    {
        $client = new Dummy_Raven_Client_With_Overrided_Direct_Send(
            'http://public:secret@example.com/1', [
                'curl_method' => 'foobar',
                'install_default_breadcrumb_handlers' => false,
            ]
        );
        $server = new \ReflectionProperty($client, 'server');
        $server->setAccessible(true);
        $server->setValue($client, null);
        $data = ['foo' => 'bar'];
        $client->send($data);
        $this->assertFalse($client->_send_http_synchronous or $client->_send_http_asynchronous_curl_exec_called);
    }

    /**
     * @covers \Raven\Client::send
     */
    public function testNonWorkingSendSetTransport()
    {
        // step 1
        $client = new Dummy_Raven_Client_With_Overrided_Direct_Send(
            'http://public:secret@example.com/1', [
                'curl_method' => 'foobar',
                'install_default_breadcrumb_handlers' => false,
            ]
        );
        $this->_closure_called = false;
        $client->setTransport([$this, 'stabClosureNull']);
        $this->assertFalse($this->_closure_called);
        $data = ['foo' => 'bar'];
        $client->send($data);
        $this->assertTrue($this->_closure_called);
        $this->assertFalse($client->_send_http_synchronous or $client->_send_http_asynchronous_curl_exec_called);
        // step 2
        $this->_closure_called = false;
        $client->setSendCallback([$this, 'stabClosureFalse']);
        $this->assertFalse($this->_closure_called);
        $data = ['foo' => 'bar'];
        $client->send($data);
        $this->assertTrue($this->_closure_called);
        $this->assertFalse($client->_send_http_synchronous or $client->_send_http_asynchronous_curl_exec_called);
    }

    /**
     * @covers \Raven\Client::__construct
     */
    public function test__construct_handlers()
    {
        foreach ([true, false] as $u1) {
            foreach ([true, false] as $u2) {
                $client = new Dummy_Raven_Client(
                    null, [
                        'install_default_breadcrumb_handlers' => $u1,
                        'install_shutdown_handler' => $u2,
                    ]
                );
                $this->assertEquals($u1, $client->dummy_breadcrumbs_handlers_has_set);
                $this->assertEquals($u2, $client->dummy_shutdown_handlers_has_set);
            }
        }
    }

    /**
     * @covers \Raven\Client::__destruct
     * @covers \Raven\Client::close_all_children_link
     */
    public function test__destruct_calls_close_functions()
    {
        $client = new Dummy_Raven_Client_With_Overrided_Direct_Send(
            'http://public:secret@example.com/1', [
                'install_default_breadcrumb_handlers' => false,
                'install_shutdown_handler' => false,
            ]
        );
        $client::$_close_curl_resource_called = false;
        $client->close_all_children_link();
        unset($client);
        $this->assertTrue(Dummy_Raven_Client_With_Overrided_Direct_Send::$_close_curl_resource_called);
    }

    /**
     * @covers \Raven\Client::get_user_data
     */
    public function testGet_user_data()
    {
        // step 1
        $client = new Dummy_Raven_Client();
        $output = $client->get_user_data();
        $this->assertInternalType('array', $output);
        $this->assertArrayHasKey('user', $output);
        $this->assertArrayHasKey('id', $output['user']);
        $session_old = $_SESSION;

        // step 2
        $session_id = session_id();
        session_write_close();
        session_id('');
        $output = $client->get_user_data();
        $this->assertInternalType('array', $output);
        $this->assertEquals(0, count($output));

        // step 3
        session_id($session_id);
        @session_start(['use_cookies' => false, ]);
        $_SESSION = ['foo' => 'bar'];
        $output = $client->get_user_data();
        $this->assertInternalType('array', $output);
        $this->assertArrayHasKey('user', $output);
        $this->assertArrayHasKey('id', $output['user']);
        $this->assertArrayHasKey('data', $output['user']);
        $this->assertArrayHasKey('foo', $output['user']['data']);
        $this->assertEquals('bar', $output['user']['data']['foo']);
        $_SESSION = $session_old;
    }

    /**
     * @covers \Raven\Client::capture
     * @covers \Raven\Client::setRelease
     * @covers \Raven\Client::setEnvironment
     */
    public function testCaptureLevel()
    {
        foreach ([\Raven\Client::MESSAGE_LIMIT * 3, 100] as $length) {
            $message = '';
            for ($i = 0; $i < $length; $i++) {
                $message .= chr($i % 256);
            }
            $client = new Dummy_Raven_Client();
            $client->capture(['message' => $message, ]);
            $events = $client->getSentEvents();
            $this->assertEquals(1, count($events));
            $event = array_pop($events);

            $this->assertEquals('error', $event['level']);
            $this->assertEquals(substr($message, 0, min(\Raven\Client::MESSAGE_LIMIT, $length)), $event['message']);
            $this->assertArrayNotHasKey('release', $event);
            $this->assertArrayNotHasKey('environment', $event);
        }

        $client = new Dummy_Raven_Client();
        $client->capture(['message' => 'foobar', ]);
        $events = $client->getSentEvents();
        $event = array_pop($events);
        $input = $client->get_http_data();
        $this->assertEquals($input['request'], $event['request']);
        $this->assertArrayNotHasKey('release', $event);
        $this->assertArrayNotHasKey('environment', $event);

        $client = new Dummy_Raven_Client();
        $client->capture(['message' => 'foobar', 'request' => ['foo' => 'bar'], ]);
        $events = $client->getSentEvents();
        $event = array_pop($events);
        $this->assertEquals(['foo' => 'bar'], $event['request']);
        $this->assertArrayNotHasKey('release', $event);
        $this->assertArrayNotHasKey('environment', $event);

        foreach ([false, true] as $u1) {
            foreach ([false, true] as $u2) {
                $client = new Dummy_Raven_Client();
                if ($u1) {
                    $client->setRelease('foo');
                }
                if ($u2) {
                    $client->setEnvironment('bar');
                }
                $client->capture(['message' => 'foobar', ]);
                $events = $client->getSentEvents();
                $event = array_pop($events);
                if ($u1) {
                    $this->assertEquals('foo', $event['release']);
                } else {
                    $this->assertArrayNotHasKey('release', $event);
                }
                if ($u2) {
                    $this->assertEquals('bar', $event['environment']);
                } else {
                    $this->assertArrayNotHasKey('environment', $event);
                }
            }
        }
    }

    /**
     * @covers \Raven\Client::capture
     */
    public function testCaptureNoUserAndRequest()
    {
        $client = new Dummy_Raven_Client_No_Http(null, [
            'install_default_breadcrumb_handlers' => false,
        ]);
        $session_id = session_id();
        session_write_close();
        session_id('');
        $client->capture(['user' => '', 'request' => '']);
        $events = $client->getSentEvents();
        $event = array_pop($events);
        $this->assertArrayNotHasKey('user', $event);
        $this->assertArrayNotHasKey('request', $event);

        // step 3
        session_id($session_id);
        @session_start(['use_cookies' => false, ]);
    }

    /**
     * @covers \Raven\Client::capture
     */
    public function testCaptureNonEmptyBreadcrumb()
    {
        $client = new Dummy_Raven_Client();
        $ts1 = microtime(true);
        $client->getBreadcrumbs()->record(['foo' => 'bar']);
        $client->getBreadcrumbs()->record(['honey' => 'clover']);
        $client->capture([]);
        $events = $client->getSentEvents();
        $event = array_pop($events);
        foreach ($event['breadcrumbs'] as &$crumb) {
            $this->assertGreaterThanOrEqual($ts1, $crumb['timestamp']);
            unset($crumb['timestamp']);
        }
        $this->assertEquals([
            ['foo' => 'bar'],
            ['honey' => 'clover'],
        ], $event['breadcrumbs']);
    }


    /**
     * @covers \Raven\Client::capture
     */
    public function testCaptureAutoLogStacks()
    {
        $client = new Dummy_Raven_Client();
        $client->capture(['auto_log_stacks' => true], true);
        $events = $client->getSentEvents();
        $event = array_pop($events);
        $this->assertArrayHasKey('stacktrace', $event);
        $this->assertInternalType('array', $event['stacktrace']['frames']);
    }

    /**
     * @covers \Raven\Client::send_http_asynchronous_curl_exec
     */
    public function testSend_http_asynchronous_curl_exec()
    {
        $client = new Dummy_Raven_Client_With_Sync_Override(
            'http://public:secret@example.com/1', [
                'curl_method' => 'exec',
                'install_default_breadcrumb_handlers' => false,
            ]
        );
        if (file_exists(Dummy_Raven_Client_With_Sync_Override::test_filename())) {
            unlink(Dummy_Raven_Client_With_Sync_Override::test_filename());
        }
        $client->captureMessage('foobar');
        $test_data = Dummy_Raven_Client_With_Sync_Override::get_test_data();
        $this->assertStringEqualsFile(Dummy_Raven_Client_With_Sync_Override::test_filename(), $test_data."\n");
    }

    /**
     * @covers \Raven\Client::close_curl_resource
     */
    public function testClose_curl_resource()
    {
        $raven = new Dummy_Raven_Client();
        $reflection = new \ReflectionProperty('\\Raven\Client', '_curl_instance');
        $reflection->setAccessible(true);
        $ch = curl_init();
        $reflection->setValue($raven, $ch);
        unset($ch);

        $this->assertInternalType('resource', $reflection->getValue($raven));
        $raven->close_curl_resource();
        $this->assertNull($reflection->getValue($raven));
    }

    /**
     * @covers \Raven\Client::send
     */
    public function testSampleRateAbsolute()
    {
        // step 1
        $client = new Dummy_Raven_Client_With_Overrided_Direct_Send(
            'http://public:secret@example.com/1', [
                'curl_method'                         => 'foobar',
                'install_default_breadcrumb_handlers' => false,
                'sample_rate'                         => 0,
            ]
        );
        for ($i = 0; $i < 1000; $i++) {
            $client->captureMessage('foobar');
            $this->assertFalse($client->_send_http_synchronous or $client->_send_http_asynchronous_curl_exec_called);
        }

        // step 2
        $client = new Dummy_Raven_Client_With_Overrided_Direct_Send(
            'http://public:secret@example.com/1', [
                'curl_method'                         => 'foobar',
                'install_default_breadcrumb_handlers' => false,
                'sample_rate'                         => 1,
            ]
        );
        for ($i = 0; $i < 1000; $i++) {
            $client->captureMessage('foobar');
            $this->assertTrue($client->_send_http_synchronous or $client->_send_http_asynchronous_curl_exec_called);
        }
    }

    /**
     * @covers \Raven\Client::send
     */
    public function testSampleRatePrc()
    {
        $client = new Dummy_Raven_Client_With_Overrided_Direct_Send(
            'http://public:secret@example.com/1', [
                'curl_method'                         => 'foobar',
                'install_default_breadcrumb_handlers' => false,
                'sample_rate'                         => 0.5,
            ]
        );
        $u_true = false;
        $u_false = false;
        for ($i = 0; $i < 1000; $i++) {
            $client->captureMessage('foobar');
            if ($client->_send_http_synchronous or $client->_send_http_asynchronous_curl_exec_called) {
                $u_true = true;
            } else {
                $u_false = true;
            }

            if ($u_true or $u_false) {
                return;
            }
        }

        $this->fail('sample_rate=0.5 can not produce fails and successes at the same time');
    }

    /**
     * @covers \Raven\Client::setAllObjectSerialize
     */
    public function testSetAllObjectSerialize()
    {
        $client = new \Raven\Client;

        $ref1 = new \ReflectionProperty($client, 'serializer');
        $ref1->setAccessible(true);
        $ref2 = new \ReflectionProperty($client, 'reprSerializer');
        $ref2->setAccessible(true);

        /**
         * @var \Raven\Serializer $o1
         * @var \Raven\Serializer $o2
         */
        $o1 = $ref1->getValue($client);
        $o2 = $ref2->getValue($client);

        $client->setAllObjectSerialize(true);
        $this->assertTrue($o1->getAllObjectSerialize());
        $this->assertTrue($o2->getAllObjectSerialize());

        $client->setAllObjectSerialize(false);
        $this->assertFalse($o1->getAllObjectSerialize());
        $this->assertFalse($o2->getAllObjectSerialize());
    }
}
